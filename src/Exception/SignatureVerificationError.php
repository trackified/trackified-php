<?php

declare(strict_types=1);

namespace Trackified\Exception;

use Trackified\SignatureFailureReason;

/** invalid webhook signature; $reason says why */
class SignatureVerificationError extends TrackifiedError
{
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $errorCode = null,
        ?string $requestId = null,
        ?\Throwable $previous = null,
        public readonly ?SignatureFailureReason $reason = null,
    ) {
        parent::__construct($message, $status, $errorCode, $requestId, $previous);
    }
}
