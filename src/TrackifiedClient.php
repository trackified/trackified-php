<?php

declare(strict_types=1);

namespace Trackified;

use Http\Discovery\Exception as DiscoveryException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Trackified\Exception\AuthenticationError;
use Trackified\Exception\ConnectionError;
use Trackified\Exception\TrackifiedError;
use Trackified\Http\CurlTransport;
use Trackified\Http\Psr18Transport;
use Trackified\Http\Response;
use Trackified\Http\Transport;
use Trackified\Resource\ApiKeys;
use Trackified\Resource\Carriers;
use Trackified\Resource\Trackings;
use Trackified\Resource\Usage;
use Trackified\Resource\WebhookDeliveries;
use Trackified\Resource\Webhooks;

final class TrackifiedClient
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.trackified.net';

    public readonly Trackings $trackings;
    public readonly Carriers $carriers;
    public readonly Webhooks $webhooks;
    public readonly WebhookDeliveries $webhookDeliveries;
    public readonly ApiKeys $apiKeys;
    public readonly Usage $usage;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly Transport $transport;

    /**
     * @param ?string          $apiKey     defaults to the TRACKIFIED_API_KEY environment variable
     * @param ?string          $baseUrl    defaults to TRACKIFIED_BASE_URL, then https://api.trackified.net
     * @param float            $timeout    seconds; applies to the built-in curl transport (configure your own PSR-18 client's timeout on it)
     * @param int              $maxRetries retries for network errors, 408, 429 and 5xx
     * @param ?ClientInterface $httpClient PSR-18 client; discovered (Guzzle, Symfony, ...) when null, curl fallback if none
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        public readonly float $timeout = 30.0,
        public readonly int $maxRetries = 2,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $apiKey ??= self::env('TRACKIFIED_API_KEY');
        if (null === $apiKey || '' === $apiKey) {
            throw new AuthenticationError('No API key provided. Pass one to new TrackifiedClient() or set TRACKIFIED_API_KEY.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?? self::env('TRACKIFIED_BASE_URL') ?? self::DEFAULT_BASE_URL, '/');
        $this->transport = self::transport($httpClient, $requestFactory, $streamFactory, $timeout);

        $this->trackings = new Trackings($this);
        $this->carriers = new Carriers($this);
        $this->webhooks = new Webhooks($this);
        $this->webhookDeliveries = new WebhookDeliveries($this);
        $this->apiKeys = new ApiKeys($this);
        $this->usage = new Usage($this);
    }

    /**
     * Low-level request; resources wrap this. Returns the decoded JSON body ([] when empty).
     *
     * @param array<string, mixed> $query
     * @param array<mixed>|null    $body
     *
     * @return array<mixed>
     *
     * @throws TrackifiedError
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?string $idempotencyKey = null): array
    {
        $url = $this->baseUrl.$path;
        $query = array_map(static fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, array_filter($query, static fn ($v) => null !== $v));
        if ([] !== $query) {
            $url .= '?'.http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'User-Agent' => 'trackified-php/'.self::VERSION,
            'Accept' => 'application/json',
        ];
        $payload = null;
        if (null !== $body) {
            $headers['Content-Type'] = 'application/json';
            $payload = [] === $body ? '{}' : json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        }
        if (null !== $idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        for ($attempt = 0;; ++$attempt) {
            try {
                $response = $this->transport->send($method, $url, $headers, $payload);
            } catch (ConnectionError $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                self::sleep(self::backoff($attempt, null));
                continue;
            }

            if ($response->status >= 200 && $response->status < 300) {
                return self::decode($response);
            }
            $retryable = 408 === $response->status || 429 === $response->status || $response->status >= 500;
            if (!$retryable || $attempt >= $this->maxRetries) {
                throw TrackifiedError::fromResponse($response);
            }
            self::sleep(self::backoff($attempt, $response->header('retry-after')));
        }
    }

    /** @internal */
    public static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = \chr((\ord($b[6]) & 0x0F) | 0x40);
        $b[8] = \chr((\ord($b[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** @return array<mixed> */
    private static function decode(Response $response): array
    {
        if ('' === trim($response->body)) {
            return [];
        }
        try {
            $decoded = json_decode($response->body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TrackifiedError('Invalid JSON from Trackified: '.$e->getMessage(), $response->status, null, $response->header('x-request-id'), $e);
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private static function backoff(int $attempt, ?string $retryAfter): float
    {
        if (is_numeric($retryAfter)) {
            return max(0.0, (float) $retryAfter);
        }
        $base = min(0.5 * 2 ** $attempt, 8.0);

        return $base / 2 + $base / 2 * (random_int(0, 1000) / 1000);
    }

    private static function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) ($seconds * 1_000_000));
        }
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return false === $value || '' === $value ? null : $value;
    }

    private static function transport(?ClientInterface $client, ?RequestFactoryInterface $requestFactory, ?StreamFactoryInterface $streamFactory, float $timeout): Transport
    {
        try {
            return new Psr18Transport(
                $client ?? Psr18ClientDiscovery::find(),
                $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
                $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            );
        } catch (DiscoveryException $e) {
            if (null !== $client) {
                throw $e;
            }

            return new CurlTransport($timeout);
        }
    }
}
