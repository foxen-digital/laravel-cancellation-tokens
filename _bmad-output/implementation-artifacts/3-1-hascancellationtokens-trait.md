# Story 3.1: HasCancellationTokens Trait

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a developer,
I want to add the `HasCancellationTokens` trait to any Eloquent model,
so that I can create and retrieve cancellation tokens directly on cancellable model instances.

## Acceptance Criteria

1. **Trait-based token creation** — Given a model uses the `HasCancellationTokens` trait, when `$model->createCancellationToken(tokenable: $actor)` is called with or without an explicit `expiresAt`, then a new token is created via the service and the plain-text token string is returned

2. **cancellationTokens relationship** — Given a model uses the trait, when `$model->cancellationTokens()` is called, then it returns an Eloquent `morphMany` relationship of all `CancellationToken` records where this model is the `cancellable`

3. **Strictly opt-in** — Given any Eloquent model class that does NOT use the trait, when the application bootstraps, then no side effects are introduced on that model — the trait is strictly opt-in

4. **Non-User tokenable support** — Given the tokenable actor is any Eloquent model (not just `User`), when `createCancellationToken(tokenable: $nonUserModel, expiresAt: ...)` is called, then the token is created successfully with the non-User model as the actor

## Tasks / Subtasks

- [x] **Task 1: Write failing tests for HasCancellationTokens trait** (RED phase — AC: 1-4)
  - [x] 1.1 Create `tests/Feature/TraitTest.php` with `uses(RefreshDatabase::class)` and `beforeEach()` setup (same pattern as `TokenConsumptionTest.php`)
  - [x] 1.2 Test: `createCancellationToken(tokenable: $actor)` on a model using the trait returns a plain-text token string starting with the configured prefix (AC 1)
  - [x] 1.3 Test: calling `createCancellationToken` with explicit `expiresAt` creates a token with that expiry (AC 1)
  - [x] 1.4 Test: calling `createCancellationToken` without `expiresAt` creates a token with default expiry from config (AC 1)
  - [x] 1.5 Test: `cancellationTokens()` relationship returns all `CancellationToken` records where this model is the `cancellable` (AC 2)
  - [x] 1.6 Test: a model without the trait has no `createCancellationToken` or `cancellationTokens` methods available (AC 3)
  - [x] 1.7 Test: using a non-User model as the tokenable creates a token with the correct `tokenable_type` and `tokenable_id` (AC 4)
  - [x] 1.8 Test: creating a token via trait persists correct `cancellable_type/id` on the token record (AC 1)
  - [x] 1.9 Confirm all new tests FAIL (trait does not exist yet)

- [x] **Task 2: Implement HasCancellationTokens trait** (GREEN phase — AC: 1-4)
  - [x] 2.1 Create `src/Traits/HasCancellationTokens.php` in the `Foxen\CancellationToken\Traits` namespace
  - [x] 2.2 Implement `cancellationTokens(): morphMany` relationship — `return $this->morphMany(CancellationToken::class, 'cancellable');`
  - [x] 2.3 Implement `createCancellationToken(Model $tokenable, ?Carbon $expiresAt = null): string` — resolve `CancellationTokenContract` from the container and delegate to `create()`
  - [x] 2.4 Confirm all tests from Task 1 now pass

- [x] **Task 3: Refactor and quality checks** (REFACTOR phase — AC: all)
  - [x] 3.1 Verify code follows project conventions (namespace, directory, no `===` on hashes, no plain-text persistence)
  - [x] 3.2 Run `composer test` — all tests pass (no regressions)
  - [x] 3.3 Run `composer analyse` — PHPStan passes
  - [x] 3.4 Run `composer format` — Pint passes

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation uses this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**File location:** `src/Traits/HasCancellationTokens.php` — namespace `Foxen\CancellationToken\Traits`

### Implementation Strategy

