<?php

declare(strict_types=1);

namespace Trackified\Model;

final class WebhookEvent
{
    /**
     * @param string       $type see WebhookEventType; kept as a string so new event types never break parsing
     * @param array<mixed> $data
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Data::str($d, 'id'), Data::str($d, 'type'), Data::arr($d, 'data'), $d);
    }

    public function eventType(): ?WebhookEventType
    {
        return WebhookEventType::tryFrom($this->type);
    }
}
