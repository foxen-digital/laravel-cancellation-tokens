# Story 5.1: Prunable Token Cleanup

Status: done

## Story

As a developer,
I want expired and consumed tokens to be automatically removed by Laravel's `model:prune` command,
so that the `cancellation_tokens` table stays lean without any custom Artisan commands or manual cleanup logic.

## Acceptance Criteria

1. **Expired and consumed tokens are deleted** — Given `model:prune` is scheduled in the application, when `php artisan model:prune` is run (or `$model->pruneAll()` is called), then all tokens where `expires_at < now()` OR `used_at IS NOT NULL` are deleted

2. **Valid tokens are preserved** — Given a token that is neither expired nor consumed, when `php artisan model:prune` is run, then that token is not deleted

3. **Chunked deletion** — Given a large number of prunable tokens exist, when `php artisan model:prune` is run, then deletion is performed in chunks — no single bulk delete that could lock the table

4. **Prunable trait used** — Given the `CancellationToken` model is inspected, when an architecture check is run, then it uses Laravel's `Prunable` trait and defines a `prunable()` method — no custom Artisan command exists in the package

5. **Customisable pruning criteria** — Given a developer wants to customise pruning criteria, when they extend or override the model's `prunable()` method in their application, then the custom criteria are used instead of the package defaults

## Tasks / Subtasks

- [x] **Task 1: Verify and refine existing Prunable implementation** (AC: 1, 2, 4)
  - [x] 1.1 Confirm `src/Models/CancellationToken.php` already uses `Prunable` trait and implements `prunable()` correctly
  - [x] 1.2 Verify `prunable()` returns `static::where('expires_at', '<', now())->orWhereNotNull('used_at')` matching AR10 spec
  - [x] 1.3 Confirm no custom Artisan commands exist in the package

- [x] **Task 2: Write feature tests for pruning behaviour** (AC: 1, 2, 3, 5)
  - [x] 2.1 Create `tests/Feature/PrunableTest.php` with standard `beforeEach()` setup (migration + fixture tables)
  - [x] 2.2 Test: expired tokens are pruned — create token, set `expires_at` to past, call `pruneAll()`, assert deleted (AC 1)
  - [x] 2.3 Test: consumed tokens are pruned — create token, consume it, call `pruneAll()`, assert deleted (AC 1)
  - [x] 2.4 Test: valid (unexpired, unconsumed) tokens are NOT pruned — create token, call `pruneAll()`, assert survives (AC 2)
  - [x] 2.5 Test: token with future `expires_at` AND null `used_at` survives pruning (AC 2)
  - [x] 2.6 Test: token with null `expires_at` AND null `used_at` survives pruning (AC 2 — edge case)
  - [x] 2.7 Test: mixed scenario — expired + consumed + valid tokens coexist, `pruneAll()` deletes only expired/consumed, preserves valid (AC 1, 2)
  - [x] 2.8 Test: `pruneAll()` returns count of pruned records (AC 1)
  - [x] 2.9 Test: chunked deletion — create enough tokens to exceed default chunk size or verify `pruneAll()` calls with chunk parameter (AC 3)
  - [x] 2.10 Test: custom model subclass can override `prunable()` and its criteria are used (AC 5)

- [x] **Task 3: Run quality checks** (AC: all)
  - [x] 3.1 Run `composer test` — all tests pass (no regressions)
  - [x] 3.2 Run `composer analyse` — PHPStan passes
  - [x] 3.3 Run `composer format` — Pint passes

## Dev Notes

### CRITICAL: Prunable Implementation Already Exists

The `CancellationToken` model **already has** the `Prunable` trait and `prunable()` method from Story 2.2. This story is primarily about **testing** that implementation and verifying it meets all acceptance criteria.

Current model code (`src/Models/CancellationToken.php`):
```php
class CancellationToken extends Model
{
    use Prunable;

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now())
            ->orWhereNotNull('used_at');
    }
}
```

This is already correct per AR10: "Default `pruneQuery()` criteria: delete where `expires_at < now()` OR `used_at IS NOT NULL`."

**DO NOT rewrite or "improve" the existing model code unless a bug is found.**

### NEW FILES — Must Be Created

| File | Action |
|---|---|
| `tests/Feature/PrunableTest.php` | CREATE — feature tests for prunable behaviour |

### EXISTING FILES — Should NOT Be Modified

| File | Expected State |
|---|---|
| `src/Models/CancellationToken.php` | Already correct — has `use Prunable` and `prunable()` method |
| `src/CancellationTokenServiceProvider.php` | No changes needed |
| `src/CancellationTokenService.php` | No changes needed |

