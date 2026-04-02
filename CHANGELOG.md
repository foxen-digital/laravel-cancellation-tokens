# Changelog

All notable changes to `laravel-cancellation-tokens` will be documented in this file.

## 1.0.0 - 2026-04-02

Initial release.

### What it does

Secure, single-use, time-limited cancellation tokens for Laravel — without requiring login. Generate a token, embed it in an email link, verify it on the other end. The package handles hashing, expiry, consumption, events, cleanup, and testing. You handle the business logic.

### Features

- **Token lifecycle** — create, verify, and consume tokens via the `CancellationToken` Facade or the `HasCancellationTokens` Eloquent trait
- **Cryptographic security** — HMAC-SHA256 hashing with a dedicated `hash_key` (isolated from `APP_KEY`), timing-safe comparison via `hash_equals()`, 64 bytes of entropy
- **Single-use enforcement** — consumed tokens are marked with `used_at` and cannot be reused
- **Time-based expiry** — configurable default (7 days), per-token custom expiry supported
- **Dual polymorphic associations** — `tokenable` (who may cancel) and `cancellable` (what is being cancelled), supporting any Eloquent model
- **Automatic invalidation** — creating a new token for the same pair removes previous unused tokens
- **Validation rule** — `ValidCancellationToken` for form requests, with distinguishable failure reasons (`NotFound`, `Expired`, `Consumed`)
- **Events** — `TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired` dispatched at each lifecycle point
- **Automatic cleanup** — `Prunable` model integrates with `model:prune` for removing expired and consumed tokens
- **Testing support** — `CancellationTokenFake` for database-free unit tests with assertion helpers, `CancellationTokenFactory` for feature test scaffolding

##### Requirements

- PHP 8.3+
- Laravel 12 or 13
