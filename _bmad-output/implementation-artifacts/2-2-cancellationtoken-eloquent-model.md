# Story 2.2: CancellationToken Eloquent Model

Status: done

## Story

As a developer,
I want a `CancellationToken` Eloquent model backed by the published migration,
So that token records can be read and written using Laravel's standard ORM conventions.

## Acceptance Criteria

1. **Configurable table name** — Given the migration has been run, when the `CancellationToken` model is instantiated, then it maps to the table name from `config('cancellation-tokens.table')`

2. **Tokenable relationship** — Given a token record exists, when `$token->tokenable` is accessed, then it returns the associated polymorphic actor model via a `morphTo` relationship

3. **Cancellable relationship** — Given a token record exists, when `$token->cancellable` is accessed, then it returns the associated polymorphic subject model via a `morphTo` relationship

4. **Date casting** — Given a token record exists, when `$token->expires_at` or `$token->used_at` are accessed, then they are cast as `Carbon` instances (or `null` if not set)

5. **No business logic** — Given an architecture check is run, when the model class is inspected, then it contains no token hashing, verification, or business logic — those concerns belong in the service

6. **Prunable integration** — Given `model:prune` is scheduled, when `php artisan model:prune` is run, then tokens where `expires_at < now()` OR `used_at IS NOT NULL` are deleted

7. **Mass-assignment protection** — Given the model is used with mass-assignment, when attempting to set `token`, `tokenable_*`, `cancellable_*`, `expires_at`, or `used_at` via mass-assignment, then these fields are protected (empty `$guarded` or explicit `[$guarded]`)

## Tasks / Subtasks

- [x] **Task 1: Update CancellationToken Model** (AC: 1, 2, 3, 4, 5, 7)
  - [x] 1.1 Update table name to use config: `config('cancellation-tokens.table')`
  - [x] 1.2 Add `morphTo` relationship method for `tokenable()`
  - [x] 1.3 Add `morphTo` relationship method for `cancellable()`
  - [x] 1.4 Ensure `expires_at` and `used_at` are cast as `datetime` (already done, verify)
  - [x] 1.5 Replace `$fillable` with `$guarded = ['*']` to prevent mass-assignment of sensitive columns
  - [x] 1.6 Add proper PHPDoc for Eloquent-native properties (`$id`, morph column types)

- [x] **Task 2: Implement Prunable Trait** (AC: 6)
  - [x] 2.1 Add `Illuminate\Database\Eloquent\Prunable` trait to model
  - [x] 2.2 Implement `prunable()` method returning query for expired OR consumed tokens
  - [x] 2.3 Ensure pruning uses chunked deletion (Laravel default with Prunable trait)

- [x] **Task 3: Feature Tests** (AC: 1-7)
  - [x] 3.1 Create `tests/Feature/CancellationTokenModelTest.php`
  - [x] 3.2 Test model uses configurable table name
  - [x] 3.3 Test `tokenable` relationship returns correct morphed model
  - [x] 3.4 Test `cancellable` relationship returns correct morphed model
  - [x] 3.5 Test date casting for `expires_at` and `used_at`
  - [x] 3.6 Test prunable query returns correct tokens (expired + consumed)
  - [x] 3.7 Test prunable query excludes valid, unconsumed tokens
  - [x] 3.8 Test mass-assignment protection on sensitive columns

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation must use this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**No soft deletes** — `used_at` and `expires_at` serve as the audit trail. Do NOT add `deleted_at` or `SoftDeletes` trait.

### Mass-Assignment Protection Rationale

The model MUST NOT allow mass-assignment of these columns:
- `token` — the HMAC-SHA256 hash; overwriting breaks verification
- `tokenable_type`/`tokenable_id` — actor identity; changing allows impersonation
- `cancellable_type`/`cancellable_id` — subject identity; changing allows redirecting cancellation
- `expires_at` — expiry enforcement; changing extends/shortens validity
- `used_at` — single-use enforcement; clearing allows replay attacks

Using `$guarded = ['*']` ensures the service layer has explicit control over all data.

### Prunable Implementation

