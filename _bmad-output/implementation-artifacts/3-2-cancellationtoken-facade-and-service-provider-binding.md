# Story 3.2: CancellationToken Facade and Service Provider Binding

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a developer,
I want to create, verify, and consume tokens via a static Facade,
so that I can use the full token API without adding a trait to any model, and swap the implementation in tests.

## Acceptance Criteria

1. **Facade create delegation** — Given the package is installed, when `CancellationToken::create(cancellable: $model, tokenable: $actor)` is called with or without an explicit `expiresAt`, then it delegates to the bound `CancellationTokenContract` implementation and returns the plain-text token string

2. **Facade verify delegation** — Given the package is installed, when `CancellationToken::verify($plainToken)` is called, then it delegates to the bound `CancellationTokenContract` implementation and returns the `CancellationToken` model or throws `TokenVerificationException`

3. **Facade consume delegation** — Given the package is installed, when `CancellationToken::consume($plainToken)` is called, then it delegates to the bound `CancellationTokenContract` implementation and returns the consumed `CancellationToken` model or throws `TokenVerificationException`

4. **Service container binding** — Given the service provider is loaded, when the service container resolves `CancellationTokenContract`, then it returns an instance of `CancellationTokenService`

## Tasks / Subtasks

- [x] **Task 1: Write failing tests for Facade and Service Provider binding** (RED phase — AC: 1-4)
  - [x] 1.1 Create `tests/Feature/FacadeTest.php` with `uses(RefreshDatabase::class)` and `beforeEach()` setup (same pattern as `TraitTest.php`)
  - [x] 1.2 Test: container resolves `CancellationTokenContract` to an instance of `CancellationTokenService` (AC 4)
  - [x] 1.3 Test: `CancellationToken::create(cancellable: $booking, tokenable: $user)` returns a plain-text token string starting with the configured prefix (AC 1)
  - [x] 1.4 Test: `CancellationToken::create(...)` with explicit `expiresAt` persists a token with that expiry (AC 1)
  - [x] 1.5 Test: `CancellationToken::verify($plainToken)` on a valid token returns the `CancellationToken` model (AC 2)
  - [x] 1.6 Test: `CancellationToken::verify($plainToken)` on an expired token throws `TokenVerificationException` with `Expired` reason (AC 2)
  - [x] 1.7 Test: `CancellationToken::verify($plainToken)` on a consumed token throws `TokenVerificationException` with `Consumed` reason (AC 2)
  - [x] 1.8 Test: `CancellationToken::verify($plainToken)` on a non-existent token throws `TokenVerificationException` with `NotFound` reason (AC 2)
  - [x] 1.9 Test: `CancellationToken::consume($plainToken)` marks the token as used and returns the model (AC 3)
  - [x] 1.10 Test: `CancellationToken::consume($plainToken)` on an already-consumed token throws `TokenVerificationException` with `Consumed` reason (AC 3)
  - [x] 1.11 Test: `CancellationToken::consume($plainToken)` on an expired token throws `TokenVerificationException` with `Expired` reason (AC 3)
  - [x] 1.12 Test: facade accessor returns `CancellationTokenContract::class` (architectural verification)
  - [x] 1.13 Confirm all new tests FAIL or PASS (facade and binding already exist — tests may pass immediately)

- [x] **Task 2: Verify and fix existing implementation if needed** (GREEN phase — AC: 1-4)
  - [x] 2.1 Verify `src/Facades/CancellationToken.php` delegates via `getFacadeAccessor()` returning `CancellationTokenContract::class`
  - [x] 2.2 Verify `src/CancellationTokenServiceProvider.php` binds `CancellationTokenContract` → `CancellationTokenService` in `packageBooted()`
  - [x] 2.3 Verify `composer.json` `extra.laravel.aliases` registers the `CancellationToken` alias
  - [x] 2.4 If tests fail, fix the Facade or Service Provider binding as needed
  - [x] 2.5 Confirm all tests pass

- [x] **Task 3: Refactor and quality checks** (REFACTOR phase — AC: all)
  - [x] 3.1 Verify code follows project conventions (namespace, directory, no `===` on hashes, no plain-text persistence)
  - [x] 3.2 Run `composer test` — all tests pass (no regressions)
  - [x] 3.3 Run `composer analyse` — PHPStan passes
  - [x] 3.4 Run `composer format` — Pint passes

## Dev Notes

### EXISTING IMPLEMENTATION — DO NOT RECREATE

