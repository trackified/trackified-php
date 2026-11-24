<?php

declare(strict_types=1);

namespace Trackified\Model;

/** Result of carriers->resolve(). */
final class CarrierResolution
{
    /**
     * @param string       $carrier our carrier id, e.g. "usps"
     * @param string       $matched "id", "alias", "name", "provider_slug" or "prefix"
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly string $carrier,
        public readonly string $name,
        public readonly float $confidence,
        public readonly string $matched,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Data::str($d, 'carrier'), Data::str($d, 'name'), Data::float($d, 'confidence'), Data::str($d, 'matched'), $d);
    }
}
