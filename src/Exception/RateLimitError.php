<?php

declare(strict_types=1);

namespace Trackified\Exception;

/** 429: back off for $retryAfter seconds. */
class RateLimitError extends TrackifiedError
{
    public function __construct(
        string $message,
        ?int $status = 429,
        ?string $errorCode = null,
        ?string $requestId = null,
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct($message, $status, $errorCode, $requestId);
    }
}