The `Prunable` trait automatically:
- Queries records matching `prunable()` conditions
- Deletes in chunks (configurable via `$chunkSize` property)
- Works with `php artisan model:prune` command

Default pruning criteria:
- `expires_at < now()` — token has passed its expiry time
- `used_at IS NOT NULL` — token has been consumed

Both conditions use OR — once expired or consumed, the token has no further utility.

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Feature tests use `RefreshDatabase` trait
- Test descriptions are lowercase sentences

### Anti-Patterns to Avoid

1. **DO NOT** add `SoftDeletes` trait or `deleted_at` column — use `used_at` and `expires_at` for audit trail
2. **DO NOT** add token hashing/verification logic to model — belongs in service
3. **DO NOT** use `$fillable` with explicit column list — use `$guarded = ['*']`
4. **DO NOT** hardcode table name — use `config('cancellation-tokens.table')`
5. **DO NOT** add custom Artisan commands for pruning — `model:prune` is the only mechanism
6. **DO NOT** expose `id` in any public API — token string is the public identifier

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Data Architecture]
- [Source: _bmad-output/planning-artifacts/architecture.md#Project Structure]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.2]
- [Source: _bmad-output/project-context.md#Laravel / Package Rules]

## Dev Agent Record

### Agent Model Used

Claude glm-5

### Debug Log References

N/A

### Completion Notes List

- Updated `CancellationToken` model with configurable table name via `getTable()` method
- Added `Prunable` trait for automatic token cleanup via `php artisan model:prune`
- Implemented `prunable()` method returning expired OR consumed tokens
- Added `tokenable()` and `cancellable()` morphTo relationships
- Replaced `$fillable` with `$guarded = ['*']` for mass-assignment protection
- Added proper PHPDoc with `@property` annotations for all columns
- Added `casts()` method for `expires_at` and `used_at` as datetime
- Created comprehensive feature tests (25 tests, 33 assertions) covering:
  - Configurable table name (3 tests)
  - Polymorphic relationships (4 tests)
  - Date casting (4 tests)
  - No business logic verification (2 tests)
  - Prunable integration (4 tests)
  - Mass-assignment protection (8 tests)
- All 47 tests pass
- PHPStan analysis passes with no errors
- Pint formatting applied

### File List

- `src/Models/CancellationToken.php` (updated)
- `tests/Feature/CancellationTokenModelTest.php` (created)

### Review Findings

- [x] [Review][Patch] `getTable()` uses `??` — empty string config value bypasses fallback [`src/Models/CancellationToken.php:42`]
- [x] [Review][Patch] `eval()` used to define test fixture classes — replaced with `tests/Fixtures/TestUser.php` and `tests/Fixtures/TestBooking.php` [`tests/Feature/CancellationTokenModelTest.php:30-44`]
- [x] [Review][Patch] Migration run in `beforeEach` without `Schema::hasTable` guard — double-migration risk with `RefreshDatabase` [`tests/Feature/CancellationTokenModelTest.php:29`]
- [x] [Review][Patch] Global `createTestToken()` helper at file scope — wrapped with `function_exists` guard [`tests/Feature/CancellationTokenModelTest.php:13`]
- [x] [Review][Patch] Missing test: token with `expires_at = NULL, used_at = NULL` should be excluded from prunable query [`tests/Feature/CancellationTokenModelTest.php` prunable section]
- [x] [Review][Patch] Relationship tests use hardcoded foreign IDs (`tokenable_id = 1` / `cancellable_id = 1`) with no corresponding DB rows [`tests/Feature/CancellationTokenModelTest.php` relationship tests]
- [x] [Review][Defer] `static::` in `prunable()` may misdirect if model is subclassed [`src/Models/CancellationToken.php:80`] — deferred, theoretical/pre-existing
- [x] [Review][Defer] Architecture assertions for absent `SoftDeletes`/`$fillable` belong in story 6-3 — deferred, out of scope for this story
- [x] [Review][Defer] `getTable()` re-reads config on every Eloquent call — no `$this->table` caching [`src/Models/CancellationToken.php:42`] — deferred, negligible in practice

## Change Log

- 2026-03-28: Completed Story 2.2 - CancellationToken Eloquent Model
