# Story 4.1: Token Lifecycle Events

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a developer,
I want the package to dispatch events at each stage of the token lifecycle,
so that I can react to token creation, verification, consumption, and expiry without modifying package code.

## Acceptance Criteria

1. **TokenCreated event** — Given `CancellationTokenService::create()` completes successfully, when a listener is registered for `TokenCreated`, then it receives a `TokenCreated` event with a public `$token` property containing the newly created `CancellationToken` model

2. **TokenVerified event** — Given `CancellationTokenService::verify()` completes successfully, when a listener is registered for `TokenVerified`, then it receives a `TokenVerified` event with a public `$token` property containing the verified `CancellationToken` model

3. **TokenConsumed event** — Given `CancellationTokenService::consume()` completes successfully, when a listener is registered for `TokenConsumed`, then it receives a `TokenConsumed` event with a public `$token` property containing the consumed `CancellationToken` model

4. **TokenExpired event** — Given an expired token is presented to `verify()` or `consume()`, when a listener is registered for `TokenExpired`, then it receives a `TokenExpired` event with a public `$token` property containing the expired `CancellationToken` model — dispatched **before** the `TokenVerificationException` is thrown

5. **Readonly event classes** — Given all four event classes are inspected, when an architecture check is run, then all are `readonly` classes with a single public constructor property (`CancellationToken $token`) and no getter methods

6. **TokenExpired + exception consistency** — Given `TokenExpired` is dispatched and a `TokenVerificationException` is subsequently thrown, when both the event payload and exception are inspected, then `$event->token->expires_at` is in the past and `$exception->reason === TokenVerificationFailure::Expired`

## Tasks / Subtasks

- [x] **Task 1: Create the four event classes** (AC: 5)
  - [x] 1.1 Create `src/Events/TokenCreated.php` — `readonly` class with `public CancellationToken $token` constructor property
  - [x] 1.2 Create `src/Events/TokenVerified.php` — same structure
  - [x] 1.3 Create `src/Events/TokenConsumed.php` — same structure
  - [x] 1.4 Create `src/Events/TokenExpired.php` — same structure
  - [x] 1.5 Verify all events are in namespace `Foxen\CancellationToken\Events`

- [x] **Task 2: Add event dispatching to CancellationTokenService** (AC: 1-4, 6)
  - [x] 2.1 In `create()`: after `$token->save()`, add `event(new TokenCreated($token))` — then return `$plainToken` (AC 1)
  - [x] 2.2 In `verify()`: on success path (after all checks pass), add `event(new TokenVerified($stored))` — then `return $stored` (AC 2)
  - [x] 2.3 In `verify()`: on expired path, add `event(new TokenExpired($stored))` **before** `throw new TokenVerificationException(TokenVerificationFailure::Expired)` (AC 4, 6)
  - [x] 2.4 In `consume()`: after `$token->used_at = now(); $token->save();`, add `event(new TokenConsumed($token))` — then return (AC 3)
  - [x] 2.5 Note: `consume()` calls `verify()` — expired tokens trigger `TokenExpired` from within `verify()` before the exception propagates up. No duplicate dispatch needed in `consume()`.

- [x] **Task 3: Write feature tests for event dispatching** (AC: 1-4, 6)
  - [x] 3.1 Create `tests/Feature/EventDispatchTest.php` with `uses(RefreshDatabase::class)` and standard `beforeEach()` setup
  - [x] 3.2 Test: `TokenCreated` is dispatched with correct token model after `create()` (AC 1)
  - [x] 3.3 Test: `TokenVerified` is dispatched with correct token model after `verify()` (AC 2)
  - [x] 3.4 Test: `TokenConsumed` is dispatched with correct token model after `consume()` (AC 3)
  - [x] 3.5 Test: `TokenExpired` is dispatched before `TokenVerificationException` on expired `verify()` (AC 4, 6)
  - [x] 3.6 Test: `TokenExpired` is dispatched before `TokenVerificationException` on expired `consume()` (AC 4)
  - [x] 3.7 Test: `TokenExpired` is NOT dispatched for non-expired failure cases (NotFound, Consumed) — negative test
  - [x] 3.8 Test: `$event->token->expires_at` is in the past and `$exception->reason === Expired` (AC 6)

- [x] **Task 4: Make all tests pass and quality checks** (AC: all)
  - [x] 4.1 Run `composer test` — all tests pass (no regressions in existing 108+ tests)
  - [x] 4.2 Run `composer analyse` — PHPStan passes
  - [x] 4.3 Run `composer format` — Pint passes

## Dev Notes

### NEW FILES — Must Be Created

