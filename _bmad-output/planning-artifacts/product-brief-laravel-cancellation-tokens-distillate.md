---
title: "Product Brief Distillate: laravel-cancellation-tokens"
type: llm-distillate
source: "product-brief-laravel-cancellation-tokens.md"
created: "2026-03-28"
purpose: "Token-efficient context for downstream PRD creation"
---

# Product Brief Distillate: laravel-cancellation-tokens

## Core Problem & Motivation

- Developer (Mrdth, Foxen Digital) has hand-rolled cancellation token systems 4 times across different Laravel projects — this package extracts the pattern into a reusable, correct implementation
- The trigger use case: desk booking app where a user books → receives confirmation email → email contains a cancellation link with a unique token → token is verified to authorise cancellation
- Pain: each re-implementation has inconsistent security posture, custom migrations, custom validation logic, no shared conventions
- The package scope is deliberately narrow: token lifecycle only. Consuming app owns emailing, routing, rendering, and executing the actual cancellation.

## Architectural Decisions (Made, Not Open)

- **Token hashing**: HMAC-SHA256 keyed with `app.key` — NOT bcrypt (too slow for lookups), NOT plain text. Plain token returned to caller for URL inclusion; only hash persisted. Matches Laravel's password reset approach in recent versions.
- **Dual polymorphic association**: Two morph pairs on the token record:
  - `tokenable_type` / `tokenable_id` — the actor (who may cancel). Polymorphic to support any model, not just `User`. Follows Laravel Sanctum's pattern.
  - `cancellable_type` / `cancellable_id` — the subject (what is being cancelled). Polymorphic. Follows Spatie activitylog's `subject_type/subject_id` naming convention.
  - Reason for polymorphic actor: original design assumed `user_id BIGINT` but this breaks for apps using UUIDs or non-User authenticatables (e.g. `Customer`, `Guest`)
- **Token prefix**: `ct_` prepended to generated token before returning to caller. Follows Stripe (`sk_live_`) and Sanctum (`1|`) conventions. Makes tokens identifiable in logs and error messages. Included in v1 (not deferred to v2).
- **Expiry model**: Two independent expiry mechanisms — `expires_at` (timestamp, time-based) and `used_at` (nullable timestamp, set on consumption). Both checked during verification. Either can invalidate a token.
- **Cleanup**: `Prunable` trait on the `CancellationToken` model, implementing `prunable()` returning a QueryBuilder for expired/consumed tokens. Integrates with Laravel's built-in `php artisan model:prune`. No custom artisan cleanup command.
- **Validation rule scope**: `ValidCancellationToken` validates existence + unexpired + unconsumed only. It does NOT validate claims (entity state, user permissions, business rules). Consuming app owns all claim validation.
- **URL design**: Because the token record stores both actor and cancellable associations, the token is a self-contained lookup key. Clean URLs like `/cancel/ct_abc123` are sufficient — no entity type/ID needed in the URL path. This is a deliberate design advantage over naive implementations.
- **No `is_valid` boolean**: Redundant given `used_at` + `expires_at`. Original research doc proposed it; rejected.

## Rejected Ideas (Do Not Re-Propose)

- **Custom `cancellation:cleanup-expired` artisan command**: Rejected — Laravel's `Prunable` + `model:prune` handles this natively without custom commands
- **`cancelViaToken()` / `cancelUsingToken()` on the model trait**: Rejected — executing the cancellation is out of scope; consuming app owns that
- **Notification/email layer in the package**: Explicitly out of scope. The research doc included `SendCancellationNotification` listener and email templates — rejected entirely
- **Routes and controllers bundled in the package**: Out of scope. Consuming app owns its own routes
- **`is_valid` boolean column**: Redundant with `used_at` + `expires_at`; adds complexity for no benefit
- **IP binding**: Out of scope for v1; consuming app's middleware responsibility
- **Entity-specific expiry config** (e.g. `'expiry' => ['booking' => '48h', 'subscription' => '7d']`): Rejected — bakes domain knowledge into the package config. Consuming app passes `expires_at` explicitly at token creation time
- **JWT-based tokens**: Stateless, designed for authentication, no first-class concept of "consumed" — wrong tool for this problem
- **Analytics / business intelligence features**: Out of scope; not a package concern
- **Laravel Telescope / Nova integrations**: Deferred, not v1
- **`CancellationRequest` as model name**: Rejected — confusing naming. Model should be `CancellationToken`
- **Claims validation in the package** (verifying entity state, user permissions): Out of scope. The package only knows about tokens, not business rules

