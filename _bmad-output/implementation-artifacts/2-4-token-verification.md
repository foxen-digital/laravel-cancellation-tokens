# Story 2.4: Token Verification

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a developer,
I want to verify a plain-text token string,
so that I can confirm it exists, has not expired, and has not been consumed — and retrieve its associated models.

## Acceptance Criteria

1. **Valid token verification** — Given a valid, unexpired, unconsumed token, when `CancellationTokenService::verify($plainToken)` is called, then the `CancellationToken` model instance is returned with `tokenable` and `cancellable` relationships accessible

2. **Not-found failure** — Given a token string that does not match any stored hash, when `verify()` is called, then a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::NotFound`

3. **Expired failure** — Given a token whose `expires_at` is in the past, when `verify()` is called, then a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Expired`

4. **Consumed failure** — Given a token whose `used_at` is not null, when `verify()` is called, then a `TokenVerificationException` is thrown with `$reason === TokenVerificationFailure::Consumed`

5. **Timing-safe comparison** — Given any verification attempt, when the hash comparison is performed, then `hash_equals()` is used — direct `===` comparison of token strings is never used

6. **Indistinguishable external responses** — Given a non-existent token and an expired token presented for verification, when the exception messages or response codes are compared externally, then they are indistinguishable — failure reasons are available on the exception for internal use only, not surfaced to HTTP responses automatically

## Tasks / Subtasks

- [x] **Task 1: Write failing tests for verify()** (RED phase — AC: 1-6)
  - [x] 1.1 Create `tests/Feature/TokenVerificationTest.php` with `uses(RefreshDatabase::class)` and `beforeEach()` setup (migration + fixture tables, same pattern as `TokenCreationTest.php`)
  - [x] 1.2 Test: valid token returns `CancellationToken` model with accessible `tokenable` and `cancellable` relationships (AC 1)
  - [x] 1.3 Test: non-existent token string throws `TokenVerificationException` with `reason === NotFound` (AC 2)
  - [x] 1.4 Test: expired token (insert record with past `expires_at`) throws `TokenVerificationException` with `reason === Expired` (AC 3)
  - [x] 1.5 Test: consumed token (insert record with non-null `used_at`) throws `TokenVerificationException` with `reason === Consumed` (AC 4)
  - [x] 1.6 Test: token that is both consumed AND expired throws `Consumed` (not `Expired`) — verifies check precedence (AC 3+4 edge case)
  - [x] 1.7 Test: all failure cases throw the same exception class (`TokenVerificationException`); distinguishing requires `$e->reason` access (AC 6)
  - [x] 1.8 Confirm all new tests FAIL (verify returns `RuntimeException` stub)

- [x] **Task 2: Implement verify() method** (GREEN phase — AC: 1-5)
  - [x] 2.1 Replace `RuntimeException` stub in `CancellationTokenService::verify()` with implementation
  - [x] 2.2 Hash the plain-text token using existing `hashToken()` private method
  - [x] 2.3 Look up token record: `CancellationToken::where('token', $computedHash)->first()` — single indexed DB lookup (NFR2)
  - [x] 2.4 If no record found, throw `TokenVerificationException(TokenVerificationFailure::NotFound)`
  - [x] 2.5 Apply `hash_equals($stored->token, $computedHash)` as defense-in-depth timing-safe check (AC 5, NFR8) — throw `NotFound` if mismatch
  - [x] 2.6 If `$stored->used_at` is not null, throw `TokenVerificationException(TokenVerificationFailure::Consumed)`
  - [x] 2.7 If `$stored->expires_at` is not null and is in the past, throw `TokenVerificationException(TokenVerificationFailure::Expired)`
  - [x] 2.8 Return the `CancellationToken` model instance
  - [x] 2.9 Confirm all tests from Task 1 now pass

- [x] **Task 3: Refactor and quality checks** (REFACTOR phase — AC: all)
  - [x] 3.1 Verify code follows project conventions (no `===` on hashes, no plain-text persistence)
  - [x] 3.2 Run `composer test` — all tests pass (no regressions from 55 existing tests)
  - [x] 3.3 Run `composer analyse` — PHPStan passes
  - [x] 3.4 Run `composer format` — Pint passes

