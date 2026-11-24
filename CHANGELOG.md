# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-11-24

All changes are additive; code written for 0.1 keeps working.

### Added
- T-1/T-3: `baseUrl` accepts `https://api.trackified.net` or `https://api.trackified.net/v1`, with or without a
  trailing slash; one trailing `/v1` is stripped.
- T-4: `$client->health()` (`GET /v1/health`).
- T-5: `carriers->resolve($name)` returns a `CarrierResolution` (`carrier`, `name`, `confidence`, `matched`) or null.
  `Carrier::$aliases`; `Carrier::$displayName` reads `display_name` or `displayName`.
- T-6: `WebhookEvent::tracking()` (and `WebhookSignature::trackingOf()`, `webhooks->trackingOf()`) returns the
  Tracking for any event type; `previousStatus()` / `currentStatus()` for `tracking.status_changed`.
- T-9: `WebhookSignature::verifyDetailed()` returns a readonly `SignatureVerificationResult` (`valid`, `reason`);
  `reason` is the `SignatureFailureReason` enum (`missing`, `malformed`, `timestamp`, `mismatch`), also on
  `SignatureVerificationError::$reason`.
- T-10: signatures with several `v1=` values verify if any matches; verification accepts a list of secrets;
  `webhooks->rotateSecret($id, $expiresInHours)`.
- T-11: `webhooks->update($id, url:, eventTypes:, enabled:)`.
- T-12: `QuotaExceededError` (402) and `ConflictError` (409, not retried); `ConnectionError::$errorCode` is
  `"timeout"` or `"connection"`.
- T-13: `onRequest` / `onResponse` client hooks, called once per attempt; exceptions they throw are ignored.

### Changed
- T-2: `usage->get()` keeps calling `/v1/account/usage` (`/v1/usage` is an API alias); documented.
- T-7: `TrackingEvent::$date` is deprecated and removed after 2027-06-01; documented what a zoneless `occurredAt` means.
- T-8: documented that `Tracking::$events` is newest first (guaranteed by the API).

## [0.1.0] - 2026-11-24

### Added
- `TrackifiedClient` covering every operation in the Trackified API: trackings (create, bulk create, list,
  auto-paging `all()`, get, update, delete, retrack), carriers (list, get, detect, catalog, status), webhooks
  (create, list, delete, test), webhook deliveries, API keys (create, list, revoke) and usage.
- PSR-18 / PSR-17 HTTP via php-http/discovery, with a built-in curl fallback.
- Automatic retries with exponential backoff and jitter for network errors, 408, 429 (honours `Retry-After`) and 5xx;
  automatic `Idempotency-Key` on creates, reused across retries.
- Typed errors, readonly models and enums for tracking statuses, event statuses and webhook event types.
- Webhook signature verification (`verify`, `constructEvent`).
