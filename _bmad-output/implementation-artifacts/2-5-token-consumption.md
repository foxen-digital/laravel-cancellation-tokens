# Story 2.5: Token Consumption

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a developer,
I want to consume a token,
so that it is permanently marked as used and cannot be verified or consumed again.

## Acceptance Criteria

1. **Happy path consumption** — Given a valid, unexpired, unconsumed token, when `CancellationTokenService::consume($plainToken)` is called, then the `CancellationToken` model's `used_at` column is set to the current timestamp and the model instance is returned

2. **Already-consumed rejection** — Given a token that has already been consumed (`used_at` is not null), when `consume()` is called, then a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Consumed`

3. **Expired rejection** — Given an expired token, when `consume()` is called, then a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Expired`

4. **Post-consumption verify fails** — Given a token that has been consumed, when `verify()` is subsequently called with the same plain-text token, then a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Consumed`

## Tasks / Subtasks

- [x] **Task 1: Write failing tests for consume()** (RED phase — AC: 1-4)
  - [x] 1.1 Create `tests/Feature/TokenConsumptionTest.php` with `uses(RefreshDatabase::class)` and `beforeEach()` setup (same pattern as `TokenVerificationTest.php`)
  - [x] 1.2 Test: valid token → `consume()` returns `CancellationToken` model with non-null `used_at` (AC 1)
  - [x] 1.3 Test: `used_at` is a Carbon instance set to approximately the current time (AC 1)
  - [x] 1.4 Test: consumed token → second `consume()` throws `TokenVerificationException` with `reason === Consumed` (AC 2)
  - [x] 1.5 Test: expired token → `consume()` throws `TokenVerificationException` with `reason === Expired` (AC 3)
  - [x] 1.6 Test: non-existent token → `consume()` throws `TokenVerificationException` with `reason === NotFound` (implicit from verify reuse)
  - [x] 1.7 Test: consumed token → `verify()` throws `TokenVerificationException` with `reason === Consumed` (AC 4)
  - [x] 1.8 Test: token that is both consumed AND expired → `consume()` throws `Consumed` (not `Expired`) — verifies check precedence
  - [x] 1.9 Confirm all new tests FAIL (consume returns `RuntimeException` stub)

- [x] **Task 2: Implement consume() method** (GREEN phase — AC: 1-4)
  - [x] 2.1 Replace `RuntimeException` stub in `CancellationTokenService::consume()` with implementation
  - [x] 2.2 Reuse `verify()` for token lookup and all validation checks (NotFound, Consumed, Expired)
  - [x] 2.3 Set `$token->used_at = now()` on the verified token model
  - [x] 2.4 Save the model via `$token->save()`
  - [x] 2.5 Return the `CancellationToken` model instance
  - [x] 2.6 Confirm all tests from Task 1 now pass

- [x] **Task 3: Refactor and quality checks** (REFACTOR phase — AC: all)
  - [x] 3.1 Verify code follows project conventions (no `===` on hashes, no plain-text persistence)
  - [x] 3.2 Run `composer test` — all tests pass (no regressions)
  - [x] 3.3 Run `composer analyse` — PHPStan passes
  - [x] 3.4 Run `composer format` — Pint passes

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation uses this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

### Implementation Strategy: Reuse verify()

The `consume()` method should delegate verification to `verify()` and then mark the token as consumed. This avoids duplicating the hash lookup, `hash_equals()` check, consumed check, and expired check:

```php
public function consume(string $plainToken): CancellationToken
{
    $token = $this->verify($plainToken);
    $token->used_at = now();
    $token->save();
    return $token;
}
```

This approach ensures:
- AC 2 (already-consumed rejection) is handled by `verify()` throwing `Consumed`
- AC 3 (expired rejection) is handled by `verify()` throwing `Expired`
- AC 4 (post-consumption verify fails) works because `used_at` is persisted — any subsequent `verify()` call sees a non-null `used_at` and throws `Consumed`
- Check precedence (NotFound → Consumed → Expired) is inherited from `verify()`

### Why Not Duplicate verify() Logic

Duplicating the verification logic in `consume()` would violate DRY and create a maintenance risk — any change to check precedence or validation logic would need to be applied in two places. The `verify()` method is the single source of truth for token validation.

### Model Update Pattern

The `CancellationToken` model uses `$guarded = ['*']` — use direct property assignment, never mass-assignment:

```php
$token->used_at = now();
$token->save();
```

### Check Precedence Order (Inherited from verify())

1. **NotFound** — no record found (must be first)
2. **Consumed** — `used_at` is not null (takes priority over Expired)
3. **Expired** — `expires_at` is in the past

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Feature tests use `RefreshDatabase` trait
- Test descriptions are lowercase sentences

### Test Setup Pattern

Use the same `beforeEach()` pattern from `tests/Feature/TokenVerificationTest.php`:

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

### Creating Test Tokens for Consumption Tests

Use the existing `CancellationTokenService::create()` to create valid tokens, then test consumption:

```php
// Valid token for happy path
$plainToken = $service->create($booking, $user);

// Expired token — modify directly
$token = CancellationToken::first();
$token->expires_at = now()->subHour();
$token->save();

