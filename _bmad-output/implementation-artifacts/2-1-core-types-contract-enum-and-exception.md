# Story 2.1: Core Types — Contract, Enum, and Exception

Status: done

## Story

As a developer,
I want a shared contract interface, verification failure enum, and exception type,
So that all package components share a single source of truth for token operation signatures and failure states.

## Acceptance Criteria

1. **Contract interface** — Given the package is installed, when `CancellationTokenContract` is inspected, then it declares three methods with exact signatures: `create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string`, `verify(string $plainToken): CancellationToken`, and `consume(string $plainToken): CancellationToken`

2. **Failure enum** — Given a token operation fails, when the failure reason is inspected, then it is one of three `TokenVerificationFailure` backed enum cases: `NotFound`, `Expired`, or `Consumed`

3. **Exception type** — Given a token operation fails, when a `TokenVerificationException` is caught, then it exposes a public `$reason` property containing a `TokenVerificationFailure` enum case

4. **Namespace compliance** — Given all three types are defined, when an architecture check is run, then all classes exist in the `Foxen\CancellationToken` root namespace at their specified paths: `Contracts/CancellationTokenContract.php`, `Enums/TokenVerificationFailure.php`, `Exceptions/TokenVerificationException.php`

## Tasks / Subtasks

- [x] **Task 1: Create TokenVerificationFailure Enum** (AC: 2, 4)
  - [x] 1.1 Create `src/Enums/TokenVerificationFailure.php`
  - [x] 1.2 Define as backed enum with `string` type
  - [x] 1.3 Add three cases: `NotFound = 'not_found'`, `Expired = 'expired'`, `Consumed = 'consumed'`

- [x] **Task 2: Update TokenVerificationException** (AC: 3, 4)
  - [x] 2.1 Update `src/Exceptions/TokenVerificationException.php` to carry `$reason` property
  - [x] 2.2 Add constructor accepting `TokenVerificationFailure $reason`
  - [x] 2.3 Make `$reason` a public readonly property

- [x] **Task 3: Update CancellationTokenContract Interface** (AC: 1, 4)
  - [x] 3.1 Update `src/Contracts/CancellationTokenContract.php` with correct signatures per architecture
  - [x] 3.2 Import `Illuminate\Database\Eloquent\Model` and `Carbon\Carbon`
  - [x] 3.3 Import `Foxen\CancellationToken\Models\CancellationToken` for return types
  - [x] 3.4 Fix `create()` signature: `create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string`
  - [x] 3.5 Fix `verify()` signature: `verify(string $plainToken): CancellationToken`
  - [x] 3.6 Fix `consume()` signature: `consume(string $plainToken): CancellationToken`

- [x] **Task 4: Update CancellationTokenService Stub** (AC: 1)
  - [x] 4.1 Update `src/CancellationTokenService.php` method signatures to match updated contract
  - [x] 4.2 Add proper type imports
  - [x] 4.3 Keep `RuntimeException('Not implemented')` throws for Stories 2.3-2.5

- [x] **Task 5: Unit Tests** (AC: 1-4)
  - [x] 5.1 Create `tests/Unit/TokenVerificationFailureEnumTest.php`
  - [x] 5.2 Test enum has three cases with correct values
  - [x] 5.3 Create `tests/Unit/TokenVerificationExceptionTest.php`
  - [x] 5.4 Test exception carries reason property
  - [x] 5.5 Test exception reason is accessible and returns correct enum case

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation must use this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

### Current State (from Story 1.1)

The following stub files already exist from Story 1.1 and need updating:

1. **`src/Contracts/CancellationTokenContract.php`** — Has INCORRECT signatures:
   ```php
   // Current (WRONG):
   public function create(object $cancellable, ?object $tokenable = null, ?int $expiryMinutes = null): string;
   public function verify(string $token, ?object $cancellable = null): object;
   public function consume(string $token, ?object $cancellable = null): object;
   ```
   Must be changed to:
   ```php
   // Required (CORRECT):
   public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string;
   public function verify(string $plainToken): CancellationToken;
   public function consume(string $plainToken): CancellationToken;
   ```

