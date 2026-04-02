---
stepsCompleted:
  - step-01-validate-prerequisites
  - step-02-design-epics
  - step-03-create-stories
  - step-04-final-validation
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/architecture.md
---

# laravel-cancellation-tokens - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for laravel-cancellation-tokens, decomposing the requirements from the PRD and Architecture into implementable stories.

## Requirements Inventory

### Functional Requirements

**Token Lifecycle Management:**
- FR1: Developer can create a cancellation token associated with both a tokenable actor and a cancellable subject
- FR2: Developer can specify an expiry timestamp at token creation time
- FR3: Developer can verify a presented plain-text token string against stored tokens
- FR4: Developer can consume a token, recording it as used
- FR5: Developer can retrieve the tokenable actor from a token record
- FR6: Developer can retrieve the cancellable subject from a token record

**Token Security:**
- FR7: The system stores only a hashed representation of the token — the plain-text value is never persisted
- FR8: The system returns a plain-text token string to the caller at creation time for inclusion in URLs
- FR9: The system prepends a configurable prefix to generated tokens before returning them to the caller
- FR10: The system enforces single-use tokens by tracking a consumption timestamp
- FR11: The system enforces time-based expiry by comparing against a stored expiry timestamp
- FR12: The system uses timing-safe comparison when verifying token hashes

**Model Integration (Trait):**
- FR13: Developer can add cancellation token capabilities to any Eloquent model via a trait
- FR14: Developer can associate a token with any Eloquent model as the tokenable actor — not limited to `User`
- FR15: Developer can associate a token with any Eloquent model as the cancellable subject
- FR16: Developer can access all cancellation tokens for a cancellable model via an Eloquent relationship
- FR17: Developer can create a token directly on a cancellable model instance via the trait

**Facade API:**
- FR18: Developer can create, verify, and consume tokens via a static Facade without adding a trait to any model
- FR19: Developer can swap the Facade's underlying implementation in tests without modifying production code

**Validation:**
- FR20: Developer can validate a token string using a Laravel validation rule in a form request or controller
- FR21: The validation rule distinguishes between not-found, expired, and consumed failure states
- FR22: Developer can determine the specific reason a token failed validation to provide appropriate user feedback

**Event System:**
- FR23: The application can listen for an event when a token is successfully created
- FR24: The application can listen for an event when a token is successfully verified
- FR25: The application can listen for an event when a token is successfully consumed
- FR26: The application can listen for an event when an expired token is presented for verification

**Token Cleanup:**
- FR27: The application can automatically prune expired and consumed tokens using Laravel's built-in `model:prune` command
- FR28: Developer can configure the criteria that determine which tokens are prunable

**Testing Support:**
- FR29: Developer can replace the token service with an in-memory test double that requires no database
- FR30: Developer can assert that a token was created for a specific cancellable model in tests
- FR31: Developer can assert that a token was consumed in tests
- FR32: Developer can create token records using a model factory in feature tests

**Package Setup & Configuration:**
- FR33: Developer can publish the database migration to their application
- FR34: Developer can publish the configuration file to their application
- FR35: Developer can configure the token table name
- FR36: Developer can configure the token prefix
- FR37: Developer can configure the default token expiry duration
- FR38: The package registers automatically via Laravel's service provider auto-discovery

### NonFunctional Requirements

**Performance:**
- NFR1: Token generation completes in a single database write — no multi-query overhead at creation time
- NFR2: Token verification completes in a single database lookup — the token hash is the lookup key; no table scans
- NFR3: HMAC-SHA256 is the required hashing algorithm — bcrypt is explicitly disallowed (too slow for synchronous request verification)
- NFR4: The `cancellation_tokens` table must be indexed on the token hash column to guarantee O(log n) lookups regardless of table size
- NFR5: Token pruning via `model:prune` must operate in batches to avoid memory exhaustion on large tables