// Consumed token — modify directly
$token = CancellationToken::first();
$token->used_at = now();
$token->save();
```

### Existing Test Fixtures

- `Foxen\CancellationToken\Tests\Fixtures\TestUser` — uses `test_users` table, `$guarded = ['*']`, no timestamps
- `Foxen\CancellationToken\Tests\Fixtures\TestBooking` — uses `test_bookings` table, `$guarded = ['*']`, no timestamps

### Previous Story Context (2.4)

From Story 2.4 completion:
- `verify()` implements: hash → DB lookup → Consumed check → Expired check → return model
- Check precedence: NotFound → Consumed → Expired
- Exception messages are generic: `'Token verification failed'` (indistinguishable externally)
- `hashToken()` is a private method using `hash_hmac('sha256', $plainToken, config('app.key'))`
- 66 tests currently pass across all test files (55 pre-existing + 9 from TokenVerificationTest + 2 from TokenVerificationExceptionTest)
- PHPStan and Pint pass clean

### Known Deferred Issues

- **TestCase never sets `app.key`** — all HMAC tests use an empty key (deferred from Story 2.3 review). Tests are internally consistent since both creation and verification use the same key.
- **`app.key` base64 prefix not stripped** — `config('app.key')` may return `base64:...` format; HMAC uses the raw string.
- **TOCTOU race on consumption** — a token could be consumed by a concurrent request between `verify()` and the `used_at` save. This is an inherent read-then-act design limitation, pre-existing from architecture.
- **Timing oracle on DB token lookup** — querying `WHERE token = $computedHash` leaks hit-vs-miss timing; the spec prescribes this lookup strategy.

### Anti-Patterns to Avoid

1. **DO NOT** duplicate the verify logic in consume — reuse `verify()`
2. **DO NOT** use `===` or `==` to compare token hashes — always `hash_equals()`
3. **DO NOT** use mass-assignment (`CancellationToken::where(...)->update(...)`) — model has `$guarded = ['*']`; use direct property assignment
4. **DO NOT** log, cache, or store the plain-text token at any point
5. **DO NOT** return `null` from `consume()` — always return model or throw exception
6. **DO NOT** modify the `CancellationTokenContract` interface — signature is already defined

### Project Structure Notes

Only these files should be modified or created:

| File | Action |
|---|---|
| `src/CancellationTokenService.php` | MODIFY — replace `consume()` stub with implementation |
| `tests/Feature/TokenConsumptionTest.php` | CREATE — feature tests for consumption |

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/architecture.md#Failure Handling Pattern]
- [Source: _bmad-output/planning-artifacts/architecture.md#Integration Points & Data Flow]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.5]
- [Source: _bmad-output/project-context.md#PHP Rules]
- [Source: _bmad-output/project-context.md#Security Anti-Patterns]
- [Source: _bmad-output/implementation-artifacts/2-4-token-verification.md — previous story context]

## Dev Agent Record

### Agent Model Used

GLM-5.1

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Implemented `consume()` by delegating to `verify()` for all validation, then setting `used_at` and saving — 3-line implementation
- `verify()` reuse ensures all failure paths (NotFound, Consumed, Expired) and check precedence are handled without duplication
- AC 4 (post-consumption verify fails) verified: `consume()` persists `used_at`, so subsequent `verify()` sees the consumed state
- 10 new tests covering all 4 ACs plus edge cases (both-consumed-and-expired, non-existent token)
- 76 total tests pass with zero regressions
- PHPStan and Pint both pass clean

### File List

- `src/CancellationTokenService.php` — MODIFIED (replaced `consume()` stub with 3-line implementation using `verify()` delegation)
- `tests/Feature/TokenConsumptionTest.php` — CREATED (10 tests covering AC 1-4 plus edge cases)

## Tasks / Review Findings

### Review Findings

- [x] [Review][Patch] Tests use `CancellationToken::first()` — fragile if any test adds a second token [tests/Feature/TokenConsumptionTest.php — multiple tests]
- [x] [Review][Patch] Test names say "with Consumed/Expired reason" but only assert exception class via `->throws()`, not the reason property [tests/Feature/TokenConsumptionTest.php:60, 92]
- [x] [Review][Defer] TOCTOU race condition in `consume()` — `verify()` read and `save()` are not atomic [src/CancellationTokenService.php:75-82] — deferred, pre-existing (explicitly documented in Known Deferred Issues)
- [x] [Review][Defer] SQL equality used for hash lookup in `verify()`, not `hash_equals()` [src/CancellationTokenService.php:58] — deferred, pre-existing (spec prescribes this lookup strategy)
- [x] [Review][Defer] Returned model is in-memory only — DB-persisted `used_at` may differ (precision/casting) [src/CancellationTokenService.php:77-81] — deferred, pre-existing Eloquent pattern
- [x] [Review][Defer] `app.key` empty/null silently accepted by `hash_hmac()` [src/CancellationTokenService.php:99] — deferred, pre-existing from Story 2.3 review
- [x] [Review][Defer] Pruner could delete token between `verify()` and `save()` inside `consume()` — deferred, pre-existing architectural TOCTOU variant

## Change Log

- 2026-04-01: Story 2.5 implemented — `consume()` method via `verify()` delegation, `used_at` persistence, and 10 feature tests
- 2026-04-01: Code review complete — 2 patch findings, 5 deferred (pre-existing), 3 dismissed
