<?php

declare(strict_types=1);

namespace Trackified\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Trackified\Exception\APIError;
use Trackified\Exception\AuthenticationError;
use Trackified\Exception\ConnectionError;
use Trackified\Exception\NotFoundError;
use Trackified\Exception\RateLimitError;
use Trackified\Exception\ValidationError;
use Trackified\Model\EventStatus;
use Trackified\Model\TrackingStatus;
use Trackified\Model\WebhookEventType;
use Trackified\TrackifiedClient;

final class ClientTest extends TestCase
{
    private MockHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
    }

    private function client(int $maxRetries = 2): TrackifiedClient
    {
        $f = new Psr17Factory();

        return new TrackifiedClient('tk_test_123', 'https://api.example.test/', maxRetries: $maxRetries, httpClient: $this->http, requestFactory: $f, streamFactory: $f);
    }

    /** @return array<string, mixed> */
    private static function tracking(string $id = 'trk_1'): array
    {
        return [
            'id' => $id, 'tracking_number' => '9434650110837017316201', 'carrier' => 'usps', 'carrier_detected' => true,
            'status' => 'in_transit', 'events' => [['occurred_at' => '2026-06-25T14:31:28Z', 'time_zone_known' => true, 'status' => 'in_transit', 'description' => 'Departed', 'location' => 'NY', 'date' => null]],
            'created_at' => '2026-06-25T14:00:00Z', 'updated_at' => '2026-06-25T15:00:00Z', 'future_field' => 'x',
        ];
    }

    public function testCreateTrackingSendsAuthUserAgentAndIdempotencyKey(): void
    {
        $this->http->push(201, self::tracking());
        $t = $this->client()->trackings->create(['tracking_number' => '9434650110837017316201', 'carrier' => 'usps']);

        self::assertSame('trk_1', $t->id);
        self::assertSame(TrackingStatus::InTransit, $t->status);
        self::assertSame(EventStatus::InTransit, $t->events[0]->status);
        self::assertSame('x', $t->raw['future_field']);

        $req = $this->http->requests[0];
        self::assertSame('POST', $req->getMethod());
        self::assertSame('https://api.example.test/v1/trackings', (string) $req->getUri());
        self::assertSame('Bearer tk_test_123', $req->getHeaderLine('Authorization'));
        self::assertSame('trackified-php/'.TrackifiedClient::VERSION, $req->getHeaderLine('User-Agent'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame(['tracking_number' => '9434650110837017316201', 'carrier' => 'usps'], json_decode((string) $req->getBody(), true));
    }

    public function testRetriesReuseIdempotencyKeyAndHonourRetryAfter(): void
    {
        $this->http->push(429, ['error' => ['code' => 'rate_limited', 'message' => 'slow down']], ['Retry-After' => '0'])
            ->push(503, 'oops')
            ->push(201, self::tracking());
        $this->client()->trackings->create(['tracking_number' => 'X'], 'order-42');

        self::assertCount(3, $this->http->requests);
        foreach ($this->http->requests as $r) {
            self::assertSame('order-42', $r->getHeaderLine('Idempotency-Key'));
        }
    }

    public function testRetriesNetworkErrors(): void
    {
        $this->http->pushNetworkError(new Request('GET', '/'))->push(200, self::tracking());
        self::assertSame('trk_1', $this->client()->trackings->get('trk_1')->id);
        self::assertCount(2, $this->http->requests);
    }

    public function testNetworkErrorAfterRetriesBecomesConnectionError(): void
    {
        $this->http->pushNetworkError(new Request('GET', '/'));
        $this->expectException(ConnectionError::class);
        $this->client(0)->trackings->get('trk_1');
    }

    public function testDoesNotRetryOtherClientErrors(): void
    {
        $this->http->push(422, ['error' => ['code' => 'carrier_not_detected', 'message' => 'pass a carrier', 'request_id' => 'req_1']]);
        try {
            $this->client()->trackings->create(['tracking_number' => 'X']);
            self::fail('expected ValidationError');
        } catch (ValidationError $e) {
            self::assertSame(422, $e->status);
            self::assertSame('carrier_not_detected', $e->errorCode);
            self::assertSame('pass a carrier', $e->getMessage());
            self::assertSame('req_1', $e->requestId);
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testErrorMapping(): void
    {
        $cases = [401 => AuthenticationError::class, 404 => NotFoundError::class, 400 => ValidationError::class, 500 => APIError::class];
        foreach ($cases as $status => $class) {
            $this->http->push($status, ['error' => ['code' => 'c', 'message' => 'm', 'request_id' => 'r']]);
            try {
                $this->client(0)->usage->get();
                self::fail("expected $class");
            } catch (\Throwable $e) {
                self::assertInstanceOf($class, $e);
            }
        }

        $this->http->push(429, ['error' => ['code' => 'rate_limited', 'message' => 'm']], ['Retry-After' => '7']);
        try {
            $this->client(0)->usage->get();
            self::fail('expected RateLimitError');
        } catch (RateLimitError $e) {
            self::assertSame(7.0, $e->retryAfter);
        }
    }

    public function testAllAutoPaginates(): void
    {
        $this->http->push(200, ['data' => [self::tracking('a'), self::tracking('b')], 'pagination' => ['limit' => 2, 'offset' => 0, 'total' => 3]])
            ->push(200, ['data' => [self::tracking('c')], 'pagination' => ['limit' => 2, 'offset' => 2, 'total' => 3]]);

        $ids = [];
        foreach ($this->client()->trackings->all(['limit' => 2, 'status' => TrackingStatus::InTransit]) as $t) {
            $ids[] = $t->id;
        }
        self::assertSame(['a', 'b', 'c'], $ids);
        self::assertSame('limit=2&status=in_transit&offset=2', $this->http->requests[1]->getUri()->getQuery());
    }

    public function testCarriersDetectUsesNumberParamAndDisplayName(): void
    {
        $this->http->push(200, ['tracking_number' => '94', 'candidates' => [['carrier' => 'usps', 'confidence' => 0.95, 'reason' => 'IMpb']]])
            ->push(200, ['id' => 'usps', 'displayName' => 'USPS']);
        $c = $this->client();
        self::assertSame('usps', $c->carriers->detect('94')[0]->carrier);
        self::assertSame('number=94', $this->http->requests[0]->getUri()->getQuery());
        self::assertSame('USPS', $c->carriers->get('usps')->displayName);
    }

    public function testWebhooksAndDeliveries(): void
    {
        $this->http->push(201, ['id' => 'wh_1', 'url' => 'https://x.test', 'event_types' => ['tracking.delivered', 'tracking.new_thing'], 'enabled' => true, 'secret' => 'whsec_1'])
            ->push(200, '')
            ->push(200, ['data' => [['id' => 'whd_1', 'webhook_id' => 'wh_1', 'event_type' => 'tracking.updated', 'status' => 200, 'attempts' => 1, 'error' => null]], 'pagination' => ['limit' => 50, 'offset' => 0, 'total' => 1]]);
        $c = $this->client();
        $wh = $c->webhooks->create('https://x.test', [WebhookEventType::Delivered]);
        self::assertSame([WebhookEventType::Delivered], $wh->eventTypes);
        self::assertSame('whsec_1', $wh->secret);
        self::assertSame(['url' => 'https://x.test', 'event_types' => ['tracking.delivered']], json_decode((string) $this->http->requests[0]->getBody(), true));
        self::assertSame('', $this->http->requests[0]->getHeaderLine('Idempotency-Key'));

        $c->webhooks->delete('wh_1');
        self::assertSame('DELETE', $this->http->requests[1]->getMethod());

        $page = $c->webhookDeliveries->list();
        self::assertSame(WebhookEventType::Updated, $page->data[0]->eventType);
        self::assertSame(200, $page->data[0]->status);
        self::assertFalse($page->hasMore());
    }

    public function testUsageAndApiKeys(): void
    {
        $this->http->push(200, ['plan' => 'scale', 'trackings_used' => 16, 'carrier_volume' => [['carrier' => 'usps', 'count' => 5]]])
            ->push(201, ['id' => 'key_1', 'mode' => 'test', 'scopes' => ['trackings:read'], 'token' => 'tk_test_new']);
        $c = $this->client();
        $u = $c->usage->get();
        self::assertSame(['usps' => 5], $u->carrierVolume);
        self::assertSame(16, $u->trackingsUsed);
        $k = $c->apiKeys->create();
        self::assertSame('{}', (string) $this->http->requests[1]->getBody());
        self::assertSame(['trackings:read'], $k->scopes);
    }

    public function testMissingApiKey(): void
    {
        putenv('TRACKIFIED_API_KEY');
        $this->expectException(AuthenticationError::class);
        new TrackifiedClient(httpClient: $this->http);
    }
}
