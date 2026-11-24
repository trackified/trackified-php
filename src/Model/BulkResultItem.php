<?php

declare(strict_types=1);

namespace Trackified\Model;

final class BulkResultItem
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?Tracking $tracking,
        public readonly ?string $error,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        $tracking = Data::arr($d, 'tracking');

        return new self(Data::bool($d, 'ok'), [] === $tracking ? null : Tracking::fromArray($tracking), Data::nstr($d, 'error'));
    }
}
