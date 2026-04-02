---
stepsCompleted:
  - step-01-init
  - step-02-discovery
  - step-02b-vision
  - step-02c-executive-summary
  - step-03-success
  - step-04-journeys
  - step-05-domain
  - step-06-innovation
  - step-07-project-type
  - step-08-scoping
  - step-09-functional
  - step-10-nonfunctional
  - step-11-polish
inputDocuments:
  - _bmad-output/planning-artifacts/product-brief-laravel-cancellation-tokens.md
  - _bmad-output/planning-artifacts/product-brief-laravel-cancellation-tokens-distillate.md
  - docs/laravel-cancellation-token-package.md
workflowType: 'prd'
classification:
  projectType: developer_tool
  domain: general
  complexity: low
  projectContext: greenfield
briefCount: 2
researchCount: 1
brainstormingCount: 0
projectDocsCount: 0
---

# Product Requirements Document - laravel-cancellation-tokens

**Author:** Mrdth
**Date:** 2026-03-28

## Executive Summary

`foxen-digital/laravel-cancellation-tokens` is a focused Laravel package that owns the full cancellation token lifecycle — generation, storage, verification, expiry, and consumption — so consuming applications never hand-roll this system again. The primary user is a Laravel developer building applications with cancellable workflows (bookings, orders, subscriptions, events). Their aha moment: pulling in one package instead of wiring a token table for the fourth time and wondering whether their entropy is sufficient. The secondary user is teams at Foxen Digital, where every internal project with cancellations becomes a first-class consumer with consistent, auditable token handling.

The problem this solves is real and repeated: a user books, registers, or orders; they receive a confirmation email with a cancellation link; that link must work without login, be unforgeable, single-use, and time-limited. Existing approaches are stateless (signed routes — no revocation, no single-use), over-engineered for auth workflows (JWT), or absent from the ecosystem entirely. Developers reach for ad-hoc implementations each time, with inconsistent security and no shared conventions.

### What Makes This Special

**Scope discipline.** The package does exactly one thing. It does not send emails, define routes, render views, or execute cancellations. Consuming applications own their business logic; this package owns the token.

**Correct security model.** Tokens are HMAC-SHA256 hashed (keyed with a dedicated `cancellation-tokens.hash_key`, isolated from `APP_KEY`) before storage. The plain-text token never persists. Single-use enforcement is first-class via `used_at`, not bolted on.

**Self-contained lookup key.** The token record carries both the `tokenable` (who may cancel — polymorphic, any model) and the `cancellable` (what is being cancelled — polymorphic, any entity), making the token sufficient for full context resolution. This enables clean `/cancel/{token}` URLs with no entity ID in the path — a DX improvement that falls out naturally from the architecture and is unavailable in any ad-hoc implementation.

There is no direct Packagist equivalent. This fills a genuine ecosystem gap.

## Success Criteria

### User Success

- A developer unfamiliar with the package can implement a complete cancellation flow in under 30 minutes, using only the README
- Zero hand-rolled token code in any Foxen Digital project with a cancellation workflow after v1 is published
- Consuming apps can issue, verify, and consume tokens without understanding the hashing internals — the abstraction holds
- The `ValidCancellationToken` rule integrates into standard Laravel form request validation with no ceremony

### Business Success

- Published on Packagist under `foxen-digital/laravel-cancellation-tokens` at v1.0.0
- Adopted in the next Foxen Digital internal project requiring cancellation token handling, with no supplementary token code
- Organic Laravel community traction within 90 days of release: measurable via Packagist downloads and GitHub stars
- Documentation complete and published alongside v1 release

### Technical Success

- Test coverage ≥ 95% (unit + integration)
- Passes a basic security review: no plain-text token storage, no timing attack vectors in hash comparison, no token enumeration via error messages
- Tokens are cryptographically secure with sufficient entropy (minimum 64 bytes of randomness post-prefix)
- `Prunable` integration verified against Laravel's `model:prune` command with no custom artisan commands required

### Measurable Outcomes

