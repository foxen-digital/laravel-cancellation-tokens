# Story 6.1: CancellationTokenFake

Status: done

## Story

As a developer,
I want an in-memory test double for the token service,
so that I can write fast unit tests without a database and assert token interactions with precision.

## Acceptance Criteria

1. **Container substitution** — Given `CancellationToken::fake()` is called in a test, when the service container resolves `CancellationTokenContract`, then it returns the `CancellationTokenFake` instance instead of `CancellationTokenService`, without any changes to production code

2. **assertTokenCreatedFor with cancellable only** — Given `CancellationTokenFake` is active and `create()` is called, when `CancellationToken::assertTokenCreatedFor($cancellable)` is called, then the assertion passes if a token was created for that cancellable model

3. **assertTokenCreatedFor with cancellable + tokenable** — Given `CancellationTokenFake` is active and `create()` is called with a specific tokenable, when `CancellationToken::assertTokenCreatedFor($cancellable, $tokenable)` is called, then the assertion passes only if both the cancellable and tokenable match

4. **assertTokenConsumed** — Given `CancellationTokenFake` is active and `consume()` is called, when `CancellationToken::assertTokenConsumed()` is called, then the assertion passes

5. **assertTokenNotConsumed** — Given `CancellationTokenFake` is active and `consume()` has not been called, when `CancellationToken::assertTokenNotConsumed()` is called, then the assertion passes

6. **assertNoTokensCreated** — Given `CancellationTokenFake` is active and no tokens have been created, when `CancellationToken::assertNoTokensCreated()` is called, then the assertion passes

7. **Architecture compliance** — Given `CancellationTokenFake` is inspected, when an architecture check is run, then it lives in `src/Testing/CancellationTokenFake.php`, implements `CancellationTokenContract`, and is never loaded outside of test environments

## Tasks / Subtasks

- [x] **Task 1: Create CancellationTokenFake** (AC: 1, 2, 3, 4, 5, 6, 7)
  - [x] 1.1 Create `src/Testing/` directory
  - [x] 1.2 Create `src/Testing/CancellationTokenFake.php` implementing `CancellationTokenContract`
  - [x] 1.3 Implement `create()` — store cancellable/tokenable pair in internal array, generate `ct_fake_` prefixed token, return it
  - [x] 1.4 Implement `verify()` — look up token in internal store, return unsaved `CancellationToken` model or throw `TokenVerificationException(NotFound)`
  - [x] 1.5 Implement `consume()` — look up token, mark consumed in store, return unsaved model or throw
  - [x] 1.6 Implement assertion methods: `assertTokenCreatedFor()`, `assertTokenConsumed()`, `assertTokenNotConsumed()`, `assertNoTokensCreated()`

- [x] **Task 2: Add Facade fake() method and assertion proxies** (AC: 1, 2, 3, 4, 5, 6)
  - [x] 2.1 Add `static fake()` method to `src/Facades/CancellationToken.php` — creates `CancellationTokenFake`, swaps container binding via `app()->instance(CancellationTokenContract::class, $fake)`
  - [x] 2.2 Add static assertion proxy methods that delegate to the resolved fake instance

- [x] **Task 3: Write unit tests** (AC: all)
  - [x] 3.1 Create `tests/Unit/FakeTest.php` — NO database access, NO `RefreshDatabase`
  - [x] 3.2 Test `fake()` swaps container binding (AC 1)
  - [x] 3.3 Test `assertTokenCreatedFor` with cancellable only (AC 2)
  - [x] 3.4 Test `assertTokenCreatedFor` with cancellable + tokenable (AC 3)
  - [x] 3.5 Test `assertTokenCreatedFor` fails when no token created for that cancellable (AC 2 negative)
  - [x] 3.6 Test `assertTokenConsumed` passes after consume call (AC 4)
  - [x] 3.7 Test `assertTokenNotConsumed` passes when consume not called (AC 5)
  - [x] 3.8 Test `assertNoTokensCreated` passes when no tokens created (AC 6)
  - [x] 3.9 Test `assertNoTokensCreated` fails when tokens were created (AC 6 negative)
  - [x] 3.10 Test `fake()` returns a new instance each call (clean state)