### How Laravel's Prunable Trait Works

The `Illuminate\Database\Eloquent\Prunable` trait:
1. Requires a `prunable()` method returning an Eloquent `Builder` query
2. `pruneAll(int $chunkSize = 1000)` fetches matching records in chunks and deletes each model individually (fires model events per delete)
3. `php artisan model:prune` discovers all models using `Prunable` and calls `pruneAll()` on each
4. Chunking is built-in — records are processed in batches of `$chunkSize` (default 1000), not in a single `DELETE WHERE` query

### Testing Approach

**Use `$model->pruneAll()` directly** in tests rather than running the Artisan command — this tests the actual pruning logic without needing to boot the full command kernel. `pruneAll()` returns the count of pruned records.

```php
it('prunes expired tokens', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // Create an expired token
    $plainToken = $service->create($booking, $user, now()->addHour());
    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    // Create a valid token
    $service->create($booking, $user, now()->addDay());

    expect(CancellationToken::count())->toBe(2);

    $pruned = CancellationToken::pruneAll();

    expect($pruned)->toBe(1);
    expect(CancellationToken::count())->toBe(1);
    expect(CancellationToken::first()->expires_at->isFuture())->toBeTrue();
});
```

### Testing Chunked Deletion (AC 3)

The default chunk size is 1000. Rather than creating 1000+ tokens (slow), test that `pruneAll()` accepts a chunk size parameter and verify via a small chunk size that it processes correctly:

```php
it('prunes tokens in chunks', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // Create 5 expired tokens
    for ($i = 0; $i < 5; $i++) {
        $plainToken = $service->create($booking, $user, now()->addHour());
        $token = CancellationToken::latest()->first();
        $token->expires_at = now()->subHour();
        $token->save();
    }

    expect(CancellationToken::count())->toBe(5);

    // Use chunk size of 2 to verify chunked processing
    $pruned = CancellationToken::pruneAll(2);

    expect($pruned)->toBe(5);
    expect(CancellationToken::count())->toBe(0);
});
```

### Testing Custom Prunable Override (AC 5)

Create an anonymous class extending `CancellationToken` that overrides `prunable()`:

```php
it('allows custom pruning criteria via model extension', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // Create an expired token
    $plainToken = $service->create($booking, $user, now()->addHour());
    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    // Custom model: only prune consumed tokens (NOT expired)
    $customModel = new class extends CancellationToken
    {
        public function prunable(): Builder
        {
            return static::whereNotNull('used_at');
        }
    };

    // Expired token should NOT be pruned by custom criteria
    $pruned = $customModel->pruneAll();
    expect($pruned)->toBe(0);
    expect(CancellationToken::count())->toBe(1);
});
```

### Test Setup Pattern

Follow the same `beforeEach()` pattern from existing feature tests:

```php
<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\Models\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Schema::hasTable(config('cancellation-tokens.table', 'cancellation_tokens'))) {
        $migration = include __DIR__.'/../../database/migrations/create_cancellation_tokens_table.php';
        $migration->up();
    }

    if (! Schema::hasTable('test_users')) {
        Schema::create('test_users', function ($table) {
            $table->id();
        });
    }

    if (! Schema::hasTable('test_bookings')) {
        Schema::create('test_bookings', function ($table) {
            $table->id();
        });
    }
});
```

### Epics Terminology Note: `pruneQuery()` vs `prunable()`

The epics file references `pruneQuery()` in AC 4 and AC 5, but Laravel's `Prunable` trait uses `prunable()` as the method name. The existing model correctly implements `prunable()`. This is a terminology mismatch in the epics file — the implementation is correct.

### Deferred Issues (Carry Forward)

From Story 4.1 and earlier stories, these deferred items are NOT in scope for this story:
- **No index on `expires_at` or `used_at`** — pruning queries will be full-table scans at scale. Address when performance becomes a concern.
- **`static::` in `prunable()` misdirects if model is subclassed** — `static::where(...)` queries the subclass table. Low risk currently.
- **Pruner deletes token between `verify()` and `save()` in `consume()`** — TOCTOU variant if model:prune runs during consumption window.

### Anti-Patterns to Avoid

