<?php

declare(strict_types=1);

namespace Trackified\Model;

final class Webhook
{
    /**
     * @param list<WebhookEventType> $eventTypes
     * @param ?string                $secret                  signing secret; only present in the create and rotateSecret responses
     * @param array<mixed>           $raw
     * @param ?string                $previousSecretExpiresAt rotateSecret only: until then deliveries are also signed with the old secret
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
        public readonly ?string $previousSecretExpiresAt = null,
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
            Data::nstr($d, 'previous_secret_expires_at'),
        );
    }
}
