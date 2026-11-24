<?php

declare(strict_types=1);

namespace Trackified\Tests;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|\Throwable> $queue */
    public function __construct(private array $queue = [])
    {
    }

    /**
     * @param array<mixed>|string   $body
     * @param array<string, string> $headers
     */
    public function push(int $status, array|string $body = [], array $headers = []): self
    {
        $this->queue[] = new Response($status, $headers + ['Content-Type' => 'application/json'], \is_string($body) ? $body : (string) json_encode($body));

        return $this;
    }

    public function pushNetworkError(RequestInterface $request, string $message = 'connection refused'): self
    {
        $this->queue[] = new class($message, $request) extends \RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $m, private RequestInterface $r)
            {
                parent::__construct($m);
            }

            public function getRequest(): RequestInterface
            {
                return $this->r;
            }
        };

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? throw new \LogicException('No mock response queued');
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
