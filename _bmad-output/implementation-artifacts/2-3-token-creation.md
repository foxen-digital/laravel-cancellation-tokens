# Story 2.3: Token Creation

Status: done

## Story

As a developer,
I want to create a cancellation token via the service,
So that I receive a plain-text token string to embed in URLs while the package handles secure storage.

## Acceptance Criteria

1. **Explicit expiry** — Given a cancellable model, a tokenable model, and an explicit expiry timestamp, when `CancellationTokenService::create()` is called, then a new row is inserted into `cancellation_tokens` with the correct `cancellable_type/id`, `tokenable_type/id`, and `expires_at` values

2. **Default expiry** — Given a cancellable model and a tokenable model with no `expiresAt` argument provided, when `CancellationTokenService::create()` is called, then `expires_at` is set to `now()->addMinutes(config('cancellation-tokens.default_expiry'))`

3. **Hash-only storage** — Given a token is created, when the stored `token` column value is inspected, then it is an HMAC-SHA256 hash of the plain-text token keyed with `app.key` — the plain-text value is never in the database

4. **Plain-text return** — Given a token is created, when the return value of `create()` is inspected, then it is a plain-text string beginning with the configured prefix (default `ct_`) followed by 64 random alphanumeric characters

5. **Uniqueness** — Given two successive calls to `create()`, when the returned token strings are compared, then they are unique — no two tokens are identical

6. **Hash verification** — Given a token is created, when the plain-text token string is hashed with `hash_hmac('sha256', $plainToken, config('app.key'))`, then the result matches the `token` column value stored in the database

## Tasks / Subtasks

- [x] **Task 1: Implement Token Generation Logic** (AC: 4, 5)
  - [x] 1.1 Create private `generatePlainTextToken()` method in `CancellationTokenService`
  - [x] 1.2 Use `Str::random(64)` for entropy (backed by `random_bytes()`)
  - [x] 1.3 Prepend configured prefix from `config('cancellation-tokens.prefix')` (default: `ct_`)
  - [x] 1.4 Ensure each call produces a unique token string

- [x] **Task 2: Implement Token Hashing** (AC: 3, 6)
  - [x] 2.1 Create private `hashToken(string $plainToken): string` method
  - [x] 2.2 Use `hash_hmac('sha256', $plainToken, config('app.key'))`
  - [x] 2.3 NEVER use bcrypt, password_hash, or slow hashes