The Facade and Service Provider binding **already exist** from Stories 1.1 and 2.3. This story is about **testing and verifying** the delegation works correctly.

**Existing files:**

| File | Status | Description |
|---|---|---|
| `src/Facades/CancellationToken.php` | EXISTS | Facade with `getFacadeAccessor()` returning `CancellationTokenContract::class` |
| `src/CancellationTokenServiceProvider.php` | EXISTS | Binds `CancellationTokenContract` → `CancellationTokenService` in `packageBooted()` |
| `composer.json` `extra.laravel` | EXISTS | Auto-discovery with providers and aliases configured |

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation uses this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

### Facade Implementation (Already Exists)

```php
// src/Facades/CancellationToken.php — DO NOT MODIFY unless broken
namespace Foxen\CancellationToken\Facades;

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Illuminate\Support\Facades\Facade;

class CancellationToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CancellationTokenContract::class;
    }
}
```

The facade accessor returns the **contract class name** (not the service class name). This is critical — it means:
- The container resolves `CancellationTokenContract::class` to get the underlying instance
- The service provider binds `CancellationTokenContract::class` → `CancellationTokenService`
- When `CancellationToken::fake()` is implemented (Story 6.1), it swaps this binding to `CancellationTokenFake`

### Service Provider Binding (Already Exists)

```php
// src/CancellationTokenServiceProvider.php — DO NOT MODIFY unless broken
public function packageBooted(): void
{
    $this->app->bind(
        CancellationTokenContract::class,
        CancellationTokenService::class
    );
}
```

Uses `$this->app->bind()` (not `singleton()`) — each resolution creates a new instance. This is correct for stateless service operations.

### Facade Usage in Tests

Use the **full Facade class path** in test imports:

```php
use Foxen\CancellationToken\Facades\CancellationToken;
```

NOT the model class (`Foxen\CancellationToken\Models\CancellationToken`). If you need the model class in the same test file, alias it:

```php
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Models\CancellationToken as CancellationTokenModel;
```

The existing `TestCase.php` does NOT define `getPackageAliases()`. Laravel's auto-discovery (via `composer.json` `extra.laravel.aliases`) handles alias registration. Using the full class path in `use` statements avoids any alias registration issues.

### Why This Story Matters

The Facade is the primary public API for developers who don't want to add the `HasCancellationTokens` trait to their models (FR18). It must be verified to delegate correctly. The container binding is the seam that enables `CancellationToken::fake()` (Story 6.1, FR19) — getting this binding right is a prerequisite.

### Test Setup Pattern

Use the same `beforeEach()` pattern from `tests/Feature/TraitTest.php`:

```php
uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Schema::hasTable(config('cancellation-tokens.table', 'cancellation_tokens'))) {
        $migration = include __DIR__.'/../../database/migrations/create_cancellation_tokens_table.php';
        $migration->up();
    }
    if (! Schema::hasTable('test_users')) {
        Schema::create('test_users', fn ($table) => $table->id());
    }
    if (! Schema::hasTable('test_bookings')) {
        Schema::create('test_bookings', fn ($table) => $table->id());
    }
});
```

### Existing Test Fixtures

- `Foxen\CancellationToken\Tests\Fixtures\TestUser` — uses `test_users` table, `$guarded = ['*']`, no timestamps
- `Foxen\CancellationToken\Tests\Fixtures\TestBooking` — uses `test_bookings` table, `$guarded = ['*']`, no timestamps, **uses `HasCancellationTokens` trait**

### Testing AC 4 (Container Binding)

```php
it('resolves the contract to the service implementation', function () {
    $resolved = app(CancellationTokenContract::class);

    expect($resolved)->toBeInstanceOf(CancellationTokenService::class);
});
```

Import `CancellationTokenContract` and `CancellationTokenService` directly — do not go through the Facade for this test.

### Testing Facade Delegation (AC 1-3)

Each Facade method should be tested the same way the underlying service is tested in `TokenCreationTest.php`, `TokenVerificationTest.php`, and `TokenConsumptionTest.php` — but calling via the Facade's static API instead of `app(CancellationTokenContract::class)->...`.

Example:

```php
// Instead of:
$plainToken = app(CancellationTokenContract::class)->create($booking, $user);

// Test via Facade:
$plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
```

### Previous Story Context (3.1)