2. **`src/Exceptions/TokenVerificationException.php`** — Is a basic empty exception:
   ```php
   // Current:
   class TokenVerificationException extends Exception {}
   ```
   Must be updated to carry `$reason` property.

3. **`src/CancellationTokenService.php`** — Has stub methods with wrong signatures. Update to match contract.

### TokenVerificationFailure Enum Implementation

```php
<?php

namespace Foxen\CancellationToken\Enums;

enum TokenVerificationFailure: string
{
    case NotFound = 'not_found';
    case Expired = 'expired';
    case Consumed = 'consumed';
}
```

**Why backed enum?** The string value allows serialization for logging/monitoring while the enum type provides exhaustive matching in `match` expressions.

### TokenVerificationException Implementation

```php
<?php

namespace Foxen\CancellationToken\Exceptions;

use Exception;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;

class TokenVerificationException extends Exception
{
    public function __construct(
        public readonly TokenVerificationFailure $reason,
    ) {
        $message = match ($reason) {
            TokenVerificationFailure::NotFound => 'Token not found',
            TokenVerificationFailure::Expired => 'Token has expired',
            TokenVerificationFailure::Consumed => 'Token has already been consumed',
        };

        parent::__construct($message);
    }
}
```

**Note:** Cannot use `readonly class` because `Exception` is not readonly. Used `public readonly` property instead.

### CancellationTokenContract Interface Implementation

```php
<?php

namespace Foxen\CancellationToken\Contracts;

use Carbon\Carbon;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;

interface CancellationTokenContract
{
    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string;
    public function verify(string $plainToken): CancellationToken;
    public function consume(string $plainToken): CancellationToken;
}
```

**Key differences from previous stub:**
- `$tokenable` is REQUIRED (not nullable) — the actor must always be specified
- `$expiresAt` is a Carbon instance (not int minutes) — the service handles default calculation
- Return type is `CancellationToken` model (not generic `object`)
- `verify()` and `consume()` do NOT take `$cancellable` parameter — verification is by token only

### CancellationTokenService Stub Update

```php
<?php

namespace Foxen\CancellationToken;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;

class CancellationTokenService implements CancellationTokenContract
{
    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string
    {
        // Implementation will be completed in Story 2.3
        throw new \RuntimeException('Not implemented');
    }

    public function verify(string $plainToken): CancellationToken
    {
        // Implementation will be completed in Story 2.4
        throw new \RuntimeException('Not implemented');
    }

    public function consume(string $plainToken): CancellationToken
    {
        // Implementation will be completed in Story 2.5
        throw new \RuntimeException('Not implemented');
    }
}
```

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Unit tests must NOT hit the database
- Test descriptions are lowercase sentences

### Files Created/Modified

| File | Action |
|---|---|
| `src/Enums/TokenVerificationFailure.php` | CREATE |
| `src/Contracts/CancellationTokenContract.php` | UPDATE |
| `src/Exceptions/TokenVerificationException.php` | UPDATE |
| `src/CancellationTokenService.php` | UPDATE |
| `src/Models/CancellationToken.php` | CREATE (stub for PHPStan) |
| `tests/Unit/TokenVerificationFailureEnumTest.php` | CREATE |
| `tests/Unit/TokenVerificationExceptionTest.php` | CREATE |

### Files NOT Touched

- `src/CancellationTokenServiceProvider.php` — binding already set up correctly
- `src/Facades/CancellationToken.php` — facade accessor already correct
- `config/cancellation-tokens.php` — no changes needed
- `database/migrations/*` — no changes needed
- `tests/Feature/*` — unit tests only for this story

### Previous Story Learnings (Story 1.1)

1. **Contract interface signatures matter** — The stub created in 1.1 had incorrect signatures. This story MUST correct them to match architecture exactly.

2. **Service container binding** — Already set up to bind `CancellationTokenContract::class` → `CancellationTokenService::class`. Changing the contract interface will NOT break this.

3. **Pest conventions enforced** — Use `it()` syntax and `expect()` assertions. Code review flagged this.

### Anti-Patterns to Avoid