| Outcome | Signal | Target |
|---|---|---|
| Developer time-to-implement | README walkthrough | < 30 minutes |
| Internal adoption | Foxen Digital projects | First eligible project post-v1 |
| Test coverage | PHPUnit coverage report | ≥ 95% |
| Security posture | Code review | No token-handling vulnerabilities |
| Ecosystem presence | Packagist listing | v1.0.0 published |

## Product Scope

### MVP Strategy

**Approach:** Problem-solving MVP — a complete, correct token lifecycle shipped as a production-ready Composer package. Partial implementations have no value; the full lifecycle (generate → store → verify → consume → prune) is the atomic unit of usefulness. Everything in Phase 1 is load-bearing; nothing can be deferred.

**Resource:** Solo developer (Mrdth). Timeline driven by the next Foxen Digital project requiring cancellation token handling.

### Phase 1 — Minimum Viable Product

- Token generation: cryptographically secure random bytes, `ct_` prefix, HMAC-SHA256 hash stored
- Dual polymorphic association: `tokenable` (actor) + `cancellable` (subject)
- Time-based expiry (`expires_at`) + single-use enforcement (`used_at`)
- Token verification and consumption
- `HasCancellationTokens` trait for cancellable Eloquent models
- `CancellationToken` Facade delegating to the underlying service class
- `ValidCancellationToken` validation rule with distinguishable failure reasons (expired / consumed / not-found)
- Events: `TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired`
- `Prunable` on `CancellationToken` model + `model:prune` integration
- `CancellationTokenFactory` model factory
- `CancellationTokenFake` testing helper
- Published migration, config file, service provider with auto-discovery
- README documentation sufficient for 30-minute implementation

### Phase 2 — Post-MVP

- `model:prune`-compatible cleanup report (visibility into pruned token counts)

### Phase 3 — Vision

- Broader Foxen Digital "workflow primitives" suite: confirmations, approvals, one-time access links sharing the same security model
- Laravel Telescope integration for token visibility in development

### Scope Risk Mitigation

**Technical:** Hash comparison correctness (timing attacks) — mitigated by `hash_equals()` and explicit security review. Token entropy — 64 bytes of randomness post-prefix is well-established as sufficient.

**Market:** Low — ecosystem gap is confirmed; internal adoption validates the concept before public release.

**Resource:** Narrow, well-defined scope completable in one focused sprint. The hard "not in scope" list (email layer, routes, controllers, claim validation) is the circuit breaker for scope creep.

## User Journeys

### Journey 1: The Developer Installing for the First Time (Primary — Happy Path)

**Meet Marcus.** He's a solo developer at a mid-sized agency. His client wants to add desk booking to their Laravel app, including a "cancel your booking" link in the confirmation email. Marcus has built this before — three times. He knows the drill: custom migration, hash the token, remember to check expiry, don't forget to mark it as used. He opens Packagist out of habit, not expecting to find anything useful.

He finds `foxen-digital/laravel-cancellation-tokens`. The README takes him through it in 20 minutes: `composer require`, publish the migration, add `HasCancellationTokens` to his `Booking` model, call `$booking->createCancellationToken(tokenable: $user, expiresAt: now()->addDays(7))`, drop the returned token into the email URL, and validate it in the controller with `ValidCancellationToken`. The token record carries both the user and the booking, so his route is a clean `/cancel/{token}` — no entity ID needed.

He runs `php artisan migrate`, writes the test using `CancellationTokenFake`, and ships. For the first time, he doesn't wonder whether his entropy is sufficient. **He didn't write a single line of token logic.**

*Capabilities revealed: token generation, `HasCancellationTokens` trait, `ValidCancellationToken` rule, `CancellationTokenFake`, published migration*

---

### Journey 2: The Developer Handling a Stale Link (Primary — Edge Case)

**Marcus's client emails him three weeks later.** A customer clicked their cancellation link after it had already expired. The page returned a generic 422. The client wants a proper "this link has expired" message and a way for the customer to request a new one.

