<?php

declare(strict_types=1);

namespace Trackified\Model;

final class Carrier
{
    /** @param array<mixed> $raw also holds mode, regions, tier, trackingNumberPattern, verification */
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        // The live API sends displayName; the spec says display_name. Accept both.
        return new self(Data::str($d, 'id'), Data::nstr($d, 'display_name') ?? Data::str($d, 'displayName'), $d);
    }
}
