<?php

declare(strict_types=1);

namespace Trackified\Exception;

use Trackified\Http\Response;

/** Base class for every error the SDK throws. */
class TrackifiedError extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @internal */
    public static function fromResponse(Response $response): self
    {
        $decoded = json_decode($response->body, true);
        $error = \is_array($decoded) && \is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $str = static fn (string $k): ?string => \is_string($error[$k] ?? null) ? $error[$k] : null;

        $status = $response->status;
        $message = $str('message') ?? 'HTTP '.$status;
        $code = $str('code');
        $requestId = $str('request_id') ?? $response->header('x-request-id');

        if (429 === $status) {
            $retryAfter = $response->header('retry-after');

            return new RateLimitError($message, $status, $code, $requestId, is_numeric($retryAfter) ? (float) $retryAfter : null);
        }

        $class = match (true) {
            401 === $status => AuthenticationError::class,
            402 === $status => QuotaExceededError::class,
            403 === $status => PermissionError::class,
            404 === $status => NotFoundError::class,
            409 === $status => ConflictError::class,
            400 === $status, 422 === $status => ValidationError::class,
            $status >= 500 => APIError::class,
            default => self::class,
        };

        return new $class($message, $status, $code, $requestId);
    }
}