Marcus digs into the package. He discovers the `TokenExpired` event fires at verification time when an expired token is presented. He adds a listener that stores a flash message and redirects to a "request new link" page. The `ValidCancellationToken` rule's failure reason is distinguishable — expired vs consumed vs not-found — so he can craft the right message for each case.

He also sets up `php artisan model:prune` on a daily schedule. Old tokens disappear automatically; no custom cleanup command needed.

**He handled three distinct failure cases without touching the token logic itself** — just events and the validation rule's failure feedback.

*Capabilities revealed: `TokenExpired` event, prunable model, `model:prune` integration, validation failure distinctions*

---

### Journey 3: The Foxen Digital Developer (Secondary — Internal Adoption)

**Priya is building Foxen Digital's next client project** — a service marketplace where users can cancel their booked appointments. She reaches for the `CancellationToken` Facade because she doesn't want to add the trait to both `Customer` and `ServiceProvider`.

`CancellationToken::create(cancellable: $appointment, tokenable: $customer, expiresAt: now()->addHours(48))` — explicit, readable, no trait needed on either model. She listens to `TokenConsumed` to trigger her own cancellation workflow. The token authorises the action; it never touches her business logic.

**She ships the feature in an afternoon with zero hand-rolled token code** — the first Foxen Digital project to do so.

*Capabilities revealed: `CancellationToken` Facade, `TokenConsumed` event, decoupled actor/subject design*

---

### Journey 4: The Developer Testing in CI (Integration — Technical User)

**Dev is setting up the test suite** for a booking app using the package. He doesn't want database migrations running in unit tests for token assertions — just fast, isolated checks that token creation and consumption are being called correctly.

He swaps in `CancellationTokenFake` in his `TestCase` setup. It intercepts token creation and consumption calls without hitting the database and exposes assertion helpers: `assertTokenCreatedFor($booking)`, `assertTokenConsumed()`. Unit tests run fast; feature tests use the real database — that's where the integration contract is verified.

**He gets a meaningful test suite without fighting the token layer** or mocking internals.

*Capabilities revealed: `CancellationTokenFake`, test isolation boundary, `CancellationTokenFactory` for feature tests*

---

### Journey Requirements Summary

| Capability | Revealed By |
|---|---|
| Token generation (service + Facade + trait) | Journeys 1, 3 |
| `HasCancellationTokens` trait | Journey 1 |
| `CancellationToken` Facade | Journey 3 |
| `ValidCancellationToken` rule with failure distinctions | Journeys 1, 2 |
| Events: `TokenCreated`, `TokenExpired`, `TokenConsumed` | Journeys 1, 2, 3 |
| `Prunable` + `model:prune` integration | Journey 2 |
| `CancellationTokenFake` testing helper | Journeys 1, 4 |
| `CancellationTokenFactory` for feature tests | Journey 4 |
| Published migration + service provider | Journey 1 |

## Innovation & Novel Patterns

### Detected Innovation Areas

**Ecosystem gap.** No direct Packagist equivalent exists for stateful cancellation tokens with dual polymorphism. This is the first dedicated implementation of this pattern as a Laravel package — not an incremental improvement on existing solutions.

**Self-contained lookup key via dual polymorphism.** Storing both `tokenable` (actor) and `cancellable` (subject) on a single token record makes the token sufficient for full context resolution at verification time. No ad-hoc implementation achieves this because they typically assume a single entity type and a `user_id` integer. Clean `/cancel/{token}` URLs fall out naturally from the architecture.

**Security model parity with Laravel core.** HMAC-SHA256 keyed with a dedicated, configurable hash key (isolated from `APP_KEY`) matches and improves on Laravel's password reset token approach. Most hand-rolled implementations store plain tokens or reach for bcrypt (too slow for lookups). Establishing this as the standard approach for cancellation tokens is itself an ecosystem contribution.

### Competitive Landscape