- [x] **Task 3: Implement create() Method** (AC: 1, 2, 3, 4, 6)
  - [x] 3.1 Resolve default expiry: if `$expiresAt` is null, use `now()->addMinutes(config('cancellation-tokens.default_expiry'))`
  - [x] 3.2 Generate plain-text token via `generatePlainTextToken()`
  - [x] 3.3 Hash the token via `hashToken()` for storage
  - [x] 3.4 Insert token record using direct property assignment and `save()`
  - [x] 3.5 Return the plain-text token string (ONLY time it's returned)

- [x] **Task 4: Feature Tests** (AC: 1-6)
  - [x] 4.1 Create `tests/Feature/TokenCreationTest.php`
  - [x] 4.2 Test token record is created with correct polymorphic relationships (AC 1)
  - [x] 4.3 Test default expiry is applied from config (AC 2)
  - [x] 4.4 Test custom expiry is respected (AC 1)
  - [x] 4.5 Test stored token is HMAC-SHA256 hash, not plain-text (AC 3)
  - [x] 4.6 Test returned token has correct format: prefix + 64 chars (AC 4)
  - [x] 4.7 Test multiple tokens are unique (AC 5)
  - [x] 4.8 Test hash can be verified with `hash_hmac()` (AC 6)

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation must use this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**Existing Contract Signature** (DO NOT MODIFY):
```php
public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string;
```

The service already has this method signature defined in `CancellationTokenContract`. Implement the body only.

### Token Generation Algorithm

```
plainToken = prefix + Str::random(64)
hashForDb  = hash_hmac('sha256', plainToken, config('app.key'))
```

**Example:**
- Config: `prefix = 'ct_'`
- Generated: `ct_abc123...` (67 characters total)
- Stored: `a1b2c3...` (64 hex characters from HMAC-SHA256)

### Security Requirements

1. **NEVER** store, log, or cache the plain-text token after creation
2. **NEVER** use `===` or `==` to compare token hashes (use `hash_equals()` — but not needed in this story)
3. **NEVER** use `bcrypt`, `password_hash()`, or any slow hash — HMAC-SHA256 only
4. **NEVER** read `env()` directly — always use `config('cancellation-tokens.*')`

### Database Insert Pattern

The `CancellationToken` model uses `$guarded = ['*']` for mass-assignment protection. Use direct property assignment (bypasses mass-assignment since it's not using `fill()` or `create()`):

```php
$token = new CancellationToken;
$token->token = $hashedToken;
$token->tokenable_type = $tokenable::class;
$token->tokenable_id = $tokenable->id;
$token->cancellable_type = $cancellable::class;
$token->cancellable_id = $cancellable->id;
$token->expires_at = $expiresAt;
$token->save();
```

### Default Expiry Calculation

```php
$expiresAt ??= now()->addMinutes(config('cancellation-tokens.default_expiry'));
```

Config default is `10080` minutes = 7 days.

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Feature tests use `RefreshDatabase` trait
- Test descriptions are lowercase sentences

### Existing Test Fixtures

Use the existing fixture classes from Story 2.2:
- `Foxen\CancellationToken\Tests\Fixtures\TestUser`
- `Foxen\CancellationToken\Tests\Fixtures\TestBooking`

These are already set up with migrations in `beforeEach()`.

### Anti-Patterns to Avoid

1. **DO NOT** modify the `CancellationTokenContract` interface — signature is already defined
2. **DO NOT** use `CancellationToken::create()` or `fill()` — model has `$guarded = ['*']` mass-assignment protection; use direct property assignment
3. **DO NOT** log or store the plain-text token anywhere
4. **DO NOT** use `rand()`, `mt_rand()`, or `uniqid()` — use `Str::random()` only
5. **DO NOT** expose the `id` column in any public API — token string is the public identifier

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Token Generation & Security]
- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.3]
- [Source: _bmad-output/project-context.md#PHP Rules]
- [Source: src/Contracts/CancellationTokenContract.php]
- [Source: src/Models/CancellationToken.php]
- [Source: src/CancellationTokenService.php]

### Previous Story Context (2.2)

From Story 2.2 completion:
- Model uses `$guarded = ['*']` — use direct property assignment for inserts
- Test fixtures exist at `tests/Fixtures/TestUser.php` and `tests/Fixtures/TestBooking.php`
- Migration creates table with indexed `token` VARCHAR(255) column
- Model has `tokenable()` and `cancellable()` morphTo relationships

## Dev Agent Record

### Agent Model Used

Claude glm-5

### Debug Log References

N/A

### Completion Notes List

- Implemented `generatePlainTextToken()` private method using `Str::random(64)` with configurable prefix
- Implemented `hashToken()` private method using HMAC-SHA256 with `app.key`
- Implemented `create()` method with:
  - Default expiry resolution from config (`10080` minutes = 7 days)
  - Direct property assignment for database insert (bypasses mass-assignment protection)
  - Used `getKey()` method instead of `->id` property for PHPStan compatibility
- Created comprehensive feature tests (8 tests, 16 assertions) covering:
  - Explicit expiry timestamp (AC 1)
  - Default expiry from config (AC 2)
  - Hash-only storage verification (AC 3)
  - Token format with prefix + 64 chars (AC 4)
  - Uniqueness of successive tokens (AC 5)
  - Hash verification with `hash_hmac()` (AC 6)
  - Custom prefix from config
  - Plain-text token never stored in database
- All 55 tests pass (no regressions)
- PHPStan analysis passes with no errors
- Pint formatting passes

### File List

- `src/CancellationTokenService.php` (updated)
- `tests/Feature/TokenCreationTest.php` (created)

### Review Findings

- [x] [Review][Defer] `app.key` base64 prefix not stripped before HMAC — deferred, future enhancement will add configurable hash key instead of using only `app.key` [src/CancellationTokenService.php]
- [x] [Review][Patch] `create()` must revoke existing active tokens for the same `(cancellable, tokenable)` pair before inserting — decided: prior tokens should be expired/deleted [src/CancellationTokenService.php]
- [x] [Review][Patch] `create()` must reject a past `$expiresAt` with `\InvalidArgumentException` — decided: throw if timestamp is in the past [src/CancellationTokenService.php]
- [x] [Review][Patch] `default_expiry` config has no fallback — `addMinutes(null)` silently creates zero-minute expiry if key is missing [src/CancellationTokenService.php]
- [x] [Review][Patch] AC 6 test uses `toBe()` (===) instead of `hash_equals()` — violates security requirement 2 [tests/Feature/TokenCreationTest.php]
- [x] [Review][Patch] `$plainToken` assigned but unused in AC 2 default-expiry test [tests/Feature/TokenCreationTest.php]
- [x] [Review][Patch] AC 3 hash assertion checks only length (64), not hex format — add regex `/^[0-9a-f]{64}$/` assertion [tests/Feature/TokenCreationTest.php]
- [x] [Review][Patch] AC 4 test checks token length but not that the 64-char suffix is alphanumeric as spec requires [tests/Feature/TokenCreationTest.php]
- [x] [Review][Defer] Unsaved model passed to `create()` causes null `getKey()` and DB rejection — deferred, pre-existing contract gap [src/CancellationTokenService.php]
- [x] [Review][Defer] TestCase never sets `app.key` so all HMAC tests use an empty key — deferred, pre-existing test infrastructure [tests/TestCase.php]

## Change Log

- 2026-03-28: Completed Story 2.3 - Token Creation