**Security:**
- NFR6: The plain-text token must never be persisted to any storage medium (database, cache, logs)
- NFR7: Token hashes must be computed using HMAC-SHA256 keyed with the application's `APP_KEY`
- NFR8: Token verification must use `hash_equals()` for timing-safe comparison — `===` comparison is prohibited
- NFR9: Generated tokens must use cryptographically secure random bytes (minimum 64 bytes of randomness post-prefix)
- NFR10: Token failure responses must not expose distinguishable error states externally in a way that enables enumeration attacks; failure reasons are available internally for developer use only
- NFR11: The `ct_` prefix aids token identification in the event of accidental exposure — plain-text tokens must not be intentionally logged or written to any persistent storage

**Scalability:**
- NFR12: All database queries issued by the package must use indexed columns — no unindexed WHERE clauses
- NFR13: Pruning operations must support chunked deletion to avoid locking the table during bulk cleanup

**Integration:**
- NFR14: Compatible with Laravel 12 and 13, PHP 8.3+
- NFR15: Must not conflict with or override any Laravel core behaviour (events, validation, Eloquent, pruning)
- NFR16: Service provider must register via Composer `extra.laravel` auto-discovery — no manual registration
- NFR17: Must not require or enforce a morph map in the consuming application
- NFR18: Must not introduce side effects on any Eloquent model that does not explicitly use the `HasCancellationTokens` trait
- NFR19: `CancellationTokenFake` must implement the same interface as the real token service to enable transparent substitution in tests

**Quality & Maintainability:**
- NFR20: Test coverage ≥ 95% (unit + integration), measured by line coverage
- NFR21: All public API methods covered by tests exercising both success and failure paths
- NFR22: Package passes a security review with no token-handling vulnerabilities before v1.0.0 release
- NFR23: Package ships with `CancellationTokenFactory` so consuming applications can generate test data without writing their own factory

### Additional Requirements

*From Architecture document — technical requirements that affect implementation:*