- **Laravel signed routes** (`URL::temporarySignedRoute`): stateless, no revocation, no single-use, no entity association — complement not replacement
- **Laravel Sanctum tokens**: closest structural parallel (morph actor, HMAC hash) but designed for API authentication with no cancellable association
- **Laravel password reset** (`DatabaseTokenRepository`): correct single-use + time-expiry pattern but single-entity (email), no polymorphism
- **JWT libraries**: stateless by design, no consumed state, over-engineered for this use case
- **No Packagist equivalent**: confirmed by research

## Developer Tool Specific Requirements

### Package Overview

`laravel-cancellation-tokens` ships as a standard Laravel package: service provider, published migration and config, Eloquent model, validation rule, events, trait, Facade, and testing helpers. No framework modifications or custom bootstrapping are required beyond `composer require` and migration publish.

### Language & Framework Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.3 | Readonly classes, typed constants, modern array functions |
| Laravel | 12 | Currently supported; Laravel 13 compatible at release |
| Composer | 2.x | Standard |

**Dependencies:** No dependencies beyond Laravel's own framework packages (`illuminate/database`, `illuminate/support`, etc.). No unrelated third-party libraries.

### Installation

```bash
composer require foxen-digital/laravel-cancellation-tokens
php artisan vendor:publish --tag=cancellation-tokens-migrations
php artisan vendor:publish --tag=cancellation-tokens-config
php artisan migrate
```

Service provider auto-discovery via `composer.json` `extra.laravel.providers` — no manual registration required.

### Public API Surface

**Facade:**
```php
CancellationToken::create(cancellable: $booking, tokenable: $user, expiresAt: now()->addDays(7));
CancellationToken::verify(token: $token);
CancellationToken::consume(token: $token);
```

**Trait (`HasCancellationTokens` on cancellable models):**
```php
$booking->createCancellationToken(tokenable: $user, expiresAt: now()->addDays(7));
$booking->cancellationTokens(); // relationship
```

**Validation rule:**
```php
['token' => ['required', new ValidCancellationToken]]
```

**Events:** `TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired` — namespace `FoxenDigital\LaravelCancellationTokens\Events`.

**Testing:**
```php
CancellationToken::fake();
CancellationToken::assertTokenCreatedFor($booking);
CancellationToken::assertTokenConsumed();
```

### README Coverage Requirements

README must include end-to-end examples covering:
1. Basic booking cancellation flow (trait approach)
2. Multi-model cancellation flow (Facade approach)
3. Handling expired/consumed tokens via events
4. Testing with `CancellationTokenFake`
5. Configuring `model:prune` for token cleanup

### Implementation Constraints

- **MorphMap:** Package must not require or enforce a morph map; document optional configuration
- **Config surface:** Table name, token prefix (`ct_`), default expiry duration — entity-specific expiry is out of scope; consuming apps pass `expiresAt` explicitly
- **Token length:** 64 random bytes post-prefix
- **Hash comparison:** `hash_equals()` required — `===` prohibited
- **Migration guide:** Not applicable for v1 (first release)

## Functional Requirements

### Token Lifecycle Management

- **FR1:** Developer can create a cancellation token associated with both a tokenable actor and a cancellable subject
- **FR2:** Developer can specify an expiry timestamp at token creation time
- **FR3:** Developer can verify a presented plain-text token string against stored tokens
- **FR4:** Developer can consume a token, recording it as used
- **FR5:** Developer can retrieve the tokenable actor from a token record
- **FR6:** Developer can retrieve the cancellable subject from a token record

### Token Security

- **FR7:** The system stores only a hashed representation of the token — the plain-text value is never persisted
- **FR8:** The system returns a plain-text token string to the caller at creation time for inclusion in URLs
- **FR9:** The system prepends a configurable prefix to generated tokens before returning them to the caller
- **FR10:** The system enforces single-use tokens by tracking a consumption timestamp
- **FR11:** The system enforces time-based expiry by comparing against a stored expiry timestamp
- **FR12:** The system uses timing-safe comparison when verifying token hashes

### Model Integration (Trait)

