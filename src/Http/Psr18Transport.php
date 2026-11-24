<?php

declare(strict_types=1);

namespace Trackified\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Trackified\Exception\ConnectionError;

/** @internal */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $body) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // shortcut: PSR-18 has no timeout exception type, so match the message (Guzzle/curl "cURL error 28", Symfony "timeout"); switch to client-specific types if one is mis-classified.
            $timeout = 1 === preg_match('/timed? ?out|timeout|cURL error 28/i', $e->getMessage());

            throw new ConnectionError('Request to Trackified failed: '.$e->getMessage(), errorCode: $timeout ? ConnectionError::TIMEOUT : ConnectionError::CONNECTION, previous: $e);
        }

        $out = [];
        foreach ($response->getHeaders() as $name => $values) {
            $out[strtolower((string) $name)] = implode(', ', $values);
        }

        return new Response($response->getStatusCode(), $out, (string) $response->getBody());
    }
}
