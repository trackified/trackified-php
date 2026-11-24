<?php

declare(strict_types=1);

namespace Trackified\Model;

final class TrackingEvent
{
    /**
     * @param ?string      $occurredAt ISO 8601; with an offset only when $timeZoneKnown, may be a date alone, null if unreadable
     * @param ?string      $date       deprecated: the carrier's original time text, use $occurredAt
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $occurredAt,
        public readonly bool $timeZoneKnown,
        public readonly EventStatus $status,
        public readonly string $description,
        public readonly string $location,
        public readonly ?string $date,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Data::nstr($d, 'occurred_at'),
            Data::bool($d, 'time_zone_known'),
            EventStatus::tryFrom(Data::str($d, 'status')) ?? EventStatus::Unknown,
            Data::str($d, 'description'),
            Data::str($d, 'location'),
            Data::nstr($d, 'date'),
            $d,
        );
    }
}
