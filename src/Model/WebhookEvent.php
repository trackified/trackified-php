<?php

declare(strict_types=1);

namespace Trackified\Model;

/**
 * A verified webhook event. $data is {tracking, previous_status, current_status} for tracking.status_changed and the
 * tracking itself for every other type; tracking() returns the Tracking for either shape.
 */
final class WebhookEvent
{
    /**
     * @param string       $type see WebhookEventType; kept as a string so new event types never break parsing
     * @param array<mixed> $data raw payload; prefer tracking(), previousStatus() and currentStatus()
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

    public function isStatusChanged(): bool
    {
        return WebhookEventType::StatusChanged->value === $this->type;
    }

    /** The tracking, whatever the event type. */
    public function tracking(): Tracking
    {
        return Tracking::fromArray($this->isStatusChanged() ? Data::arr($this->data, 'tracking') : $this->data);
    }

    /** Only for tracking.status_changed; null otherwise. */
    public function previousStatus(): ?TrackingStatus
    {
        return $this->status('previous_status');
    }

    /** Only for tracking.status_changed; null otherwise. */
    public function currentStatus(): ?TrackingStatus
    {
        return $this->status('current_status');
    }

    private function status(string $key): ?TrackingStatus
    {
        $v = Data::nstr($this->data, $key);

        return $this->isStatusChanged() && null !== $v ? TrackingStatus::tryFrom($v) ?? TrackingStatus::Unknown : null;
    }
}