1. **DO NOT** make `$tokenable` nullable in `create()` — the actor must always be specified
2. **DO NOT** use `int $expiryMinutes` — use `?Carbon $expiresAt` as per architecture
3. **DO NOT** add `$cancellable` parameter to `verify()`/`consume()` — verification is by token hash only
4. **DO NOT** return `object` — use specific `CancellationToken` return type
5. **DO NOT** add getters to exception — use public readonly property directly

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Error Classification]
- [Source: _bmad-output/planning-artifacts/architecture.md#Service Method Return Types]
- [Source: _bmad-output/planning-artifacts/architecture.md#Failure Handling Pattern]
- [Source: _bmad-output/planning-artifacts/architecture.md#Project Structure]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.1]
- [Source: _bmad-output/implementation-artifacts/1-1-*.md#Dev Notes]

## Dev Agent Record

### Agent Model Used

Claude glm-5

### Debug Log References

N/A

### Completion Notes List

- Created `TokenVerificationFailure` backed enum with three cases: `NotFound`, `Expired`, `Consumed`
- Updated `TokenVerificationException` to carry `public readonly TokenVerificationFailure $reason` property
- Exception uses `match` expression to generate appropriate message based on reason
- Updated `CancellationTokenContract` interface with correct signatures per architecture
- Updated `CancellationTokenService` stub to match new contract signatures
- Created minimal `CancellationToken` model stub for PHPStan compliance (full implementation in Story 2.2)
- All 22 tests pass including 12 new unit tests for enum and exception
- PHPStan analysis passes with no errors
- Pint formatting applied

### File List

- `src/Enums/TokenVerificationFailure.php` (created)
- `src/Exceptions/TokenVerificationException.php` (updated)
- `src/Contracts/CancellationTokenContract.php` (updated)
- `src/CancellationTokenService.php` (updated)
- `src/Models/CancellationToken.php` (created - stub)
- `tests/Unit/TokenVerificationFailureEnumTest.php` (created)
- `tests/Unit/TokenVerificationExceptionTest.php` (created)

### Review Findings

- [x] [Review][Patch] `TokenVerificationException` missing `$previous` exception chaining — constructor has no `?\Throwable $previous = null` parameter and does not forward it to `parent::__construct()`. When the service layer wraps a DB exception, the original cause is permanently discarded. [src/Exceptions/TokenVerificationException.php]
- [x] [Review][Defer] `token` column is mass-assignable on Eloquent model — any `CancellationToken::create($request->all())` call can overwrite the stored token hash directly [src/Models/CancellationToken.php] — deferred, Story 2.2's concern
- [x] [Review][Defer] `used_at` is mass-assignable, allowing a token to be pre-consumed or un-consumed via mass-assignment, bypassing the single-use guarantee [src/Models/CancellationToken.php] — deferred, Story 2.2's concern
- [x] [Review][Defer] `expires_at` is mass-assignable, allowing arbitrary expiry manipulation [src/Models/CancellationToken.php] — deferred, Story 2.2's concern
- [x] [Review][Defer] Morphable type columns (`tokenable_type`, `cancellable_type`) are mass-assignable, allowing redirect to arbitrary model class [src/Models/CancellationToken.php] — deferred, Story 2.2's concern
- [x] [Review][Defer] `@property int $id` in PHPDoc is a silent mismatch if UUID/ULID keys are used — no `$keyType` or `$incrementing` set on model [src/Models/CancellationToken.php] — deferred, Story 2.2's concern
- [x] [Review][Defer] Model hardcodes `protected $table = 'cancellation_tokens'` — bypasses the configurable `table` key in `config/cancellation-tokens.php` [src/Models/CancellationToken.php] — deferred, Story 2.2's concern
- [x] [Review][Defer] `create()` accepts a past `?Carbon $expiresAt` with no validation — token is already expired at creation instant [src/Contracts/CancellationTokenContract.php] — deferred, Story 2.3's concern (not implemented yet)
- [x] [Review][Defer] `match` in `TokenVerificationException::__construct` is not enforced as exhaustive — adding a new enum case without updating the match throws `UnhandledMatchError` at runtime [src/Exceptions/TokenVerificationException.php] — deferred, pre-existing architectural concern

## Change Log

- 2026-03-28: Completed Story 2.1 - Core Types (Contract, Enum, Exception)
- 2026-03-28: Code review — 0 decision-needed, 1 patch, 8 deferred, 4 dismissed