### Review Findings

- [x] [Review][Decision] Dead `hash_equals` check is unreachable — removed dead block; DB uniqueness guarantee accepted as satisfying NFR8
- [x] [Review][Decision] AC 6 violated: exception messages are externally distinguishable — replaced per-reason messages with single generic `'Token verification failed'`; updated unit tests
- [x] [Review][Patch] `$this->fail()` in Pest closures will fatal-error [tests/Feature/TokenVerificationTest.php:67,105,143,167]
- [x] [Review][Patch] AC 6 omnibus test silently passes if `verify()` returns without throwing — restructured to `expect()->toThrow()` [tests/Feature/TokenVerificationTest.php:170]
- [x] [Review][Patch] `CancellationToken::first()` is order-dependent in multi-token AC 6 test — scoped to `where('cancellable_id', $booking->id)` [tests/Feature/TokenVerificationTest.php:185]
- [x] [Review][Defer] AC 5 has no feature test — no assertion that `hash_equals()` is used; deferred to Story 6.3 architecture tests — deferred, pre-existing
- [x] [Review][Defer] TOCTOU race on expiry/consumption — inherent read-then-act design, pre-existing architectural concern — deferred, pre-existing
- [x] [Review][Defer] Timezone handling in `isPast()` — pre-existing concern not introduced by this diff — deferred, pre-existing
- [x] [Review][Defer] Millisecond-precision expiry boundary — system-level edge case, out of scope — deferred, pre-existing
- [x] [Review][Defer] Empty string `$plainToken` not validated — returns NotFound, not a spec requirement — deferred, pre-existing
- [x] [Review][Defer] Orphaned tokenable/cancellable relationships — lazy-load failure not guarded; out of scope for this story — deferred, pre-existing
- [x] [Review][Defer] App key rotation between creation and verification — already tracked in Known Deferred Issues — deferred, pre-existing
- [x] [Review][Defer] `RefreshDatabase` + `Schema::hasTable` redundancy — pre-existing pattern from TokenCreationTest.php — deferred, pre-existing
- [x] [Review][Defer] `new CancellationTokenService` constructed directly in tests — pre-existing convention — deferred, pre-existing
- [x] [Review][Defer] Timing oracle on DB token lookup — architectural design prescribed by spec — deferred, pre-existing

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation uses this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**Existing method to reuse:** The private `hashToken(string $plainToken): string` method already exists in `CancellationTokenService` and uses `hash_hmac('sha256', $plainToken, config('app.key'))`. Use this for consistency with `create()`.

### Verification Algorithm

```
1. computedHash = hashToken(plainToken)
2. stored = CancellationToken::where('token', computedHash)->first()
3. if stored is null → throw NotFound
4. if !hash_equals(stored.token, computedHash) → throw NotFound  (defense-in-depth)
5. if stored.used_at !== null → throw Consumed
6. if stored.expires_at !== null && stored.expires_at->isPast() → throw Expired
7. return stored
```

### Check Precedence Order

**CRITICAL — the order of checks matters for edge cases:**

1. **NotFound** — no record found (must be first — no token to inspect)
2. **Consumed** — `used_at` is not null (takes priority over Expired)
3. **Expired** — `expires_at` is in the past

**Why Consumed before Expired:** If a token was consumed AND has since expired, the consumed state is the definitive action taken. Story 2.5 AC 4 requires that `verify()` on a consumed token always returns `Consumed`, regardless of expiry status.

### hash_equals() Usage

The `hash_equals()` call in step 4 is technically redundant — the DB already matched via unique index lookup. However, it satisfies NFR8 (`hash_equals()` mandatory) and provides defense-in-depth timing safety. The ArchTest (Story 6.3) will enforce no `===` on token hashes in `src/`.

### AC 6 Interpretation

All failure paths throw the **same exception class** (`TokenVerificationException`). The `$reason` property (a `TokenVerificationFailure` enum case) is the only way to distinguish — and it requires explicit access by the consuming developer. The package generates no HTTP responses; enumeration protection is the consuming app's responsibility at the HTTP boundary.

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Feature tests use `RefreshDatabase` trait
- Test descriptions are lowercase sentences