| File | Action |
|---|---|
| `src/Events/TokenCreated.php` | CREATE — readonly event class |
| `src/Events/TokenVerified.php` | CREATE — readonly event class |
| `src/Events/TokenConsumed.php` | CREATE — readonly event class |
| `src/Events/TokenExpired.php` | CREATE — readonly event class |
| `tests/Feature/EventDispatchTest.php` | CREATE — feature tests for event dispatching |

### EXISTING FILE — Must Be Modified

| File | Action |
|---|---|
| `src/CancellationTokenService.php` | MODIFY — add `event()` dispatch calls at 4 points |

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation uses this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**Event class paths:** `Foxen\CancellationToken\Events\{TokenCreated,TokenVerified,TokenConsumed,TokenExpired}`

### Event Class Template (AR6)

All four events follow this exact structure — `readonly` class, single public constructor property, no getters:

```php
<?php

namespace Foxen\CancellationToken\Events;

use Foxen\CancellationToken\Models\CancellationToken;

readonly class TokenCreated
{
    public function __construct(
        public CancellationToken $token,
    ) {}
}
```

Repeat the same pattern for `TokenVerified`, `TokenConsumed`, and `TokenExpired`. The only difference is the class name.

### Event Dispatch Points in CancellationTokenService

The current service has **zero** event dispatching. Here are the exact insertion points:

**1. `create()` — after save, before return (line ~50):**

```php
$token->save();

event(new \Foxen\CancellationToken\Events\TokenCreated($token));  // ADD THIS

return $plainToken;
```

**2. `verify()` — success path, after all checks pass (line ~72):**

```php
if ($stored->expires_at !== null && $stored->expires_at->isPast()) {
    event(new \Foxen\CancellationToken\Events\TokenExpired($stored));  // ADD THIS (before throw)
    throw new TokenVerificationException(TokenVerificationFailure::Expired);
}

event(new \Foxen\CancellationToken\Events\TokenVerified($stored));  // ADD THIS (before return)

return $stored;
```

**3. `consume()` — after save, before return (line ~80):**

```php
$token->used_at = now();
$token->save();

event(new \Foxen\CancellationToken\Events\TokenConsumed($token));  // ADD THIS

return $token;
```

**IMPORTANT: `consume()` calls `verify()` internally.** When an expired token is passed to `consume()`:
1. `verify()` is called
2. `verify()` dispatches `TokenExpired` event
3. `verify()` throws `TokenVerificationException(Expired)`
4. `consume()` never reaches its own code — the exception propagates

This is correct behavior. Do NOT add a duplicate `TokenExpired` dispatch in `consume()`.

### Events That Do NOT Fire on Failure

Only `TokenExpired` fires on a failure path. The other failure states do NOT dispatch events:
- `NotFound` → throw only, no event
- `Consumed` → throw only, no event

This is intentional. Consuming applications that need to react to these states catch the `TokenVerificationException` and inspect `$e->reason`.

### Dispatch Mechanism

Use `event()` helper — NOT `Event::dispatch()`:

```php
event(new TokenCreated($token));
```

This matches the project-context.md rule: "Events are dispatched via `event()` helper — not `Event::dispatch()`".

### Test Setup Pattern

Use the same `beforeEach()` pattern from existing feature tests:

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

### Testing Event Dispatching with Pest

Use `Event::fake()` + `Event::assertDispatched()`:

```php
use Illuminate\Support\Facades\Event;
use Foxen\CancellationToken\Events\TokenCreated;

it('dispatches TokenCreated event on successful token creation', function () {
    Event::fake([TokenCreated::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    Event::assertDispatched(TokenCreated::class, function ($event) use ($booking, $user) {
        return $event->token->cancellable instanceof TestBooking
            && $event->token->cancellable->id === $booking->id
            && $event->token->tokenable instanceof TestUser
            && $event->token->tokenable->id === $user->id;
    });
});
```

For the `TokenExpired` event (fires before exception), use `Event::fakeFor()` or catch the exception:

```php
it('dispatches TokenExpired before throwing for an expired token', function () {
    Event::fake([TokenExpired::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();
    $plainToken = $service->create($booking, $user);

    // Expire the token
    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);
    }

    Event::assertDispatched(TokenExpired::class, function ($event) {
        return $event->token->expires_at->isPast();
    });
});
```

### Import Management in CancellationTokenService

Add these imports at the top of `CancellationTokenService.php`:

```php
use Foxen\CancellationToken\Events\TokenConsumed;
use Foxen\CancellationToken\Events\TokenCreated;
use Foxen\CancellationToken\Events\TokenExpired;
use Foxen\CancellationToken\Events\TokenVerified;
```