- **FR13:** Developer can add cancellation token capabilities to any Eloquent model via a trait
- **FR14:** Developer can associate a token with any Eloquent model as the tokenable actor — not limited to `User`
- **FR15:** Developer can associate a token with any Eloquent model as the cancellable subject
- **FR16:** Developer can access all cancellation tokens for a cancellable model via an Eloquent relationship
- **FR17:** Developer can create a token directly on a cancellable model instance via the trait

### Facade API

- **FR18:** Developer can create, verify, and consume tokens via a static Facade without adding a trait to any model
- **FR19:** Developer can swap the Facade's underlying implementation in tests without modifying production code

### Validation

- **FR20:** Developer can validate a token string using a Laravel validation rule in a form request or controller
- **FR21:** The validation rule distinguishes between not-found, expired, and consumed failure states
- **FR22:** Developer can determine the specific reason a token failed validation to provide appropriate user feedback

### Event System

- **FR23:** The application can listen for an event when a token is successfully created
- **FR24:** The application can listen for an event when a token is successfully verified
- **FR25:** The application can listen for an event when a token is successfully consumed
- **FR26:** The application can listen for an event when an expired token is presented for verification

### Token Cleanup

- **FR27:** The application can automatically prune expired and consumed tokens using Laravel's built-in `model:prune` command
- **FR28:** Developer can configure the criteria that determine which tokens are prunable

### Testing Support

- **FR29:** Developer can replace the token service with an in-memory test double that requires no database
- **FR30:** Developer can assert that a token was created for a specific cancellable model in tests
- **FR31:** Developer can assert that a token was consumed in tests
- **FR32:** Developer can create token records using a model factory in feature tests

### Package Setup & Configuration

- **FR33:** Developer can publish the database migration to their application
- **FR34:** Developer can publish the configuration file to their application
- **FR35:** Developer can configure the token table name
- **FR36:** Developer can configure the token prefix
- **FR37:** Developer can configure the default token expiry duration
- **FR38:** The package registers automatically via Laravel's service provider auto-discovery

## Non-Functional Requirements

### Performance

- Token generation completes in a single database write — no multi-query overhead at creation time
- Token verification completes in a single database lookup — the token hash is the lookup key; no table scans
- HMAC-SHA256 is the required hashing algorithm — bcrypt is explicitly disallowed (too slow for synchronous request verification)
- The `cancellation_tokens` table must be indexed on the token hash column to guarantee O(log n) lookups regardless of table size
- Token pruning via `model:prune` must operate in batches to avoid memory exhaustion on large tables

### Security

- The plain-text token must never be persisted to any storage medium (database, cache, logs)
- Token hashes must be computed using HMAC-SHA256 keyed with the application's `APP_KEY`
- Token verification must use `hash_equals()` for timing-safe comparison — `===` comparison is prohibited
- Generated tokens must use cryptographically secure random bytes (minimum 64 bytes of randomness post-prefix)
- Token failure responses must not expose distinguishable error states externally in a way that enables enumeration attacks; failure reasons are available internally for developer use only
- The `ct_` prefix aids identification in the event of accidental exposure — it is not a security mechanism, and plain-text tokens must not be intentionally logged or written to any persistent storage

### Scalability

- All database queries issued by the package must use indexed columns — no unindexed WHERE clauses
- Pruning operations must support chunked deletion to avoid locking the table during bulk cleanup

### Integration

- Compatible with Laravel 12 and 13, PHP 8.3+
- Must not conflict with or override any Laravel core behaviour (events, validation, Eloquent, pruning)
- Service provider must register via Composer `extra.laravel` auto-discovery — no manual registration
- Must not require or enforce a morph map in the consuming application
- Must not introduce side effects on any Eloquent model that does not explicitly use the `HasCancellationTokens` trait
- `CancellationTokenFake` must implement the same interface as the real token service to enable transparent substitution in tests

### Quality & Maintainability

- Test coverage ≥ 95% (unit + integration), measured by line coverage
- All public API methods covered by tests exercising both success and failure paths
- Package passes a security review with no token-handling vulnerabilities before v1.0.0 release
- Package ships with `CancellationTokenFactory` so consuming applications can generate test data without writing their own factory
