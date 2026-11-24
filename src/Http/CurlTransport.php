<?php

declare(strict_types=1);

namespace Trackified\Http;

use Trackified\Exception\ConnectionError;

/**
 * Fallback used when no PSR-18 client is installed.
 *
 * @internal
 */
final class CurlTransport implements Transport
{
    public function __construct(private readonly float $timeout)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        if (!\function_exists('curl_init')) {
            throw new ConnectionError('No PSR-18 HTTP client found and ext-curl is not installed. Install guzzlehttp/guzzle or symfony/http-client.');
        }

        $responseHeaders = [];
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, '' === $method ? 'GET' : $method);
        curl_setopt_array($ch, [
            \CURLOPT_HTTPHEADER => $lines,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000),
            \CURLOPT_HEADERFUNCTION => static function (\CurlHandle $ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (2 === \count($parts)) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return \strlen($line);
            },
        ]);
        if (null !== $body) {
            curl_setopt($ch, \CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($ch);
        if (!\is_string($result)) {
            $error = curl_error($ch);
            throw new ConnectionError('Request to Trackified failed: '.$error);
        }
        $status = curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);

        /** @var array<string, string> $responseHeaders */
        return new Response($status, $responseHeaders, $result);
    }
}