- [x] **Task 4: Run quality checks** (AC: all)
  - [x] 4.1 Run `composer test` — all tests pass (no regressions)
  - [x] 4.2 Run `composer analyse` — PHPStan passes
  - [x] 4.3 Run `composer format` — Pint passes

## Dev Notes

### CRITICAL: Files to Create and Modify

| File | Action |
|---|---|
| `src/Testing/CancellationTokenFake.php` | CREATE — in-memory test double |
| `src/Facades/CancellationToken.php` | MODIFY — add `fake()` + assertion proxies |
| `tests/Unit/FakeTest.php` | CREATE — unit tests, NO database |

### Existing Facade — Current State

The Facade currently only has `getFacadeAccessor()`:

```php
// src/Facades/CancellationToken.php
class CancellationToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CancellationTokenContract::class;
    }
}
```

You must ADD the following methods WITHOUT removing or changing the existing `getFacadeAccessor()`:

```php
public static function fake(): CancellationTokenFake
{
    $fake = new CancellationTokenFake;
    app()->instance(CancellationTokenContract::class, $fake);
    return $fake;
}

public static function assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null): void
{
    static::getFacadeRoot()->assertTokenCreatedFor($cancellable, $tokenable);
}

public static function assertTokenConsumed(): void
{
    static::getFacadeRoot()->assertTokenConsumed();
}

public static function assertTokenNotConsumed(): void
{
    static::getFacadeRoot()->assertTokenNotConsumed();
}

public static function assertNoTokensCreated(): void
{
    static::getFacadeRoot()->assertNoTokensCreated();
}
```

### CancellationTokenFake Implementation Guide

The Fake must implement `CancellationTokenContract` (same interface as `CancellationTokenService`). Internal state tracking:

```php
class CancellationTokenFake implements CancellationTokenContract
{
    protected array $createdTokens = [];
    protected bool $consumed = false;

    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string
    {
        $plainToken = 'ct_fake_'.Str::random(32);

        $this->createdTokens[] = [
            'cancellable' => $cancellable,
            'tokenable' => $tokenable,
            'expiresAt' => $expiresAt,
            'plainToken' => $plainToken,
            'consumed' => false,
        ];

        return $plainToken;
    }

    public function verify(string $plainToken): CancellationToken
    {
        // Find in internal store, return unsaved model or throw NotFound
    }

    public function consume(string $plainToken): CancellationToken
    {
        // Find, mark consumed, set $this->consumed = true, return unsaved model
    }
}
```

### Key Design Decisions

1. **No event dispatching in the Fake** — The architecture doc explicitly states: "Expired token assertions are tested via `Event::fake()` + `Event::assertDispatched(TokenExpired::class)` in feature tests." The Fake does NOT dispatch events. It is purely for creation/consumption assertions.

2. **Unsaved model instances** — `verify()` and `consume()` must return `Foxen\CancellationToken\Models\CancellationToken` instances to satisfy the contract return type. Create unsaved model instances (do not call `save()`):

```php
$token = new \Foxen\CancellationToken\Models\CancellationToken;
$token->cancellable_type = $cancellable::class;
$token->cancellable_id = $cancellable->getKey();
$token->tokenable_type = $tokenable::class;
$token->tokenable_id = $tokenable->getKey();
$token->expires_at = $expiresAt;
$token->used_at = null; // or now() for consumed
// DO NOT call $token->save()
```