- AR1: ~~Scaffold using `spatie/package-laravel-cancellation-tokens-laravel` with Pint, PHPStan, Dependabot~~ **ALREADY DONE** (per user)
- AR2: Implement `TokenVerificationFailure` backed PHP 8.1+ enum with cases: `NotFound`, `Expired`, `Consumed`
- AR3: Implement `TokenVerificationException` carrying a `TokenVerificationFailure` enum case
- AR4: `CancellationTokenContract` interface must define exact signatures: `create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string` (null defaults to `now()->addMinutes(config('cancellation-tokens.default_expiry'))`), `verify(string $plainToken): CancellationToken`, `consume(string $plainToken): CancellationToken`
- AR4a: **Namespace correction** — the scaffolded vendor namespace is `Foxen\CancellationToken` (not `FoxenDigital\LaravelCancellationTokens` as stated in the architecture doc). All implementation must use `Foxen\CancellationToken` as the root namespace. PSR-4 autoloading: `Foxen\CancellationToken\` → `src/`
- AR5: `CancellationTokenService` and `CancellationTokenFake` must both implement `CancellationTokenContract`
- AR6: All events must be `readonly` classes with public constructor properties only (no getters)
- AR7: `TokenExpired` event must fire before the `TokenVerificationException` is thrown on failure paths
- AR8: `CancellationTokenFake` must expose assertion API: `assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null)`, `assertTokenConsumed()`, `assertTokenNotConsumed()`, `assertNoTokensCreated()`
- AR9: Config schema: `table = 'cancellation_tokens'`, `prefix = 'ct_'`, `default_expiry = 10080` (minutes / 7 days)
- AR10: Default `pruneQuery()` criteria: delete where `expires_at < now()` OR `used_at IS NOT NULL`
- AR11: Migration schema: `id` BIGINT PK, `token` VARCHAR(255) unique indexed, `tokenable_type/id`, `cancellable_type/id`, `expires_at` nullable, `used_at` nullable, timestamps — NO soft deletes
- AR12: Pest tests must use `it()` syntax, `expect()` assertions, `beforeEach()` for setup; unit tests must not hit the database
- AR13: `ArchTest.php` must enforce structural rules via `pest-plugin-arch` (e.g. fake not loaded in production, no `===` on token strings)
- AR14: `CancellationTokenFake` lives in `src/Testing/` (not `tests/`) so consuming app test suites can require it via `require-dev`
- AR15: CI matrix: PHP 8.3/8.4 × Laravel 12/13 (pre-configured by spatie skeleton — no changes required)

### UX Design Requirements

*Not applicable — this package ships no views, routes, or UI components.*

### FR Coverage Map

| FR | Epic | Brief |
|---|---|---|
| FR1–FR6 | Epic 2 | Token create/verify/consume/retrieve actor+subject |
| FR7–FR12 | Epic 2 | Security: hash-only storage, prefix, single-use, expiry, timing-safe compare |
| FR13–FR17 | Epic 3 | `HasCancellationTokens` trait + polymorphic relationships |
| FR18–FR19 | Epic 3 | `CancellationToken` Facade + swappable implementation |
| FR20–FR22 | Epic 3 | `ValidCancellationToken` rule with distinguishable failures |
| FR23–FR26 | Epic 4 | `TokenCreated/Verified/Consumed/Expired` events |
| FR27–FR28 | Epic 5 | `Prunable` + `model:prune` integration |
| FR29–FR31 | Epic 6 | `CancellationTokenFake` + assertion helpers |
| FR32 | Epic 6 | `CancellationTokenFactory` for feature tests |
| FR33–FR38 | Epic 1 | Publishable migration + config + auto-discovery |

## Epic List

### Epic 1: Package Foundation — Install, Configure, Migrate
A developer can install the package via Composer, publish the migration and config file, run `php artisan migrate`, and have the `cancellation_tokens` table ready in their application. Service provider auto-discovery works out of the box.

**FRs covered:** FR33, FR34, FR35, FR36, FR37, FR38
**NFRs addressed:** NFR14, NFR16, NFR17
**Architecture:** AR9 (config schema), AR11 (migration schema with unique index on `token`)

---

### Epic 2: Core Token Lifecycle — Create, Verify, Consume
A developer can create cryptographically secure tokens linked to any two models, verify a presented plain-text token (checking expiry and usage), and consume a token to mark it used. The full security model is in place: HMAC-SHA256, `hash_equals()`, single-use enforcement, no plain-text persistence.

**FRs covered:** FR1, FR2, FR3, FR4, FR5, FR6, FR7, FR8, FR9, FR10, FR11, FR12
**NFRs addressed:** NFR1–NFR4, NFR6–NFR11, NFR15
**Architecture:** AR2 (`TokenVerificationFailure` enum), AR3 (`TokenVerificationException`), AR4 (`CancellationTokenContract` — namespace `Foxen\CancellationToken`), AR5 (service implements contract)

---

### Epic 3: Developer API Surface — Trait, Facade, Validation Rule
A developer can use the `HasCancellationTokens` trait on any cancellable Eloquent model, use the `CancellationToken` Facade for stateless invocation (no trait required), and validate token strings in form requests via `ValidCancellationToken` with distinguishable failure reasons (not-found / expired / consumed).

**FRs covered:** FR13, FR14, FR15, FR16, FR17, FR18, FR19, FR20, FR21, FR22
**NFRs addressed:** NFR18

---

### Epic 4: Event System — Observe and React to Token Lifecycle
A developer can listen to `TokenCreated`, `TokenVerified`, `TokenConsumed`, and `TokenExpired` events to drive application-specific reactions without touching token logic.

**FRs covered:** FR23, FR24, FR25, FR26
**Architecture:** AR6 (readonly event classes), AR7 (TokenExpired fires before exception throw)

---

### Epic 5: Token Cleanup — Automated Pruning via `model:prune`
A developer can schedule `php artisan model:prune` and have expired and consumed tokens automatically removed in chunks, with no custom Artisan commands.

**FRs covered:** FR27, FR28
**NFRs addressed:** NFR5, NFR12, NFR13
**Architecture:** AR10 (default pruneQuery: `expires_at < now()` OR `used_at IS NOT NULL`)

---

### Epic 6: Testing, Quality & Documentation
A developer can swap in `CancellationTokenFake` for unit tests (no database), assert token creation and consumption, use `CancellationTokenFactory` in feature tests, and follow the README to implement a complete cancellation flow in under 30 minutes. Architecture integrity is verified via `ArchTest.php`.

**FRs covered:** FR29, FR30, FR31, FR32
**NFRs addressed:** NFR19, NFR20, NFR21, NFR22, NFR23
**Architecture:** AR8 (fake assertion API), AR12 (Pest conventions), AR13 (ArchTest), AR14 (`src/Testing/` location)
**Includes:** README story covering all five documented use-case scenarios

## Epic 1: Package Foundation — Install, Configure, Migrate

A developer can install the package via Composer, publish the migration and config file, run `php artisan migrate`, and have the `cancellation_tokens` table ready in their application. Service provider auto-discovery works out of the box.

### Story 1.1: Package Bootstrap — Service Provider, Config, and Migration

As a developer,
I want to install the package and publish its migration and config,
So that the `cancellation_tokens` table is available in my application with zero manual wiring.

**Acceptance Criteria:**

**Given** the package is required via Composer
**When** the application bootstraps
**Then** the service provider is auto-discovered via `composer.json` `extra.laravel` — no manual registration required

**Given** the package is installed
**When** `php artisan vendor:publish --tag=cancellation-tokens-migrations` is run
**Then** the migration file is copied to the application's `database/migrations/` directory

**Given** the migration is published
**When** `php artisan migrate` is run
**Then** the `cancellation_tokens` table exists with: `id` (BIGINT PK), `token` (VARCHAR 255, unique index), `tokenable_type`, `tokenable_id`, `cancellable_type`, `cancellable_id`, `expires_at` (nullable timestamp), `used_at` (nullable timestamp), `created_at`, `updated_at` — and no `deleted_at` column

**Given** the package is installed
**When** `php artisan vendor:publish --tag=cancellation-tokens-config` is run
**Then** `config/cancellation-tokens.php` is available in the application with keys `table` (default: `'cancellation_tokens'`), `prefix` (default: `'ct_'`), and `default_expiry` (default: `10080`)

**Given** config is published and `table`, `prefix`, or `default_expiry` are changed
**When** the application runs
**Then** the package reads and uses the customized values

**Given** the config is published and the `table` value is changed to a custom name
**When** `php artisan migrate` is run
**Then** the table is created using the custom name, not the default `cancellation_tokens`

## Epic 2: Core Token Lifecycle — Create, Verify, Consume

A developer can create cryptographically secure tokens linked to any two models, verify a presented plain-text token (checking expiry and usage), and consume a token to mark it used. The full security model is in place: HMAC-SHA256, `hash_equals()`, single-use enforcement, no plain-text persistence.

### Story 2.1: Core Types — Contract, Enum, and Exception

As a developer,
I want a shared contract interface, verification failure enum, and exception type,
So that all package components share a single source of truth for token operation signatures and failure states.

**Acceptance Criteria:**

**Given** the package is installed
**When** `CancellationTokenContract` is inspected
**Then** it declares three methods with exact signatures: `create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string`, `verify(string $plainToken): CancellationToken`, and `consume(string $plainToken): CancellationToken`

**Given** a token operation fails
**When** the failure reason is inspected
**Then** it is one of three `TokenVerificationFailure` backed enum cases: `NotFound`, `Expired`, or `Consumed`

**Given** a token operation fails
**When** a `TokenVerificationException` is caught
**Then** it exposes a public `$reason` property containing a `TokenVerificationFailure` enum case

**Given** all three types are defined
**When** an architecture check is run
**Then** all classes exist in the `Foxen\CancellationToken` root namespace at their specified paths: `Contracts/CancellationTokenContract.php`, `Enums/TokenVerificationFailure.php`, `Exceptions/TokenVerificationException.php`

---

### Story 2.2: CancellationToken Eloquent Model

As a developer,
I want a `CancellationToken` Eloquent model backed by the published migration,
So that token records can be read and written using Laravel's standard ORM conventions.

**Acceptance Criteria:**

**Given** the migration has been run
**When** the `CancellationToken` model is instantiated
**Then** it maps to the table name from `config('cancellation-tokens.table')`

**Given** a token record exists
**When** `$token->tokenable` is accessed
**Then** it returns the associated polymorphic actor model via a `morphTo` relationship

**Given** a token record exists
**When** `$token->cancellable` is accessed
**Then** it returns the associated polymorphic subject model via a `morphTo` relationship

**Given** a token record exists
**When** `$token->expires_at` or `$token->used_at` are accessed
**Then** they are cast as `Carbon` instances (or `null` if not set)

**Given** an architecture check is run
**When** the model class is inspected
**Then** it contains no token hashing, verification, or business logic — those concerns belong in the service

---

### Story 2.3: Token Creation

As a developer,
I want to create a cancellation token via the service,
So that I receive a plain-text token string to embed in URLs while the package handles secure storage.

**Acceptance Criteria:**

**Given** a cancellable model, a tokenable model, and an explicit expiry timestamp
**When** `CancellationTokenService::create()` is called
**Then** a new row is inserted into `cancellation_tokens` with the correct `cancellable_type/id`, `tokenable_type/id`, and `expires_at` values

**Given** a cancellable model and a tokenable model with no `expiresAt` argument provided
**When** `CancellationTokenService::create()` is called
**Then** `expires_at` is set to `now()->addMinutes(config('cancellation-tokens.default_expiry'))`

**Given** a token is created
**When** the stored `token` column value is inspected
**Then** it is an HMAC-SHA256 hash of the plain-text token keyed with `cancellation-tokens.hash_key` — the plain-text value is never in the database

**Given** a token is created
**When** the return value of `create()` is inspected
**Then** it is a plain-text string beginning with the configured prefix (default `ct_`) followed by 64 random alphanumeric characters

**Given** two successive calls to `create()`
**When** the returned token strings are compared
**Then** they are unique — no two tokens are identical

**Given** a token is created
**When** the plain-text token string is hashed with `hash_hmac('sha256', $plainToken, config('cancellation-tokens.hash_key'))`
**Then** the result matches the `token` column value stored in the database

**Given** `cancellation-tokens.hash_key` is null or empty
**When** `CancellationTokenService::create()` is called
**Then** a `RuntimeException` is thrown with a message referencing `cancellation-tokens.hash_key`

---

### Story 2.4: Token Verification

As a developer,
I want to verify a plain-text token string,
So that I can confirm it exists, has not expired, and has not been consumed — and retrieve its associated models.

**Acceptance Criteria:**

**Given** a valid, unexpired, unconsumed token
**When** `CancellationTokenService::verify($plainToken)` is called
**Then** the `CancellationToken` model instance is returned with `tokenable` and `cancellable` relationships accessible

**Given** a token string that does not match any stored hash
**When** `verify()` is called
**Then** a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::NotFound`