### Test Setup Pattern

Use the same `beforeEach()` pattern from `tests/Feature/TokenCreationTest.php`:

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

### Creating Test Tokens for Verification Tests

Use the existing `CancellationTokenService::create()` to create valid tokens, then modify their state directly for edge cases:

```php
// Valid token
$plainToken = $service->create($booking, $user);

// Expired token — modify directly (bypasses mass-assignment protection)
$token = CancellationToken::first();
$token->expires_at = now()->subHour();
$token->save();

// Consumed token
$token = CancellationToken::first();
$token->used_at = now();
$token->save();
```

### Existing Test Fixtures

- `Foxen\CancellationToken\Tests\Fixtures\TestUser` — uses `test_users` table, `$guarded = ['*']`, no timestamps
- `Foxen\CancellationToken\Tests\Fixtures\TestBooking` — uses `test_bookings` table, `$guarded = ['*']`, no timestamps

### Previous Story Context (2.3)

From Story 2.3 completion:
- `hashToken()` is a private method using `hash_hmac('sha256', $plainToken, config('app.key'))`
- `generatePlainTextToken()` produces `prefix + Str::random(64)`
- `create()` does revocation of prior active tokens and rejects past `$expiresAt`
- Model uses `$guarded = ['*']` — direct property assignment for inserts/modifications
- 55 tests currently pass across all test files

### Known Deferred Issues

- **TestCase never sets `app.key`** — all HMAC tests use an empty key (deferred from Story 2.3 review). Tests are internally consistent since both creation and verification use the same key, but this doesn't prove production-safe key handling.
- **`app.key` base64 prefix not stripped** — `config('app.key')` may return `base64:...` format; HMAC uses the raw string. Deferred to future enhancement.

### Anti-Patterns to Avoid

1. **DO NOT** use `===` or `==` to compare token hashes — always `hash_equals()`
2. **DO NOT** modify the `CancellationTokenContract` interface — signature is already defined
3. **DO NOT** use `CancellationToken::create()` or `fill()` for modifying test tokens — model has `$guarded = ['*']`; use direct property assignment
4. **DO NOT** log, cache, or store the plain-text token at any point
5. **DO NOT** return `null` from `verify()` — always return model or throw exception
6. **DO NOT** eager-load relationships unless necessary — lazy loading is fine; AC 1 says "accessible"
7. **DO NOT** check `Expired` before `Consumed` — consumed state takes priority

### Project Structure Notes

Only these files should be modified or created:

| File | Action |
|---|---|
| `src/CancellationTokenService.php` | MODIFY — replace `verify()` stub with implementation |
| `tests/Feature/TokenVerificationTest.php` | CREATE — feature tests for verification |

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Token Generation & Security]
- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/architecture.md#Failure Handling Pattern]
- [Source: _bmad-output/planning-artifacts/architecture.md#Integration Points & Data Flow]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.4]
- [Source: _bmad-output/project-context.md#PHP Rules]
- [Source: _bmad-output/project-context.md#Security Anti-Patterns]
- [Source: _bmad-output/implementation-artifacts/2-3-token-creation.md — previous story context]

## Dev Agent Record

### Agent Model Used

GLM-5.1

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Implemented `verify()` method following the exact algorithm from Dev Notes: hash → DB lookup → hash_equals defense-in-depth → Consumed check → Expired check → return model
- Check precedence enforced: NotFound → Consumed → Expired (Consumed takes priority over Expired for edge case tokens that are both consumed and expired)
- `hash_equals()` used for timing-safe comparison — no `===` on hashes anywhere in implementation
- All 9 new tests pass, 66 total tests pass with zero regressions
- PHPStan and Pint both pass clean

### File List

- `src/CancellationTokenService.php` — MODIFIED (replaced `verify()` stub with full implementation, added imports for `TokenVerificationFailure` and `TokenVerificationException`)
- `tests/Feature/TokenVerificationTest.php` — CREATED (9 tests covering AC 1-6)

## Change Log

- 2026-04-01: Story 2.4 implemented — `verify()` method with hash lookup, timing-safe comparison, consumed/expired precedence, and 9 feature tests