1. **DO NOT** create a custom Artisan command for pruning — use `Prunable` trait + `model:prune` only
2. **DO NOT** modify the existing model's `prunable()` method unless a bug is found
3. **DO NOT** add soft deletes (`deleted_at`) to handle cleanup — `Prunable` does hard deletes
4. **DO NOT** use `DELETE FROM ... WHERE` raw SQL — let Eloquent handle deletion via the trait
5. **DO NOT** modify `CancellationTokenService`, `CancellationTokenContract`, or any other source file
6. **DO NOT** test the Artisan command directly — test `pruneAll()` on the model
7. **DO NOT** create massive token counts for chunk tests — use a small chunk size to prove chunking works

### Project Structure Notes

Only this file should be created:

| File | Action |
|---|---|
| `tests/Feature/PrunableTest.php` | CREATE |

No changes to any `src/` files expected.

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 5.1]
- [Source: _bmad-output/planning-artifacts/architecture.md#Data Architecture — Prunable]
- [Source: _bmad-output/planning-artifacts/architecture.md#AR10 — default pruneQuery criteria]
- [Source: _bmad-output/project-context.md#Eloquent Model — Prunable]
- [Source: _bmad-output/project-context.md#Development Workflow Rules — No custom Artisan commands]
- [Source: src/Models/CancellationToken.php — existing Prunable implementation]
- [Source: _bmad-output/implementation-artifacts/deferred-work.md — carried-forward items]

## Dev Agent Record

### Agent Model Used

Claude (GLM-5.1)

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- ✅ Verified existing Prunable implementation in CancellationToken model — trait and prunable() method already correct per spec
- ✅ Confirmed no custom Artisan commands exist in the package (no src/Console or src/Commands directories)
- ✅ Created tests/Feature/PrunableTest.php with 10 tests covering all 5 acceptance criteria
- ✅ Key test insight: create() deletes existing valid tokens for the same cancellable/tokenable pair, so tests use different users per token to avoid interference
- ✅ pruneAll() is an instance method (not static) — tests use (new CancellationToken)->pruneAll()
- ✅ Chunked deletion tested with chunk size of 2 on 5 tokens — proves chunking works without needing 1000+ records
- ✅ Custom prunable override tested via anonymous class extending CancellationToken
- ✅ All 125 tests pass (10 new + 115 existing), PHPStan clean, Pint formatted

### File List

| File | Action |
|---|---|
| `tests/Feature/PrunableTest.php` | CREATED — 10 feature tests for Prunable behaviour (AC 1–5) |

### Review Findings

- [x] [Review][Patch] Same user reused in "prunes expired tokens" — second `create()` call uses same `$user` and `$booking`, which may delete the first (now-expired) token via same-pair replacement logic before `pruneAll()` runs [tests/Feature/PrunableTest.php:35–43]
- [x] [Review][Patch] Mixed-scenario test queries by `tokenable_id` without `tokenable_type` constraint — polymorphic key collision possible if `TestUser` and `TestBooking` share ID sequences [tests/Feature/PrunableTest.php:128–148]
- [x] [Review][Patch] Missing AC1 edge case: no test for null-expiry consumed token (`expires_at = null`, `used_at` set) being pruned — the `orWhereNotNull('used_at')` arm covers it but it is untested [tests/Feature/PrunableTest.php]
- [x] [Review][Patch] "prunes consumed tokens" survivor assertion is weak — only checks `used_at` is null, not that the surviving token has the expected identity [tests/Feature/PrunableTest.php:73–74]
- [x] [Review][Defer] `beforeEach` lacks error handling for missing migration file — pre-existing pattern shared across test files [tests/Feature/PrunableTest.php:14–15] — deferred, pre-existing
- [x] [Review][Defer] `expires_at = now()` boundary condition not tested — not required by spec, strict `<` semantics are clear [tests/Feature/PrunableTest.php] — deferred, pre-existing
- [x] [Review][Defer] Token both expired AND consumed has no dedicated test — trivial OR semantics, covered implicitly by mixed-scenario test — deferred, pre-existing
- [x] [Review][Defer] No model event assertions (`pruning`/`pruned`) — out of scope for story 5.1, no AC for events — deferred, pre-existing
- [x] [Review][Defer] No test asserts "no custom Artisan command" — verified manually by dev, scope of story 6.3 architecture tests — deferred, pre-existing
- [x] [Review][Defer] AC3 chunk test proves the API parameter, not DB-level chunking — framework guarantee from Laravel's Prunable trait, no practical test exists without mocking the DB layer — deferred, pre-existing
- [x] [Review][Defer] `test_users`/`test_bookings` fixture tables have only `id` column — pre-existing pattern shared across all feature tests — deferred, pre-existing
