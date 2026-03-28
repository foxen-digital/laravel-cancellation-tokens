---
title: "Product Brief: laravel-cancellation-tokens"
status: "complete"
created: "2026-03-28"
updated: "2026-03-28"
inputs:
  - docs/laravel-cancellation-token-package.md
  - user discovery conversation
---

# Product Brief: laravel-cancellation-tokens

## Executive Summary

Every Laravel application that lets users book, order, or subscribe eventually needs a way to let them cancel — without logging in. The typical pattern is a unique link in a confirmation email, backed by a secret token that proves intent. It sounds simple. In practice, developers re-implement the same system — token generation, storage, verification, expiry — from scratch every time, with inconsistent security and no shared conventions.

`foxen-digital/laravel-cancellation-tokens` is a focused Laravel package that handles the full token lifecycle: cryptographically secure generation, storage with a hashed representation, dual polymorphic association (who can cancel, and what they're cancelling), and expiry by time or consumption. It does nothing else. Emailing the link, rendering the cancellation page, and executing the business logic are deliberately left to the consuming application.

There is no equivalent package in the Laravel ecosystem. The closest alternatives — Laravel's signed routes and JWT libraries — are either stateless (no revocation, no single-use enforcement) or overkill. This package fills a genuine, frequently-encountered gap with a solution that feels native to Laravel.

## The Problem

A user books a desk, registers for an event, or places an order. They receive a confirmation email with a cancellation link. That link must work for anyone who has it — no login required — but it must also be unforgeable, single-use, and time-limited.

Developers solving this problem today typically reach for one of three approaches, each with meaningful drawbacks:

- **Roll it by hand**: Custom migration, custom token logic, custom validation — repeated per project, per entity type. Security posture varies. The fourth implementation is no more correct than the first.
- **Laravel signed routes**: Cryptographically sound, but stateless. Once issued, a signed URL cannot be revoked and cannot be marked as used. A user who clicks a link twice cancels twice — unless the application adds its own state, at which point the developer is building the same system anyway.
- **JWT libraries**: Designed for authentication tokens, not cancellation workflows. Stateless by design, with complex setup and no first-class concept of "consumed."

The result is fragmented, inconsistent implementations scattered across codebases — a problem that compounds as applications grow and the number of cancellable entity types increases.

## The Solution

`laravel-cancellation-tokens` provides a single, reusable implementation of the cancellation token pattern:

1. **Generate** a cryptographically secure token. A plain-text token is returned to the caller (for inclusion in a URL); only an HMAC-SHA256 hash (keyed with the application key) is stored in the database — the same approach Laravel uses for password reset tokens.
2. **Associate** the token with two actors: the *tokenable* (who may cancel — polymorphic, supporting any model including non-User authenticatables) and the *cancellable* (what is being cancelled — polymorphic, any entity).
3. **Verify** a presented token: hash it, look it up, confirm it hasn't expired and hasn't been consumed.
4. **Consume** the token on successful verification, recording `used_at` to enforce single-use.
5. **Prune** expired and consumed tokens automatically via Laravel's built-in `model:prune` command — no custom artisan commands needed. The `TokenExpired` event fires at verification time when an expired token is presented, giving consuming apps a hook for alerting or logging.

The package ships with a `HasCancellationTokens` trait for cancellable models, a `ValidCancellationToken` validation rule that checks token existence, expiry, and consumption state (the token record carries the entity association, so cancellation URLs can be as clean as `/cancel/{token}` with no entity ID in the path), and a set of events (`TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired`) for consuming applications to hook into.

## What Makes This Different

**Scope discipline.** This package does exactly one thing. It does not send emails, define routes, render views, or execute cancellations. Consuming applications own their business logic; this package owns the token.

**Correct security model.** Tokens are hashed before storage, matching the approach Laravel uses for password reset tokens in recent versions. The plain-text token never persists. Single-use enforcement is first-class, not an afterthought.

**Genuine polymorphism on both axes.** Most ad-hoc implementations assume a `User` model and a single entity type. This package uses a `tokenable` morph pair (actor) and a `cancellable` morph pair (subject), following conventions established by Spatie's activity log. Any authenticatable — or any model at all — can be the actor.

**No ecosystem equivalent.** Laravel signed routes are stateless. JWT is for authentication. There is no existing package doing this. The gap is real.

## Who This Serves

**Primary user: Laravel developers** building applications with cancellable workflows — booking systems, e-commerce, SaaS subscriptions, event platforms, service marketplaces. They're comfortable with Eloquent and Laravel conventions. Their "aha moment" is pulling in one package instead of wiring up a token table for the fourth time and wondering whether their entropy is sufficient.

**Secondary user: teams at Foxen Digital** — every internal Laravel project with cancellation workflows becomes a first-class consumer, with consistent, auditable token handling across projects.

## Success Criteria

- Adopted in the next Foxen Digital project with a cancellation workflow, with zero hand-rolled token code
- Published on Packagist; gains traction through organic Laravel community discovery (GitHub stars, downloads)
- Test coverage ≥ 95%; passes a basic security review with no token-handling vulnerabilities
- README and documentation are complete enough for a developer unfamiliar with the package to implement a cancellation flow in under 30 minutes
- Documentation published alongside v1 release

## Scope

**v1 includes:**
- Token generation (cryptographically secure, HMAC-SHA256 hash stored, plain token returned)
- Configurable token prefix (default: `ct_`, following Stripe/Sanctum conventions)
- Dual polymorphic association: `tokenable` (actor) + `cancellable` (subject)
- Time-based expiry (`expires_at`) and single-use expiry (`used_at`)
- Token verification and consumption
- `HasCancellationTokens` trait for cancellable Eloquent models
- `ValidCancellationToken` validation rule (validates existence, expiry, and consumption state — token carries entity context, enabling clean single-segment URLs like `/cancel/{token}`)
- Events: `TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired`
- `Prunable` trait on the token model (integrates with `php artisan model:prune`)
- Model factory (`CancellationTokenFactory`) for testing in consuming applications
- `CancellationTokenFake` testing helper for asserting token interactions without database
- Published migration, config file, and service provider

**Explicitly out of scope for v1:**
- Email notifications or any communication layer
- Routes, controllers, or views
- Executing the cancellation (that's the consuming app's job)
- Claims validation (verifying entity state, user permissions — consuming app owns this)
- Analytics, reporting, or audit dashboards
- IP binding or rate limiting (consuming app's middleware responsibility)
- Laravel Telescope or Nova integrations

## Roadmap Thinking

If v1 gains adoption, the natural v2 additions are: a Facade for a cleaner top-level API and a `model:prune`-compatible cleanup report. Longer term, the package could serve as the foundation for a broader Foxen Digital "workflow primitives" suite alongside packages for confirmations, approvals, and one-time access links — all sharing the same token security model.
