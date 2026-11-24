<?php

declare(strict_types=1);

namespace Trackified\Model;

final class Tracking
{
    /**
     * @param list<TrackingEvent> $events newest first, guaranteed by the API (sorted by occurred_at desc; undated events
     *                                    keep their carrier position after the dated ones)
     * @param array<mixed>        $raw    the full JSON object, including fields this SDK does not model yet
     */
    public function __construct(
        public readonly string $id,
        public readonly string $trackingNumber,
        public readonly ?string $carrier,
        public readonly bool $carrierDetected,
        public readonly TrackingStatus $status,
        public readonly array $events,
        public readonly ?string $estimatedDelivery,
        public readonly ?string $deliveredAt,
        public readonly bool $deliveredAtTimeZoneKnown,
        public readonly ?string $deliveryDetectedAt,
        public readonly ?string $exception,
        public readonly ?string $serviceLevel,
        public readonly ?string $customId,
        public readonly ?string $customerEmail,
        public readonly ?string $lastScrapedAt,
        public readonly ?string $nextScrapeAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Data::str($d, 'id'),
            Data::str($d, 'tracking_number'),
            Data::nstr($d, 'carrier'),
            Data::bool($d, 'carrier_detected'),
            TrackingStatus::tryFrom(Data::str($d, 'status')) ?? TrackingStatus::Unknown,
            array_map(TrackingEvent::fromArray(...), Data::rows($d, 'events')),
            Data::nstr($d, 'estimated_delivery'),
            Data::nstr($d, 'delivered_at'),
            Data::bool($d, 'delivered_at_time_zone_known'),
            Data::nstr($d, 'delivery_detected_at'),
            Data::nstr($d, 'exception'),
            Data::nstr($d, 'service_level'),
            Data::nstr($d, 'custom_id'),
            Data::nstr($d, 'customer_email'),
            Data::nstr($d, 'last_scraped_at'),
            Data::nstr($d, 'next_scrape_at'),
            Data::str($d, 'created_at'),
            Data::str($d, 'updated_at'),
            $d,
        );
    }
}
