<?php

declare(strict_types=1);

namespace Trackified\Model;

final class CarrierCandidate
{
    public function __construct(
        public readonly string $carrier,
        public readonly float $confidence,
        public readonly string $reason,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Data::str($d, 'carrier'), Data::float($d, 'confidence'), Data::str($d, 'reason'));
    }
}
