<?php

declare(strict_types=1);

namespace Trackified\Exception;

/**
 * Network failure or timeout; no HTTP status. $errorCode is self::TIMEOUT ("timeout") or self::CONNECTION ("connection").
 * After a timeout the outcome is unknown: retry with the same idempotency key.
 */
class ConnectionError extends TrackifiedError
{
    public const TIMEOUT = 'timeout';
    public const CONNECTION = 'connection';
}
