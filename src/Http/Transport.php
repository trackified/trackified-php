<?php

declare(strict_types=1);

namespace Trackified\Http;

use Trackified\Exception\ConnectionError;

/** @internal */
interface Transport
{
    /**
     * @param array<string, string> $headers
     *
     * @throws ConnectionError on network failure
     */
    public function send(string $method, string $url, array $headers, ?string $body): Response;
}