## Technical Context & Constraints

- Public Packagist package: `foxen-digital/laravel-cancellation-tokens`
- Target: Laravel 10+ (likely), PHP 8.1+ (likely) — version support not explicitly confirmed, should be decided during PRD/architecture
- Follows Spatie package conventions for polymorphic column naming (semantic names, not generic `morphable_*`)
- MorphMap: optional for consuming apps; package should not require or enforce it, but should document how to configure it
- Token generation: cryptographically secure random bytes (e.g. `random_bytes` / `Str::random` with sufficient entropy), prefixed with `ct_`, then HMAC-SHA256 hashed for storage
- The `TokenExpired` event fires at verification time when an expired token is presented (not during pruning)

## v1 Scope (Confirmed)

- Token generation + HMAC-SHA256 hash storage
- `ct_` token prefix
- Dual morph: `tokenable` (actor) + `cancellable` (subject)
- Time-based expiry (`expires_at`) + single-use expiry (`used_at`)
- Token verification and consumption
- `HasCancellationTokens` trait for cancellable Eloquent models
- `ValidCancellationToken` validation rule (existence + expiry + consumption state)
- Events: `TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired`
- `Prunable` on `CancellationToken` model
- `CancellationTokenFactory` model factory (best practice for packages shipping models)
- `CancellationTokenFake` testing helper (assert token interactions without DB)
- Published migration, config file, service provider

## Out of Scope (v1 and Beyond)

- Email/notification layer
- Routes, controllers, views
- Executing the cancellation
- Claims validation
- Analytics/reporting
- IP binding / rate limiting
- Telescope / Nova integrations

## Deferred to v2

- Facade for top-level API (`CancellationToken::create(...)`)
- `model:prune`-compatible cleanup report

## Competitive Landscape

- **No direct Packagist equivalent** — genuine ecosystem gap confirmed by research
- **Laravel signed routes** (`URL::temporarySignedRoute()`): stateless (no revocation, no single-use), no polymorphic entity association, no audit trail — complement not replacement
- **Laravel Sanctum tokens**: closest structural parallel (morph actor, HMAC hash), but designed for API auth not cancellation workflows
- **JWT libraries** (`tymon/jwt-auth`, `spatie/laravel-jwt`): stateless, over-engineered for this use case, no consumed state
- **Laravel password reset** (`DatabaseTokenRepository`): single-use + time-expiry pattern is a useful reference, but single-entity (email), no polymorphism

## Open Questions

- **Laravel/PHP minimum versions**: Not explicitly decided. Recommend targeting Laravel 10+ / PHP 8.2+ for modern features (readonly properties, etc.) — confirm during PRD
- **Token length**: Research doc suggested 64 chars post-prefix. Confirm entropy is sufficient for the expected token volume
- **Config surface**: What should be configurable? At minimum: table name, default expiry duration, token prefix. Confirm full config surface during PRD
- **`HasCancellationTokens` trait API**: What convenience methods does it expose? `$booking->createCancellationToken(tokenable: $user, expiresAt: ...)`, `$booking->cancellationTokens()` relationship — API design to be fleshed out in PRD/architecture
- **Soft deletes on the token model**: Research doc suggested soft deletes for audit trails; not discussed during discovery. Worth considering for compliance use cases
- **Multiple active tokens per cancellable**: Should creating a new token for an entity auto-invalidate previous tokens for the same entity+tokenable combination? Behaviour to define