The trait is a thin convenience layer. It must NOT contain any business logic — all token creation, hashing, and verification happens in `CancellationTokenService`. The trait does two things:

1. **`cancellationTokens()` relationship** — standard `morphMany` pointing to `CancellationToken::class` with morph name `'cancellable'`
2. **`createCancellationToken()` method** — resolves `CancellationTokenContract` from the container and delegates to `create()`

```php
namespace Foxen\CancellationToken\Traits;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCancellationTokens
{
    public function cancellationTokens(): MorphMany
    {
        return $this->morphMany(CancellationToken::class, 'cancellable');
    }

    public function createCancellationToken(Model $tokenable, ?Carbon $expiresAt = null): string
    {
        return app(CancellationTokenContract::class)->create($this, $tokenable, $expiresAt);
    }
}
```

### Why Resolve from Container

The trait resolves `CancellationTokenContract` via `app()` instead of injecting `CancellationTokenService` directly. This ensures:
- The Facade swap (`CancellationToken::fake()`) works — tests can substitute the fake without modifying the trait
- The dependency is on the contract, not the concrete implementation (NFR19, architecture enforcement rule)
- No constructor injection needed — traits cannot define constructor dependencies in PHP

### Relationship Direction

The trait goes on the **cancellable** model (the subject being cancelled — e.g. `Booking`). The `morphMany` relationship queries `CancellationToken` where `cancellable_type = Booking::class` and `cancellable_id = $booking->id`.

The `tokenable` (actor) is passed as an argument — it is NOT a relationship on the cancellable model.

### Model Import in Trait

The trait imports `CancellationToken` model and `CancellationTokenContract`. Both already exist:
- `Foxen\CancellationToken\Models\CancellationToken` — Epic 2, Story 2.2
- `Foxen\CancellationToken\Contracts\CancellationTokenContract` — Epic 2, Story 2.1

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Feature tests use `RefreshDatabase` trait
- Test descriptions are lowercase sentences

### Test Setup Pattern

Use the same `beforeEach()` pattern from `tests/Feature/TokenConsumptionTest.php`:

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
- `Foxen\CancellationToken\Tests\Fixtures\TestBooking` — uses `test_bookings` table, `$guarded = ['*']`, no timestamps

These fixtures are available for this story. `TestBooking` should use the `HasCancellationTokens` trait in tests (it is the cancellable model). `TestUser` is the tokenable actor (no trait needed).

### Testing AC 3 (Strictly Opt-In)

Test that a model without the trait does not have `createCancellationToken` or `cancellationTokens` methods. Use `method_exists()` or `expect(method_exists($model, 'createCancellationToken'))->toBeFalse()`. Do NOT add the trait to `TestUser` for this test — use a plain Eloquent model or `TestUser` directly.

### Previous Story Context (2.5)

From Story 2.5 completion:
- `consume()` delegates to `verify()` then sets `used_at` and saves — 3-line implementation
- `verify()` check precedence: NotFound → Consumed → Expired
- `CancellationToken` model has `$guarded = ['*']` — use direct property assignment, never mass-assignment
- 76 total tests currently pass across all test files
- PHPStan and Pint pass clean

### Known Deferred Issues

- **TestCase never sets `app.key`** — all HMAC tests use an empty key (deferred from Story 2.3 review). Tests are internally consistent since both creation and verification use the same key.
- **TOCTOU race on consumption** — pre-existing architectural design limitation.
- **Timing oracle on DB token lookup** — spec prescribes this lookup strategy.

### Anti-Patterns to Avoid

1. **DO NOT** put business logic in the trait — delegate to `CancellationTokenContract`
2. **DO NOT** inject `CancellationTokenService` directly — use `app(CancellationTokenContract::class)`
3. **DO NOT** add a constructor to the trait — PHP traits cannot define constructor dependencies reliably
4. **DO NOT** use `===` or `==` to compare token hashes — always `hash_equals()`
5. **DO NOT** log, cache, or store the plain-text token at any point
6. **DO NOT** add the trait to `CancellationToken` model itself — the trait goes on cancellable models
7. **DO NOT** create a `tokenable` relationship method on the cancellable model — the tokenable is accessed via `$token->tokenable`, not through the trait
8. **DO NOT** modify the `CancellationTokenContract` interface — signature is already defined

