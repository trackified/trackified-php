<?php

declare(strict_types=1);

namespace Trackified;

/** Result of WebhookSignature::verifyDetailed(); $reason is null when $valid. */
final class SignatureVerificationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?SignatureFailureReason $reason = null,
    ) {
    }
}
