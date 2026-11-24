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
foreach ($tracking->events as $event) {   // newest first
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

if ($event->type === 'tracking.delivered') {
    // $event->data holds the tracking
}
```

`WebhookSignature::verify(...)` returns a bool instead. Both reject signatures older than 300 seconds; pass a
different `$tolerance` if you need to. The same methods are on `$trackified->webhooks`. See the
[webhook signing guide](https://trackified.net/developers/webhook-signing).

## Configuration

```php
$trackified = new TrackifiedClient(
    apiKey: 'tk_live_...',                    // default: env TRACKIFIED_API_KEY
    baseUrl: 'https://api.trackified.net',    // default: env TRACKIFIED_BASE_URL
    timeout: 30.0,                            // seconds, for the built-in curl client
    maxRetries: 2,
    httpClient: $myPsr18Client,               // optional; otherwise discovered
);
```

If you pass your own PSR-18 client, set its timeout on that client. Requests that fail with a network error, 408, 429
or 5xx are retried with exponential backoff and jitter (429 honours `Retry-After`). Creates get an `Idempotency-Key`
automatically, reused across retries; pass your own as the second argument to `trackings->create()` / `bulkCreate()`.

## Resources

| Resource | Methods |
| --- | --- |
| `trackings` | `create`, `bulkCreate`, `list`, `all`, `get`, `update`, `delete`, `retrack` |
| `carriers` | `list`, `get`, `detect`, `catalog`, `status` |
| `webhooks` | `create`, `list`, `delete`, `test`, `verify`, `constructEvent` |
| `webhookDeliveries` | `list` |
| `apiKeys` | `create`, `list`, `revoke` |
| `usage` | `get` |

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
| `RateLimitError` | 429 (`$retryAfter` seconds) |
| `APIError` | 5xx |
| `ConnectionError` | network failure or timeout |
| `SignatureVerificationError` | bad webhook signature |

```php
try {
    $trackified->trackings->create(['tracking_number' => 'ABC123']);
} catch (\Trackified\Exception\ValidationError $e) {
    echo $e->errorCode; // e.g. "carrier_not_detected"
}
```

## License

MIT
