<?php

declare(strict_types=1);

namespace Trackified\Model;

final class Carrier
{
    /**
     * @param array<mixed> $raw     also holds mode, regions, tier, trackingNumberPattern, verification
     * @param list<string> $aliases other names and provider slugs that resolve to this carrier
     */
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly array $raw,
        public readonly array $aliases = [],
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        // The live API sends displayName; the spec says display_name. Accept both.
        return new self(Data::str($d, 'id'), Data::nstr($d, 'display_name') ?? Data::str($d, 'displayName'), $d, Data::strings($d, 'aliases'));
    }
}
