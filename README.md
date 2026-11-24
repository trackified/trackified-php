# Trackified PHP SDK

[![CI](https://github.com/trackified/trackified-php/actions/workflows/ci.yml/badge.svg)](https://github.com/trackified/trackified-php/actions/workflows/ci.yml)

The official PHP client for the [Trackified](https://trackified.net) parcel-tracking API. Works in Laravel,
WooCommerce, Symfony or plain PHP.

- PHP 8.1+
- Uses any PSR-18 HTTP client you already have (Guzzle, Symfony HttpClient, ...) via
  [php-http/discovery](https://github.com/php-http/discovery), and falls back to a built-in curl client if none is installed
- Automatic retries, idempotent creates, typed models and errors, webhook signature verification

Full API docs: https://forumulate.com/p/trackified/docs

## Install

```sh
composer require trackified/trackified-php
```

## Quickstart

```php
use Trackified\TrackifiedClient;

$trackified = new TrackifiedClient('tk_live_...'); // or set TRACKIFIED_API_KEY and call new TrackifiedClient()

// Create a tracking (leave out 'carrier' to detect it from the number)
$tracking = $trackified->trackings->create([
    'tracking_number' => '9434650110837017316201',
    'carrier' => 'usps',
    'custom_id' => 'order-1042',
]);

// Get it
$tracking = $trackified->trackings->get($tracking->id);
echo $tracking->status->value;            // e.g. "in_transit"
foreach ($tracking->events as $event) {   // newest first, guaranteed by the API
    echo $event->occurredAt, ' ', $event->description, ' ', $event->location, PHP_EOL;
}

// List every tracking, page by page behind the scenes
foreach ($trackified->trackings->all(['status' => \Trackified\Model\TrackingStatus::Delivered]) as $t) {
    echo $t->trackingNumber, PHP_EOL;
}

// Or one page at a time
$page = $trackified->trackings->list(['limit' => 50, 'offset' => 0]);
echo $page->total;
```

### Events and times

`$tracking->events` is **newest first**: sorted by `occurred_at` descending, guaranteed by the API (undated events keep
their carrier position after the dated ones). An `occurredAt` without an offset is the local time where the scan
happened; when the carrier gives an offset it is included and `timeZoneKnown` is true. `$event->date` is deprecated:
it is sent until 2027-06-01 and removed after that.

### Resolve a carrier name

```php
$r = $trackified->carriers->resolve('USPS Priority Mail'); // also 'dhl_express', 'royal-mail', 'Correos de España'
$r?->carrier;    // "usps"; resolve() returns null when no carrier we serve matches
$r?->confidence; // 0.97
$r?->matched;    // "alias" (id, alias, name, provider_slug or prefix)
```

`trackings->create(['carrier' => ...])` accepts the same free text and stores our id. Carriers also have `$aliases`.

### Verify a webhook

Trackified signs each delivery with `x-tracking-signature: t=<unix seconds>,v1=<hex HMAC-SHA256>`. Pass the **raw**
request body, not re-encoded JSON:

```php
use Trackified\Exception\SignatureVerificationError;
use Trackified\WebhookSignature;

$payload = file_get_contents('php://input');     // Laravel: $request->getContent()
$header = $_SERVER['HTTP_X_TRACKING_SIGNATURE'] ?? '';

try {
    $event = WebhookSignature::constructEvent($payload, $header, getenv('TRACKIFIED_WEBHOOK_SECRET'));
} catch (SignatureVerificationError $e) {
    http_response_code(400);
    exit;
}

$tracking = $event->tracking(); // the Tracking, for every event type
if ($event->isStatusChanged()) {
    echo $event->previousStatus()?->value, ' -> ', $event->currentStatus()?->value;
}
```

`$event->data` is `{tracking, previous_status, current_status}` for `tracking.status_changed` and the tracking itself
for every other type; `$event->tracking()` (or `WebhookSignature::trackingOf($event)`) handles both.

`WebhookSignature::verify(...)` returns a bool instead. `verifyDetailed()` says why it failed:

```php
$result = WebhookSignature::verifyDetailed($payload, $header, $secret);
$result->valid;          // false
$result->reason?->value; // "missing" | "malformed" | "timestamp" | "mismatch" (Trackified\SignatureFailureReason)
```

`SignatureVerificationError::$reason` carries the same enum. Both reject signatures older than 300 seconds; pass a
different `$tolerance` if you need to. The same methods are on `$trackified->webhooks`. See the
[webhook signing guide](https://trackified.net/developers/webhook-signing).

### Rotate a webhook secret

```php
$webhook = $trackified->webhooks->rotateSecret('wh_123', expiresInHours: 24); // 0-168, default 24; 0 drops the old one now
$webhook->secret;                  // the new secret, shown once
$webhook->previousSecretExpiresAt; // until then, deliveries carry two signatures
```

During the window the header is `t=...,v1=<new>,v1=<old>` and a delivery is valid if any `v1` matches, so both the old
and the new secret verify. To switch without downtime, verify with both while you deploy the new one:

```php
WebhookSignature::constructEvent($payload, $header, [$newSecret, $oldSecret]);
```

### Update a webhook

```php
$trackified->webhooks->update('wh_123', enabled: false);
$trackified->webhooks->update('wh_123', url: 'https://example.com/hooks', eventTypes: [WebhookEventType::Delivered]);
```

## Configuration

```php
$trackified = new TrackifiedClient(
    apiKey: 'tk_live_...',                    // default: env TRACKIFIED_API_KEY
    baseUrl: 'https://api.trackified.net',    // default: env TRACKIFIED_BASE_URL; ".../v1" works too
    timeout: 30.0,                            // seconds, for the built-in curl client
    maxRetries: 2,
    httpClient: $myPsr18Client,               // optional; otherwise discovered
    onRequest: fn (array $r) => $logger->debug("{$r['method']} {$r['url']} attempt {$r['attempt']}"),
    onResponse: fn (array $r) => $metrics->timing('trackified.request', $r['durationMs'], ['status' => $r['status']]),
);
```

`onRequest` gets `['method', 'url', 'attempt', 'idempotencyKey']`; `onResponse` gets `['method', 'url', 'attempt',
'status', 'requestId', 'idempotentReplayed', 'durationMs', 'error']`. Each is called once per attempt (`attempt` starts
at 1). `error` is the typed error when the status is 400 or more, or a `ConnectionError` (with `status` null) when the
connection failed. Exceptions thrown in a hook are ignored and never break the request.

If you pass your own PSR-18 client, set its timeout on that client. Requests that fail with a network error, 408, 429
or 5xx are retried with exponential backoff and jitter (429 honours `Retry-After`). Creates get an `Idempotency-Key`
automatically, reused across retries; pass your own as the second argument to `trackings->create()` / `bulkCreate()`.

## Resources

| Resource | Methods |
| --- | --- |
| `trackings` | `create`, `bulkCreate`, `list`, `all`, `get`, `update`, `delete`, `retrack` |
| `carriers` | `list`, `get`, `resolve`, `detect`, `catalog`, `status` |
| `webhooks` | `create`, `list`, `update`, `rotateSecret`, `delete`, `test`, `verify`, `verifyDetailed`, `constructEvent`, `trackingOf` |
| `webhookDeliveries` | `list` |
| `apiKeys` | `create`, `list`, `revoke` |
| `usage` | `get` (calls `GET /v1/account/usage`) |

`$trackified->health()` calls `GET /v1/health` and returns `['status' => 'ok']`.

Every model keeps the full JSON object in `$model->raw`, so fields added to the API later are reachable before the SDK
models them.

## Errors

All errors extend `Trackified\Exception\TrackifiedError` and expose `$status`, `$errorCode`, `getMessage()` and
`$requestId`:

| Class | When |
| --- | --- |
| `AuthenticationError` | 401, or no API key |
| `PermissionError` | 403 |
| `NotFoundError` | 404 |
| `ValidationError` | 400, 422 |
| `QuotaExceededError` | 402, `quota_exceeded` |
| `ConflictError` | 409, e.g. `idempotency_in_progress` (not retried) |
| `RateLimitError` | 429 (`$retryAfter` seconds) |
| `APIError` | 5xx |
| `ConnectionError` | network failure or timeout; `$errorCode` is `"timeout"` or `"connection"` |
| `SignatureVerificationError` | bad webhook signature |

```php
try {
    $trackified->trackings->create(['tracking_number' => 'ABC123']);
} catch (\Trackified\Exception\ValidationError $e) {
    echo $e->errorCode; // e.g. "carrier_not_detected"
}
```

After a timeout the outcome is **unknown**: the API may have done the work. Retry with the same idempotency key; the
SDK does this automatically for its own retries, and you can pass your own key to `trackings->create()` /
`bulkCreate()` to carry it across yours.

## License

MIT
