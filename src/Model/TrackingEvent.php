<?php

declare(strict_types=1);

namespace Trackified\Model;

final class TrackingEvent
{
    /**
     * @param ?string      $occurredAt ISO 8601, may be a date alone, null if unreadable. Without an offset it is the local
     *                                 time where the scan happened; when the carrier gives an offset it is included and
     *                                 $timeZoneKnown is true
     * @param ?string      $date       deprecated, removed after 2027-06-01: the carrier's original time text, use $occurredAt
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