3. **Assertion failures use PHPUnit `ExpectationFailedException`** — Use `PHPUnit\Framework\ExpectationFailedException` (or Pest's built-in expectation failures) when assertions fail. The standard pattern is:

```php
use PHPUnit\Framework\ExpectationFailedException;

public function assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null): void
{
    $found = collect($this->createdTokens)->contains(function ($record) use ($cancellable, $tokenable) {
        $matchesCancellable = $record['cancellable']::class === $cancellable::class
            && $record['cancellable']->getKey() === $cancellable->getKey();

        if ($tokenable === null) {
            return $matchesCancellable;
        }

        return $matchesCancellable
            && $record['tokenable']::class === $tokenable::class
            && $record['tokenable']->getKey() === $tokenable->getKey();
    });

    if (! $found) {
        throw new ExpectationFailedException(
            $tokenable
                ? 'Expected a token to be created for the given cancellable and tokenable.'
                : 'Expected a token to be created for the given cancellable.'
        );
    }
}
```

4. **`consume()` tracking** — The Fake needs to know which specific tokens were consumed (not just a boolean) so that assertions work correctly. Consider using an index/key in the `createdTokens` array to mark individual tokens as consumed.

### Unit Test Pattern — NO DATABASE

`tests/Unit/FakeTest.php` must NOT:
- Use `RefreshDatabase`
- Touch the database in any way
- Use `beforeEach()` to create migration/table fixtures

Unit tests instantiate models without persisting them:

```php
<?php

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Foxen\CancellationToken\Testing\CancellationTokenFake;

it('swaps the container binding to CancellationTokenFake', function () {
    CancellationToken::fake();

    $resolved = app(CancellationTokenContract::class);

    expect($resolved)->toBeInstanceOf(CancellationTokenFake::class);
});

it('asserts a token was created for a cancellable', function () {
    CancellationToken::fake();

    // Create unpersisted model instances (no DB needed)
    $booking = new TestBooking;
    $booking->id = 1;
    // OR if TestBooking needs a table, use anonymous models:

    $user = new TestUser;
    $user->id = 1;

    CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertTokenCreatedFor($booking);
});
```

**IMPORTANT:** `TestUser` and `TestBooking` extend `Model` but have no DB table in unit tests. To set IDs on unsaved models, use:

```php
$booking = new TestBooking;
$booking->forceFill(['id' => 1]); // or just $booking->id = 1
```

The existing fixtures (`tests/Fixtures/TestUser.php`, `tests/Fixtures/TestBooking.php`) have `$guarded = ['*']` so use `$model->id = 1` or `$model->forceFill(['id' => 1])`.

### Namespace and Imports

```php
// src/Testing/CancellationTokenFake.php
namespace Foxen\CancellationToken\Testing;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\ExpectationFailedException;
```

```php
// tests/Unit/FakeTest.php
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Testing\CancellationTokenFake;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
```

### How Container Binding Swap Works

The service provider currently does:

```php
// In packageBooted()
$this->app->bind(CancellationTokenContract::class, CancellationTokenService::class);
```

`CancellationToken::fake()` overrides this by calling:

```php
app()->instance(CancellationTokenContract::class, $fake);
```

`instance()` sets a concrete binding that takes priority over the `bind()` registration. After calling `fake()`, any resolution of `CancellationTokenContract` returns the `CancellationTokenFake` instance.

### Service Provider — NO CHANGES NEEDED

`CancellationTokenServiceProvider` does NOT need modification. The `fake()` method on the Facade handles the binding swap directly.

### Architecture: Location in `src/Testing/`

Per AR14: `CancellationTokenFake` lives in `src/Testing/` (not `tests/`) so consuming app test suites can require it via `require-dev`. This directory does NOT exist yet — create it.

### Anti-Patterns to Avoid

1. **DO NOT** use `RefreshDatabase` or any database access in unit tests
2. **DO NOT** modify `CancellationTokenService`, `CancellationTokenContract`, or `CancellationTokenServiceProvider`
3. **DO NOT** dispatch events in the Fake — events are tested via `Event::fake()` in feature tests
4. **DO NOT** persist model instances in the Fake — create unsaved in-memory models
5. **DO NOT** use `test()` syntax — use `it()` in all Pest tests
6. **DO NOT** use `assertX()` PHPUnit assertions in tests — use Pest `expect()`
7. **DO NOT** remove or change the existing `getFacadeAccessor()` method on the Facade

### Deferred Items (Carry Forward)

From deferred-work.md, these are NOT in scope:
- **No implementation-swapping test** (deferred from 3-2) — THIS story addresses it
- **`TestCase` never sets `app.key`** — tests run against empty key; not this story's concern
- **All other deferred items** — remain deferred

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.1]
- [Source: _bmad-output/planning-artifacts/architecture.md#CancellationTokenFake — Gap 1]
- [Source: _bmad-output/planning-artifacts/architecture.md#AR8 — assertion API]
- [Source: _bmad-output/planning-artifacts/architecture.md#AR14 — src/Testing/ location]
- [Source: _bmad-output/planning-artifacts/architecture.md#AR19 — implements same interface]
- [Source: _bmad-output/project-context.md#CancellationTokenFake Assertion API]
- [Source: src/Contracts/CancellationTokenContract.php — interface to implement]
- [Source: src/Facades/CancellationToken.php — Facade to add fake() to]
- [Source: src/CancellationTokenServiceProvider.php — binding pattern]
- [Source: tests/Feature/FacadeTest.php — existing test pattern reference]
- [Source: _bmad-output/implementation-artifacts/deferred-work.md — carried-forward items]

### Review Findings

- [x] [Review][Decision] Production autoload/facade import of CancellationTokenFake — accepted: keep `fake()` on the Facade; Laravel convention takes precedence. `src/Testing/` remains in production autoload.
- [x] [Review][Decision] `assertTokenConsumed`/`assertTokenNotConsumed` scoping — resolved: changed signatures to `assertTokenConsumed(string $plainToken)` and `assertTokenNotConsumed(string $plainToken)` for token-specific, unambiguous assertions.
- [x] [Review][Patch] `fake()` does not call `clearResolvedInstance()` before returning [src/Facades/CancellationToken.php:19]
- [x] [Review][Patch] Facade assert* proxy methods delegate to `getFacadeRoot()` with no guard that the root is a `CancellationTokenFake` — calling before `fake()` causes fatal `BadMethodCallException` [src/Facades/CancellationToken.php:24]
- [x] [Review][Patch] `assertTokenCreatedFor` false-positive when `getKey()` returns null — two different unsaved model instances of the same class both return `null === null`, producing a spurious match [src/Testing/CancellationTokenFake.php:75]
- [x] [Review][Patch] Assertion failures construct `ExpectationFailedException` directly instead of using `PHPUnit\Framework\Assert::fail()` — skips the `$comparisonFailure` second arg, suppresses expected/actual diffs in test output [src/Testing/CancellationTokenFake.php:87]
- [x] [Review][Patch] Missing negative test: `assertTokenConsumed` should throw when no token has been consumed [tests/Unit/FakeTest.php]
- [x] [Review][Patch] `expect(true)->toBeTrue()` is a vacuous no-op used in every happy-path test — adds no assertion value and obscures intent [tests/Unit/FakeTest.php]
- [x] [Review][Patch] Missing negative test: `assertTokenCreatedFor($cancellable, $tokenable)` should fail when cancellable matches but tokenable does not (AC 3 negative path) [tests/Unit/FakeTest.php]
- [x] [Review][Defer] `makeModel()` does not set `token` or `id` attributes — expected fake fidelity gap; callers reading those fields will get null [src/Testing/CancellationTokenFake.php:118] — deferred, pre-existing
- [x] [Review][Defer] `expiresAt` exact boundary — `isPast()` returns false at the exact millisecond of expiry, meaning a token that expired "right now" passes verification [src/Testing/CancellationTokenFake.php:41] — deferred, pre-existing Carbon behavior shared with real implementation
- [x] [Review][Defer] `fake()` does not accept pre-seeded tokens or pre-configured state — limits testing of error paths without manual setup [src/Facades/CancellationToken.php:19] — deferred, not in spec scope
- [x] [Review][Defer] No `assertTokenVerified` tracking — `verify()` calls are not recorded, cannot assert verification occurred without consumption [src/Testing/CancellationTokenFake.php] — deferred, not in spec scope

## Dev Agent Record

### Agent Model Used

Claude (glm-5.1)

### Debug Log References

### Completion Notes List

- Created CancellationTokenFake in-memory test double implementing CancellationTokenContract with create/verify/consume and 4 assertion methods
- Added fake() static method and assertion proxy methods to the CancellationToken Facade
- Created 9 unit tests covering all acceptance criteria (AC 1-6) with no database access
- All 135 tests pass, PHPStan clean, Pint formatting clean
- Pint auto-fixed: yoda_style, unary_operator_spaces, not_operator_with_space in Fake; fully_qualified_strict_types in test

### File List

- `src/Testing/CancellationTokenFake.php` — CREATED — in-memory test double
- `src/Facades/CancellationToken.php` — MODIFIED — added fake() + assertion proxies
- `tests/Unit/FakeTest.php` — CREATED — 9 unit tests, no database
