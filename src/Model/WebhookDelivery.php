<?php

declare(strict_types=1);

namespace Trackified\Model;

final class WebhookDelivery
{
    /**
     * @param ?int         $status HTTP status your endpoint answered with, null if it could not be reached
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $webhookId,
        public readonly string $url,
        public readonly ?WebhookEventType $eventType,
        public readonly ?int $status,
        public readonly int $attempts,
        public readonly ?string $error,
        public readonly ?string $deliveredAt,
        public readonly string $createdAt,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Data::str($d, 'id'),
            Data::str($d, 'webhook_id'),
            Data::str($d, 'url'),
            WebhookEventType::tryFrom(Data::str($d, 'event_type')),
            Data::nint($d, 'status'),
            Data::int($d, 'attempts'),
            Data::nstr($d, 'error'),
            Data::nstr($d, 'delivered_at'),
            Data::str($d, 'created_at'),
            $d,
        );
    }
}
