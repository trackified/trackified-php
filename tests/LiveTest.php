<?php

declare(strict_types=1);

namespace Trackified\Tests;

use PHPUnit\Framework\TestCase;
use Trackified\TrackifiedClient;

/** Hits the real API; runs only when TRACKIFIED_API_KEY is set. Creates one tracking and deletes it. */
final class LiveTest extends TestCase
{
    public function testRoundTrip(): void
    {
        if (false === getenv('TRACKIFIED_API_KEY') || '' === getenv('TRACKIFIED_API_KEY')) {
            self::markTestSkipped('TRACKIFIED_API_KEY not set');
        }
        $client = new TrackifiedClient();

        self::assertNotSame('', $client->usage->get()->plan);
        self::assertSame(['status' => 'ok'], $client->health());
        self::assertSame('usps', $client->carriers->resolve('USPS Priority Mail')?->carrier);
        self::assertSame('usps', $client->carriers->detect('9434650110837017316201')[0]->carrier);

        $t = $client->trackings->create(['tracking_number' => '9434650110837017316201', 'carrier' => 'usps']);
        try {
            self::assertSame($t->id, $client->trackings->get($t->id)->id);
            self::assertGreaterThanOrEqual(1, $client->trackings->list(['limit' => 1])->total);
        } finally {
            $client->trackings->delete($t->id);
        }
    }
}