From Story 3.1 completion:
- `HasCancellationTokens` trait implemented with `cancellationTokens()` morphMany and `createCancellationToken()` delegation
- `TestBooking` fixture now uses `HasCancellationTokens` trait
- `phpstan.neon.dist` updated to scan `tests/Fixtures`
- 85 total tests pass across all test files
- PHPStan and Pint pass clean

### Known Deferred Issues

- **TestCase never sets `app.key`** — all HMAC tests use an empty key (deferred from Story 2.3 review). Tests are internally consistent since both creation and verification use the same key.
- **TOCTOU race on consumption** — pre-existing architectural design limitation.
- **Timing oracle on DB token lookup** — spec prescribes this lookup strategy.

### Anti-Patterns to Avoid

1. **DO NOT** recreate the Facade or Service Provider — they already exist
2. **DO NOT** change `getFacadeAccessor()` to return anything other than `CancellationTokenContract::class`
3. **DO NOT** change `$this->app->bind()` to `$this->app->singleton()` — the service should be stateless
4. **DO NOT** import the Facade as `CancellationToken` and the Model as `CancellationToken` in the same file — use aliases
5. **DO NOT** use `===` or `==` to compare token hashes — always `hash_equals()`
6. **DO NOT** log, cache, or store the plain-text token at any point
7. **DO NOT** add `getPackageAliases()` to `TestCase.php` — auto-discovery handles it
8. **DO NOT** modify the `CancellationTokenContract` interface — signature is already defined

### Project Structure Notes

Only these files should be created or modified:

| File | Action |
|---|---|
| `tests/Feature/FacadeTest.php` | CREATE — feature tests for Facade delegation and container binding |

No changes to `src/` files are expected — the implementation already exists.

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/architecture.md#Integration Points & Data Flow]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.2]
- [Source: _bmad-output/project-context.md#Laravel / Package Rules — Facade]
- [Source: _bmad-output/project-context.md#Service Contract Anti-Patterns]
- [Source: _bmad-output/implementation-artifacts/3-1-hascancellationtokens-trait.md — previous story context]

## Dev Agent Record

### Agent Model Used

Claude GLM-5.1

### Debug Log References

### Completion Notes List

- Created `tests/Feature/FacadeTest.php` with 16 tests covering all 4 ACs
- All tests passed immediately — existing Facade and Service Provider implementation is correct
- Container binding (`CancellationTokenContract` → `CancellationTokenService`) verified via test
- Facade delegation for `create()`, `verify()`, `consume()` verified with positive and negative cases
- Exception reasons (Expired, Consumed, NotFound) verified on all error paths
- Architectural test confirms `getFacadeAccessor()` returns `CancellationTokenContract::class`
- No src/ changes needed — implementation was already correct from Stories 1.1 and 2.3
- 101 total tests pass (16 new + 85 existing), PHPStan clean, Pint clean

### File List

- `tests/Feature/FacadeTest.php` — CREATED — 16 feature tests for Facade delegation and container binding

## Change Log

- 2026-04-01: Story 3.2 complete — added FacadeTest with 16 tests verifying all 4 ACs. No source changes required.

### Review Findings

- [x] [Review][Patch] `ReflectionMethod::invoke(null)` on protected method without `setAccessible(true)` will throw `ReflectionException` on PHP 8.1+ [tests/Feature/FacadeTest.php:228-230]
- [x] [Review][Patch] `$token` from `->first()` not null-checked before `->expires_at` access — fatal error on null instead of test failure [tests/Feature/FacadeTest.php:55-59]
- [x] [Review][Patch] `now()->addSecond()` expiry window is too tight for slow CI — use `addSeconds(5)` [tests/Feature/FacadeTest.php:79-82,156-159]
- [x] [Review][Patch] Inconsistent fake token strings in paired NotFound tests — use same value for consistency [tests/Feature/FacadeTest.php:131,137]
- [x] [Review][Defer] Expiry `toDateTimeString()` comparison truncates to second-precision — may produce false failures on sub-second drift [tests/Feature/FacadeTest.php:59] — deferred, pre-existing pattern from TraitTest.php
- [x] [Review][Defer] `beforeEach` migration include path is hardcoded relative — rename/move breaks with unhelpful error [tests/Feature/FacadeTest.php:18-20] — deferred, pre-existing pattern
- [x] [Review][Defer] Migration `include` return not checked before `->up()` call — fatal TypeError if path wrong [tests/Feature/FacadeTest.php:18-20] — deferred, pre-existing pattern
- [x] [Review][Defer] No test for implementation swapping via `app()->bind()` override — deferred to Story 6.1 (CancellationTokenFake)