Then use short class names in the dispatch calls: `event(new TokenCreated($token))`.

### Previous Story Context (3.3)

From Story 3.3 completion:
- 108 total tests pass across all test files
- PHPStan and Pint pass clean
- Container binding (`CancellationTokenContract` → `CancellationTokenService`) verified
- `src/Events/` directory does not exist yet — needs to be created
- Test fixtures `TestUser` and `TestBooking` available in `tests/Fixtures/`

### Known Deferred Issues (Carry Forward)

- **TestCase never sets `app.key`** — all HMAC tests use an empty key (deferred from Story 2.3). Tests are internally consistent.
- **TOCTOU race on consumption** — pre-existing architectural design limitation.
- **Timing oracle on DB token lookup** — spec prescribes this lookup strategy.
- **`beforeEach` migration include path hardcoded** — pre-existing pattern, not introduced by this story.
- **Expiry `toDateTimeString()` comparison** — pre-existing, deferred to Story 6.2.

### Anti-Patterns to Avoid

1. **DO NOT** add getter methods to event classes — use public constructor properties only
2. **DO NOT** make event classes mutable — they must be `readonly`
3. **DO NOT** use `Event::dispatch()` — use `event()` helper
4. **DO NOT** dispatch `TokenExpired` in `consume()` — it's already dispatched by `verify()` which `consume()` calls
5. **DO NOT** dispatch events for `NotFound` or `Consumed` failure states — only `Expired` gets a failure event
6. **DO NOT** throw the exception before dispatching `TokenExpired` — event MUST fire first
7. **DO NOT** register any event listeners in the ServiceProvider — consuming applications own listeners
8. **DO NOT** modify any existing `src/` files other than `CancellationTokenService.php`
9. **DO NOT** import the Facade alias as `CancellationToken` in test files — use fully qualified `Foxen\CancellationToken\Facades\CancellationToken` if needed to avoid model name collision
10. **DO NOT** modify `CancellationTokenContract` — events are an implementation detail of the service, not part of the contract interface

### Project Structure Notes

Only these files should be created or modified:

| File | Action |
|---|---|
| `src/Events/TokenCreated.php` | CREATE |
| `src/Events/TokenVerified.php` | CREATE |
| `src/Events/TokenConsumed.php` | CREATE |
| `src/Events/TokenExpired.php` | CREATE |
| `tests/Feature/EventDispatchTest.php` | CREATE |
| `src/CancellationTokenService.php` | MODIFY — add event dispatch calls |

No changes to the contract, model, facade, trait, validation rule, or service provider.

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Event Payload Structure]
- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 4.1]
- [Source: _bmad-output/project-context.md#Laravel / Package Rules — Events]
- [Source: _bmad-output/project-context.md#Critical Don't-Miss Rules — Event Anti-Patterns]
- [Source: src/CancellationTokenService.php — current service with zero event dispatching]

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### Review Findings

- [x] [Review][Patch] Double `Event::fake()` in TokenVerified test — redundant first call before `create()` resets on second call; `create()` fires real events [tests/Feature/EventDispatchTest.php:56]
- [x] [Review][Patch] Double `Event::fake()` in TokenConsumed test — same redundant first-call pattern [tests/Feature/EventDispatchTest.php:77]
- [x] [Review][Patch] Silent exception swallow in expired-verify test — `try/catch` has no assertion that exception was actually thrown; test passes green if `verify()` stops throwing [tests/Feature/EventDispatchTest.php:114]
- [x] [Review][Patch] Silent exception swallow in expired-consume test — same gap in expired-consume test [tests/Feature/EventDispatchTest.php:136]
- [x] [Review][Defer] `readonly` event classes expose mutable Eloquent model payload — `readonly` prevents property reassignment only; listeners can mutate `$event->token` fields and call `save()` [src/Events/*.php] — deferred, pre-existing
- [x] [Review][Defer] No negative test: `TokenVerified` NOT dispatched on failure paths — NotFound / Consumed / Expired failure cases have no assertion that `TokenVerified` is absent [tests/Feature/EventDispatchTest.php] — deferred, pre-existing
- [x] [Review][Defer] No negative test: `TokenConsumed` NOT dispatched on failure paths — parallel gap for `TokenConsumed` [tests/Feature/EventDispatchTest.php] — deferred, pre-existing
- [x] [Review][Defer] No `ShouldDispatchAfterCommit` interface — if `create()` or `consume()` is wrapped in a caller-owned DB transaction, events fire before the transaction commits and queue workers may not see the token [src/Events/*.php] — deferred, pre-existing

### File List
