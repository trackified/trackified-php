<?php

declare(strict_types=1);

namespace Trackified\Model;

final class Usage
{
    /**
     * @param string             $plan          free, starter, pro or scale
     * @param array<string, int> $carrierVolume trackings per carrier this period
     * @param array<mixed>       $raw
     */
    public function __construct(
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $plan,
        public readonly int $trackingsUsed,
        public readonly int $trackingsLimit,
        public readonly int $trackingsRemaining,
        public readonly int $rateLimitPerMinute,
        public readonly int $webhookDeliveries,
        public readonly array $carrierVolume,
        public readonly array $raw,
    ) {
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        $volume = [];
        foreach (Data::rows($d, 'carrier_volume') as $row) {
            $volume[Data::str($row, 'carrier')] = Data::int($row, 'count');
        }

        return new self(
            Data::str($d, 'period_start'),
            Data::str($d, 'period_end'),
            Data::str($d, 'plan'),
            Data::int($d, 'trackings_used'),
            Data::int($d, 'trackings_limit'),
            Data::int($d, 'trackings_remaining'),
            Data::int($d, 'rate_limit_per_minute'),
            Data::int($d, 'webhook_deliveries'),
            $volume,
            $d,
        );
    }
}
