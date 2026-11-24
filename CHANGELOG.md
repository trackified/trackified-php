# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

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
