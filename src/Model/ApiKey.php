<?php

declare(strict_types=1);

namespace Trackified\Model;

final class ApiKey
{
    /**
     * @param string       $mode   "test" or "live"
     * @param list<string> $scopes
     * @param ?string      $token  the full key; only present in the create response
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $prefix,
        public readonly string $mode,
        public readonly array $scopes,
        public readonly ?string $token,
        public readonly string $createdAt,
        public readonly ?string $lastUsedAt,
        public readonly ?string $revokedAt,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Data::str($d, 'id'),
            Data::str($d, 'name'),
            Data::str($d, 'prefix'),
            Data::str($d, 'mode'),
            Data::strings($d, 'scopes'),
            Data::nstr($d, 'token'),
            Data::str($d, 'created_at'),
            Data::nstr($d, 'last_used_at'),
            Data::nstr($d, 'revoked_at'),
            $d,
        );
    }
}
