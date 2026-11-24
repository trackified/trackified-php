<?php

declare(strict_types=1);

namespace Trackified\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Trackified\Exception\APIError;
use Trackified\Exception\AuthenticationError;
use Trackified\Exception\ConflictError;
use Trackified\Exception\ConnectionError;
use Trackified\Exception\NotFoundError;
use Trackified\Exception\QuotaExceededError;
use Trackified\Exception\RateLimitError;
use Trackified\Exception\TrackifiedError;
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

    private function client(int $maxRetries = 2, string $baseUrl = 'https://api.example.test/', ?callable $onRequest = null, ?callable $onResponse = null): TrackifiedClient
    {
        $f = new Psr17Factory();

        return new TrackifiedClient('tk_test_123', $baseUrl, maxRetries: $maxRetries, httpClient: $this->http, requestFactory: $f, streamFactory: $f, onRequest: $onRequest, onResponse: $onResponse);
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
        $cases = [401 => AuthenticationError::class, 402 => QuotaExceededError::class, 404 => NotFoundError::class, 409 => ConflictError::class, 400 => ValidationError::class, 500 => APIError::class];
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

    public function testBaseUrlAcceptsV1AndTrailingSlash(): void
    {
        foreach (['https://api.example.test', 'https://api.example.test/', 'https://api.example.test/v1', 'https://api.example.test/v1/'] as $url) {
            $this->http->push(200, ['plan' => 'free']);
            $this->client(0, $url)->usage->get();
        }
        foreach ($this->http->requests as $r) {
            self::assertSame('https://api.example.test/v1/account/usage', (string) $r->getUri());
        }
    }

    public function testHealth(): void
    {
        $this->http->push(200, ['status' => 'ok']);
        self::assertSame(['status' => 'ok'], $this->client()->health());
        self::assertSame('/v1/health', $this->http->requests[0]->getUri()->getPath());
    }

    public function testConflictIsNotRetried(): void
    {
        $this->http->push(409, ['error' => ['code' => 'idempotency_in_progress', 'message' => 'busy']]);
        try {
            $this->client()->trackings->create(['tracking_number' => 'X']);
            self::fail('expected ConflictError');
        } catch (ConflictError $e) {
            self::assertSame('idempotency_in_progress', $e->errorCode);
            self::assertInstanceOf(TrackifiedError::class, $e);
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testConnectionErrorCode(): void
    {
        $this->http->pushNetworkError(new Request('GET', '/'), 'cURL error 28: Operation timed out after 30001 milliseconds')
            ->pushNetworkError(new Request('GET', '/'));
        foreach (['timeout', 'connection'] as $code) {
            try {
                $this->client(0)->health();
                self::fail('expected ConnectionError');
            } catch (ConnectionError $e) {
                self::assertSame($code, $e->errorCode);
            }
        }
    }

    public function testResolveCarrier(): void
    {
        $this->http->push(200, ['carrier' => 'usps', 'name' => 'USPS', 'confidence' => 0.97, 'matched' => 'alias'])
            ->push(200, ['carrier' => null, 'confidence' => 0]);
        $c = $this->client();
        $r = $c->carriers->resolve('USPS Priority Mail');
        self::assertNotNull($r);
        self::assertSame(['usps', 'USPS', 0.97, 'alias'], [$r->carrier, $r->name, $r->confidence, $r->matched]);
        self::assertSame('name=USPS%20Priority%20Mail', $this->http->requests[0]->getUri()->getQuery());
        self::assertNull($c->carriers->resolve('nope'));
    }

    public function testCarrierAliasesAndDisplayNameSpellings(): void
    {
        $this->http->push(200, ['id' => 'ups', 'display_name' => 'UPS', 'aliases' => ['ups_ground']]);
        $carrier = $this->client()->carriers->get('ups');
        self::assertSame(['UPS', ['ups_ground']], [$carrier->displayName, $carrier->aliases]);
    }

    public function testUpdateAndRotateWebhook(): void
    {
        $this->http->push(200, ['id' => 'wh_1', 'url' => 'https://x.test', 'enabled' => false])
            ->push(200, ['id' => 'wh_1', 'secret' => 'whsec_new', 'previous_secret_expires_at' => '2026-11-25T00:00:00Z']);
        $c = $this->client();
        self::assertFalse($c->webhooks->update('wh_1', enabled: false, eventTypes: [WebhookEventType::Delivered])->enabled);
        self::assertSame('PATCH', $this->http->requests[0]->getMethod());
        self::assertSame(['event_types' => ['tracking.delivered'], 'enabled' => false], json_decode((string) $this->http->requests[0]->getBody(), true));

        $w = $c->webhooks->rotateSecret('wh_1', 0);
        self::assertSame(['whsec_new', '2026-11-25T00:00:00Z'], [$w->secret, $w->previousSecretExpiresAt]);
        self::assertSame('/v1/webhooks/wh_1/rotate-secret', $this->http->requests[1]->getUri()->getPath());
        self::assertSame(['expires_in_hours' => 0], json_decode((string) $this->http->requests[1]->getBody(), true));

        $this->expectException(\InvalidArgumentException::class);
        $c->webhooks->update('wh_1');
    }

    public function testHooksRunOncePerAttemptAndMayThrow(): void
    {
        /** @var list<array{string, array<string, mixed>}> $seen */
        $seen = [];
        $onRequest = static function (array $i) use (&$seen): void {
            $seen[] = ['req', $i];
            throw new \RuntimeException('boom');
        };
        $onResponse = static function (array $i) use (&$seen): void {
            $seen[] = ['res', $i];
            throw new \RuntimeException('boom');
        };
        $this->http->push(503, 'oops', ['x-request-id' => 'req_a'])
            ->push(201, self::tracking(), ['x-request-id' => 'req_b', 'idempotent-replayed' => 'true']);
        $t = $this->client(1, onRequest: $onRequest, onResponse: $onResponse)->trackings->create(['tracking_number' => 'X'], 'k1');

        self::assertSame('trk_1', $t->id);
        self::assertSame(['req', 'res', 'req', 'res'], array_column($seen, 0));
        self::assertSame(['method' => 'POST', 'url' => 'https://api.example.test/v1/trackings', 'attempt' => 1, 'idempotencyKey' => 'k1'], $seen[0][1]);
        self::assertSame(503, $seen[1][1]['status']);
        self::assertSame('req_a', $seen[1][1]['requestId']);
        self::assertInstanceOf(APIError::class, $seen[1][1]['error']);
        self::assertSame(2, $seen[3][1]['attempt']);
        self::assertSame(201, $seen[3][1]['status']);
        self::assertTrue($seen[3][1]['idempotentReplayed']);
        self::assertNull($seen[3][1]['error']);
        self::assertIsInt($seen[3][1]['durationMs']);
    }

    public function testOnResponseGetsConnectionError(): void
    {
        $errors = [];
        $this->http->pushNetworkError(new Request('GET', '/'));
        try {
            $this->client(0, onResponse: static function (array $i) use (&$errors): void {
                $errors[] = $i['error'];
            })->health();
        } catch (ConnectionError) {
        }
        self::assertCount(1, $errors);
        self::assertInstanceOf(ConnectionError::class, $errors[0]);
    }
}