### Project Structure Notes

Only these files should be modified or created:

| File | Action |
|---|---|
| `src/Traits/HasCancellationTokens.php` | CREATE — trait with `cancellationTokens()` relationship and `createCancellationToken()` method |
| `tests/Feature/TraitTest.php` | CREATE — feature tests for the trait |

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Namespace / Directory Structure]
- [Source: _bmad-output/planning-artifacts/architecture.md#Requirements to Structure Mapping — Model Trait]
- [Source: _bmad-output/planning-artifacts/architecture.md#Integration Points & Data Flow]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.1]
- [Source: _bmad-output/project-context.md#PHP Rules]
- [Source: _bmad-output/project-context.md#Security Anti-Patterns]
- [Source: _bmad-output/implementation-artifacts/2-5-token-consumption.md — previous story context]

## Dev Agent Record

### Agent Model Used

GLM-5.1

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Implemented `HasCancellationTokens` trait with two methods: `cancellationTokens()` morphMany relationship and `createCancellationToken()` delegating to the service via container
- Trait resolves `CancellationTokenContract` via `app()` to ensure Facade swap works in tests
- `TestBooking` fixture updated to use the trait (cancellable model)
- Added `tests/Fixtures` to PHPStan scan paths so the trait is recognized as used
- 8 new tests covering all 4 ACs: token creation (with/without expiry, prefix check), morphMany relationship, opt-in enforcement, non-User tokenable, correct cancellable type/id
- 84 total tests pass with zero regressions
- PHPStan and Pint both pass clean

### File List

- `src/Traits/HasCancellationTokens.php` — CREATED (trait with `cancellationTokens()` relationship and `createCancellationToken()` method)
- `tests/Feature/TraitTest.php` — CREATED (8 tests covering AC 1-4)
- `tests/Fixtures/TestBooking.php` — MODIFIED (added `HasCancellationTokens` trait import and usage)
- `phpstan.neon.dist` — MODIFIED (added `tests/Fixtures` to scan paths for trait usage detection)

### Review Findings

- [x] [Review][Patch] `$plainToken` unused in 3 tests — AC 1 return value unverified in expiry/non-User tests [tests/Feature/TraitTest.php:42,53,101]
- [x] [Review][Patch] `CancellationToken::first()` lacks cancellable scope — implicit ownership assertion [tests/Feature/TraitTest.php:44,55,103,115]
- [x] [Review][Patch] `toDateString()` precision loss — sub-day bug undetected, midnight race theoretical [tests/Feature/TraitTest.php:45,57]
- [x] [Review][Patch] Unused `use Illuminate\Database\Eloquent\Model` import [tests/Feature/TraitTest.php:6]
- [x] [Review][Patch] AC 3 opt-in test writes to DB unnecessarily — `new TestUser()` suffices [tests/Feature/TraitTest.php:90]
- [x] [Review][Patch] AC 2 gap: `cancellationTokens()` method never called — only dynamic property tested [tests/Feature/TraitTest.php:69,84]
- [x] [Review][Defer] Trait has no host type constraint — non-Model usage fatals silently (PHP language limitation) [src/Traits/HasCancellationTokens.php] — deferred, pre-existing
- [x] [Review][Defer] Morph map interference — `cancellable_type` assertion breaks if `Relation::morphMap()` configured [tests/Feature/TraitTest.php:104,116] — deferred, pre-existing

## Change Log

- 2026-04-01: Story 3.1 implemented — `HasCancellationTokens` trait with `cancellationTokens()` morphMany and `createCancellationToken()` delegation, 8 feature tests