**Given** a token whose `expires_at` is in the past
**When** `verify()` is called
**Then** a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Expired`

**Given** a token whose `used_at` is not null
**When** `verify()` is called
**Then** a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Consumed`

**Given** any verification attempt
**When** the hash comparison is performed
**Then** `hash_equals()` is used — direct `===` comparison of token strings is never used

**Given** a non-existent token and an expired token presented for verification
**When** the exception messages or response codes are compared externally
**Then** they are indistinguishable — failure reasons are available on the exception for internal use only, not surfaced to HTTP responses automatically

---

### Story 2.5: Token Consumption

As a developer,
I want to consume a token,
So that it is permanently marked as used and cannot be verified or consumed again.

**Acceptance Criteria:**

**Given** a valid, unexpired, unconsumed token
**When** `CancellationTokenService::consume($plainToken)` is called
**Then** the `CancellationToken` model's `used_at` column is set to the current timestamp and the model instance is returned

**Given** a token that has already been consumed (`used_at` is not null)
**When** `consume()` is called
**Then** a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Consumed`

**Given** an expired token
**When** `consume()` is called
**Then** a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Expired`

**Given** a token that has been consumed
**When** `verify()` is subsequently called with the same plain-text token
**Then** a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Consumed`

## Epic 3: Developer API Surface — Trait, Facade, Validation Rule

A developer can use the `HasCancellationTokens` trait on any cancellable Eloquent model, use the `CancellationToken` Facade for stateless invocation (no trait required), and validate token strings in form requests via `ValidCancellationToken` with distinguishable failure reasons.

### Story 3.1: HasCancellationTokens Trait

As a developer,
I want to add the `HasCancellationTokens` trait to any Eloquent model,
So that I can create and retrieve cancellation tokens directly on cancellable model instances.

**Acceptance Criteria:**

**Given** a model uses the `HasCancellationTokens` trait
**When** `$model->createCancellationToken(tokenable: $actor)` is called with or without an explicit `expiresAt`
**Then** a new token is created via the service and the plain-text token string is returned

**Given** a model uses the trait
**When** `$model->cancellationTokens()` is called
**Then** it returns an Eloquent `morphMany` relationship of all `CancellationToken` records where this model is the `cancellable`

**Given** any Eloquent model class that does NOT use the trait
**When** the application bootstraps
**Then** no side effects are introduced on that model — the trait is strictly opt-in

**Given** the tokenable actor is any Eloquent model (not just `User`)
**When** `createCancellationToken(tokenable: $nonUserModel, expiresAt: ...)` is called
**Then** the token is created successfully with the non-User model as the actor

---

### Story 3.2: CancellationToken Facade and Service Provider Binding

As a developer,
I want to create, verify, and consume tokens via a static Facade,
So that I can use the full token API without adding a trait to any model, and swap the implementation in tests.

**Acceptance Criteria:**

**Given** the package is installed
**When** `CancellationToken::create(cancellable: $model, tokenable: $actor)` is called with or without an explicit `expiresAt`
**Then** it delegates to the bound `CancellationTokenContract` implementation and returns the plain-text token string

**Given** the package is installed
**When** `CancellationToken::verify($plainToken)` is called
**Then** it delegates to the bound `CancellationTokenContract` implementation and returns the `CancellationToken` model or throws `TokenVerificationException`

**Given** the package is installed
**When** `CancellationToken::consume($plainToken)` is called
**Then** it delegates to the bound `CancellationTokenContract` implementation and returns the consumed `CancellationToken` model or throws `TokenVerificationException`

**Given** the service provider is loaded
**When** the service container resolves `CancellationTokenContract`
**Then** it returns an instance of `CancellationTokenService`

---

### Story 3.3: ValidCancellationToken Validation Rule

As a developer,
I want to validate a token string using a Laravel validation rule,
So that I can integrate token validation into standard form requests and provide appropriate feedback for each failure reason.

**Acceptance Criteria:**

**Given** a valid, unexpired, unconsumed token string
**When** `['token' => ['required', new ValidCancellationToken]]` is used in a form request
**Then** validation passes

**Given** a token string that does not exist
**When** the `ValidCancellationToken` rule is evaluated
**Then** validation fails with a failure reason of `TokenVerificationFailure::NotFound` accessible to the developer

**Given** an expired token string
**When** the `ValidCancellationToken` rule is evaluated
**Then** validation fails with a failure reason of `TokenVerificationFailure::Expired` accessible to the developer

**Given** a consumed token string
**When** the `ValidCancellationToken` rule is evaluated
**Then** validation fails with a failure reason of `TokenVerificationFailure::Consumed` accessible to the developer

**Given** any token validation failure
**When** the validation error is returned in an HTTP response
**Then** the error message does not expose which specific failure reason occurred — the reason is available internally via the rule, not in the public response

## Epic 4: Event System — Observe and React to Token Lifecycle

A developer can listen to `TokenCreated`, `TokenVerified`, `TokenConsumed`, and `TokenExpired` events to drive application-specific reactions without touching token logic.

### Story 4.1: Token Lifecycle Events

As a developer,
I want the package to dispatch events at each stage of the token lifecycle,
So that I can react to token creation, verification, consumption, and expiry without modifying package code.

**Acceptance Criteria:**

**Given** `CancellationTokenService::create()` completes successfully
**When** a listener is registered for `TokenCreated`
**Then** it receives a `TokenCreated` event with a public `$token` property containing the newly created `CancellationToken` model

**Given** `CancellationTokenService::verify()` completes successfully
**When** a listener is registered for `TokenVerified`
**Then** it receives a `TokenVerified` event with a public `$token` property containing the verified `CancellationToken` model

**Given** `CancellationTokenService::consume()` completes successfully
**When** a listener is registered for `TokenConsumed`
**Then** it receives a `TokenConsumed` event with a public `$token` property containing the consumed `CancellationToken` model

**Given** an expired token is presented to `verify()` or `consume()`
**When** a listener is registered for `TokenExpired`
**Then** it receives a `TokenExpired` event with a public `$token` property containing the expired `CancellationToken` model — dispatched **before** the `TokenVerificationException` is thrown

**Given** all four event classes are inspected
**When** an architecture check is run
**Then** all are `readonly` classes with a single public constructor property (`CancellationToken $token`) and no getter methods

**Given** `TokenExpired` is dispatched and a `TokenVerificationException` is subsequently thrown
**When** both the event payload and exception are inspected
**Then** `$event->token->expires_at` is in the past and `$exception->reason === TokenVerificationFailure::Expired`

## Epic 5: Token Cleanup — Automated Pruning via `model:prune`

A developer can schedule `php artisan model:prune` and have expired and consumed tokens automatically removed in chunks, with no custom Artisan commands.

### Story 5.1: Prunable Token Cleanup

As a developer,
I want expired and consumed tokens to be automatically removed by Laravel's `model:prune` command,
So that the `cancellation_tokens` table stays lean without any custom Artisan commands or manual cleanup logic.

**Acceptance Criteria:**

**Given** `model:prune` is scheduled in the application
**When** `php artisan model:prune` is run
**Then** all tokens where `expires_at < now()` OR `used_at IS NOT NULL` are deleted

**Given** a token that is neither expired nor consumed
**When** `php artisan model:prune` is run
**Then** that token is not deleted

**Given** a large number of prunable tokens exist
**When** `php artisan model:prune` is run
**Then** deletion is performed in chunks — no single bulk delete that could lock the table

**Given** the `CancellationToken` model is inspected
**When** an architecture check is run
**Then** it uses Laravel's `Prunable` trait and defines a `pruneQuery()` method — no custom Artisan command exists in the package

**Given** a developer wants to customise pruning criteria
**When** they extend or override the model's `pruneQuery()` method in their application
**Then** the custom criteria are used instead of the package defaults

## Epic 6: Testing, Quality & Documentation

A developer can swap in `CancellationTokenFake` for unit tests (no database), assert token creation and consumption, use `CancellationTokenFactory` in feature tests, and follow the README to implement a complete cancellation flow in under 30 minutes. Architecture integrity is verified via `ArchTest.php`.

### Story 6.1: CancellationTokenFake

As a developer,
I want an in-memory test double for the token service,
So that I can write fast unit tests without a database and assert token interactions with precision.

**Acceptance Criteria:**

**Given** `CancellationToken::fake()` is called in a test
**When** the service container resolves `CancellationTokenContract`
**Then** it returns the `CancellationTokenFake` instance instead of `CancellationTokenService`, without any changes to production code

**Given** `CancellationTokenFake` is active and `create()` is called
**When** `CancellationToken::assertTokenCreatedFor($cancellable)` is called
**Then** the assertion passes if a token was created for that cancellable model

**Given** `CancellationTokenFake` is active and `create()` is called with a specific tokenable
**When** `CancellationToken::assertTokenCreatedFor($cancellable, $tokenable)` is called
**Then** the assertion passes only if both the cancellable and tokenable match

**Given** `CancellationTokenFake` is active and `consume()` is called
**When** `CancellationToken::assertTokenConsumed()` is called
**Then** the assertion passes

**Given** `CancellationTokenFake` is active and `consume()` has not been called
**When** `CancellationToken::assertTokenNotConsumed()` is called
**Then** the assertion passes

**Given** `CancellationTokenFake` is active and no tokens have been created
**When** `CancellationToken::assertNoTokensCreated()` is called
**Then** the assertion passes

**Given** `CancellationTokenFake` is inspected
**When** an architecture check is run
**Then** it lives in `src/Testing/CancellationTokenFake.php`, implements `CancellationTokenContract`, and is never loaded outside of test environments

---

### Story 6.2: CancellationTokenFactory

As a developer,
I want a model factory for `CancellationToken`,
So that I can seed realistic token records in feature tests without writing my own factory.

**Acceptance Criteria:**

**Given** the package is installed
**When** `CancellationToken::factory()->create()` is called in a feature test
**Then** a valid token record is persisted with a hashed `token` value, polymorphic morph columns populated, and a future `expires_at`

**Given** the factory is used
**When** `CancellationToken::factory()->consumed()->create()` is called
**Then** the created record has a non-null `used_at` timestamp

**Given** the factory is used
**When** `CancellationToken::factory()->expired()->create()` is called
**Then** the created record has an `expires_at` in the past

**Given** the factory is used
**When** `CancellationToken::factory()->for($cancellable, 'cancellable')->create()` is called
**Then** the created record is associated with the given cancellable model

---

### Story 6.3: Architecture Tests

As a developer,
I want automated architecture assertions enforced in CI,
So that structural rules are verified on every pull request without relying on manual code review.

**Acceptance Criteria:**

**Given** `ArchTest.php` runs via `composer test`
**When** any class in `src/Testing/` is loaded
**Then** the test fails if it is loaded outside of a test environment

**Given** `ArchTest.php` runs
**When** token hash comparison code in `src/` is inspected
**Then** the test fails if `===` is used to compare token strings (enforcing `hash_equals()`)

**Given** `ArchTest.php` runs
**When** all classes in `src/` are inspected
**Then** the test fails if any class stores, logs, or returns a plain-text token string after the initial `create()` return

**Given** `ArchTest.php` runs
**When** all Pest test files are inspected
**Then** the test fails if any test file uses `setUp()` instead of `beforeEach()`, or `assertX()` style assertions where a Pest `expect()` equivalent exists

---

### Story 6.4: README Documentation

As a developer unfamiliar with the package,
I want a comprehensive README,
So that I can implement a complete cancellation flow in under 30 minutes using only the README as reference.

**Acceptance Criteria:**

**Given** the README is published with v1.0.0
**When** a developer follows the installation section
**Then** they can complete `composer require`, publish migration and config, and run `php artisan migrate` with no ambiguity

**Given** the README is published
**When** a developer reads the basic usage section
**Then** it demonstrates a complete booking cancellation flow using the `HasCancellationTokens` trait: token creation, embedding in an email URL, and validation via `ValidCancellationToken`

**Given** the README is published
**When** a developer reads the Facade usage section
**Then** it demonstrates the multi-model flow using `CancellationToken::create()` directly, without a trait on either model

**Given** the README is published
**When** a developer reads the events section
**Then** it shows how to listen for `TokenExpired`, `TokenConsumed`, and the other lifecycle events to handle failure cases and trigger downstream logic

**Given** the README is published
**When** a developer reads the testing section
**Then** it demonstrates both `CancellationToken::fake()` with assertion helpers for unit tests, and `CancellationTokenFactory` for feature tests

**Given** the README is published
**When** a developer reads the token cleanup section
**Then** it shows how to schedule `model:prune` and explains the default pruning criteria
