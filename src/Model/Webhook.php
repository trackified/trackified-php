<?php

declare(strict_types=1);

namespace Trackified\Model;

final class Webhook
{
    /**
     * @param list<WebhookEventType> $eventTypes
     * @param ?string                $secret     signing secret; only present in the create response
     * @param array<mixed>           $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly array $eventTypes,
        public readonly bool $enabled,
        public readonly ?string $secretPreview,
        public readonly ?string $secret,
        public readonly string $createdAt,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Data::str($d, 'id'),
            Data::str($d, 'url'),
            Data::eventTypes($d, 'event_types'),
            Data::bool($d, 'enabled'),
            Data::nstr($d, 'secret_preview'),
            Data::nstr($d, 'secret'),
            Data::str($d, 'created_at'),
            $d,
        );
    }
}
