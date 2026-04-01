# Story 3.3: ValidCancellationToken Validation Rule

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a developer,
I want to validate a token string using a Laravel validation rule,
so that I can integrate token validation into standard form requests and provide appropriate feedback for each failure reason.

## Acceptance Criteria

1. **Valid token passes** — Given a valid, unexpired, unconsumed token string, when `['token' => ['required', new ValidCancellationToken]]` is used in a form request, then validation passes

2. **NotFound failure** — Given a token string that does not exist, when the `ValidCancellationToken` rule is evaluated, then validation fails with a failure reason of `TokenVerificationFailure::NotFound` accessible to the developer

3. **Expired failure** — Given an expired token string, when the `ValidCancellationToken` rule is evaluated, then validation fails with a failure reason of `TokenVerificationFailure::Expired` accessible to the developer

4. **Consumed failure** — Given a consumed token string, when the `ValidCancellationToken` rule is evaluated, then validation fails with a failure reason of `TokenVerificationFailure::Consumed` accessible to the developer

5. **Generic public error** — Given any token validation failure, when the validation error is returned in an HTTP response, then the error message does not expose which specific failure reason occurred — the reason is available internally via the rule, not in the public response

## Tasks / Subtasks

- [x] **Task 1: Create the ValidCancellationToken validation rule** (AC: 1-5)
  - [x] 1.1 Create `src/Rules/ValidCancellationToken.php`
  - [x] 1.2 Implement `Illuminate\Contracts\Validation\ValidationRule` interface with `validate(string $attribute, mixed $value, Closure $fail): void`
  - [x] 1.3 In `validate()`: resolve `CancellationTokenContract` from the container, call `verify($value)`, return silently on success
  - [x] 1.4 In `validate()`: catch `TokenVerificationException`, store `$e->reason` on a public property, call `$fail()` with a **generic** message (no failure-specific wording)
  - [x] 1.5 Expose a public `?TokenVerificationFailure $failureReason` property (nullable, set only on failure) so developers can inspect the specific reason after validation

- [x] **Task 2: Write failing tests for the validation rule** (RED phase — AC: 1-5)
  - [x] 2.1 Create `tests/Feature/ValidationRuleTest.php` with `uses(RefreshDatabase::class)` and `beforeEach()` setup (same pattern as `FacadeTest.php`)
  - [x] 2.2 Test: valid token passes validation (AC 1)
  - [x] 2.3 Test: non-existent token fails validation, `$failureReason === NotFound` (AC 2)
  - [x] 2.4 Test: expired token fails validation, `$failureReason === Expired` (AC 3)
  - [x] 2.5 Test: consumed token fails validation, `$failureReason === Consumed` (AC 4)
  - [x] 2.6 Test: validation error message is generic — does not contain "expired", "consumed", or "not found" (AC 5)
  - [x] 2.7 Test: `$failureReason` is `null` after a passing validation (AC 1 + property hygiene)
  - [x] 2.8 Test: rule works via `$validator->validate()` or `Validator::make()` integration (form request simulation)

- [x] **Task 3: Make all tests pass** (GREEN phase — AC: 1-5)
  - [x] 3.1 Run `composer test` — all tests pass
  - [x] 3.2 Fix any failures in the rule implementation

- [x] **Task 4: Refactor and quality checks** (REFACTOR phase — AC: all)
  - [x] 4.1 Verify code follows project conventions (namespace, directory, no `===` on hashes, no plain-text persistence)
  - [x] 4.2 Run `composer test` — all tests pass (no regressions)
  - [x] 4.3 Run `composer analyse` — PHPStan passes
  - [x] 4.4 Run `composer format` — Pint passes

## Dev Notes

### NEW FILE — Must Be Created

`src/Rules/ValidCancellationToken.php` — this file does not exist yet. The `src/Rules/` directory must be created.

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — all implementation uses this as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**Full class path:** `Foxen\CancellationToken\Rules\ValidCancellationToken`

### ValidationRule Interface (Use This, NOT Deprecated `Rule`)

The architecture specifies `ValidationRule` — the modern Laravel closure-based interface:

```php
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCancellationToken implements ValidationRule
{
    public ?TokenVerificationFailure $failureReason = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(CancellationTokenContract::class)->verify($value);
            // On success: $failureReason stays null, do not call $fail()
        } catch (TokenVerificationException $e) {
            $this->failureReason = $e->reason;
            $fail('validation.cancellation_token')->translate();
        }
    }
}
```

**DO NOT implement the deprecated `Illuminate\Contracts\Validation\Rule` interface** (with `passes()` and `message()` methods).

### Implementation Details

**Resolving the service:** Use `app(CancellationTokenContract::class)` — NOT `CancellationTokenService` directly. This ensures the rule goes through the container binding and works with `CancellationTokenFake` in tests.

**Error message:** The `$fail()` closure sets the validation error message visible to end users. This message MUST be generic — it cannot reveal whether the token was expired, consumed, or not found. A single generic message like "The token is invalid." is required.

**Why generic:** Per NFR10, failure reasons must not be exposed externally in a way that enables enumeration attacks. The `TokenVerificationFailure` enum is for internal developer use only. The validation error message should be a single generic string regardless of the actual failure reason.

**How developers access the reason:** After validation fails, developers can inspect `$rule->failureReason` to determine the specific cause. This supports FR22 (determine specific reason for user feedback) without exposing it in the HTTP response. Example usage in a controller:

```php
$rule = new ValidCancellationToken;
$validator = Validator::make($data, ['token' => ['required', $rule]]);
if ($validator->fails()) {
    // $rule->failureReason is TokenVerificationFailure::Expired|Consumed|NotFound
    // Send appropriate response based on the reason — NOT in the validation message
}
```

### Test Setup Pattern

Use the same `beforeEach()` pattern from `tests/Feature/FacadeTest.php`:

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
- `Foxen\CancellationToken\Tests\Fixtures\TestBooking` — uses `test_bookings` table, `$guarded = ['*']`, no timestamps, uses `HasCancellationTokens` trait

### Testing via Validator::make (AC 5 + Form Request Simulation)

To test that validation integrates correctly with Laravel's validation system:

```php
it('fails with a generic error message', function () {
    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => 'ct_nonexistent'],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeTrue();
    expect($rule->failureReason)->toBe(TokenVerificationFailure::NotFound);

    $message = $validator->errors()->first('token');
    expect($message)->not->toContain('expired');
    expect($message)->not->toContain('consumed');
    expect($message)->not->toContain('not found');
});
```

For the passing case:

```php
it('passes validation for a valid token', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user);

    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => $plainToken],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeFalse();
    expect($rule->failureReason)->toBeNull();
});
```

### Previous Story Context (3.2)

From Story 3.2 completion:
- Facade and Service Provider binding tested and verified
- 101 total tests pass across all test files
- PHPStan and Pint pass clean
- Container binding (`CancellationTokenContract` → `CancellationTokenService`) verified
- `src/Rules/` directory does not exist yet — needs to be created for this story

### Known Deferred Issues (Carry Forward)

- **TestCase never sets `app.key`** — all HMAC tests use an empty key (deferred from Story 2.3). Tests are internally consistent.
- **TOCTOU race on consumption** — pre-existing architectural design limitation.
- **Timing oracle on DB token lookup** — spec prescribes this lookup strategy.
- **`beforeEach` migration include path hardcoded** — pre-existing pattern, not introduced by this story.
- **Expiry `toDateTimeString()` comparison** — pre-existing, deferred to Story 6.2.

### Anti-Patterns to Avoid

1. **DO NOT** implement the deprecated `Illuminate\Contracts\Validation\Rule` interface — use `ValidationRule`
2. **DO NOT** expose failure-specific wording in the validation error message — always generic
3. **DO NOT** call `CancellationTokenService` directly — resolve via `CancellationTokenContract`
4. **DO NOT** use `===` or `==` to compare token hashes — always `hash_equals()` (handled by the service layer, but be aware)
5. **DO NOT** log, cache, or store the plain-text token at any point
6. **DO NOT** import the Facade as `CancellationToken` and the Model as `CancellationToken` in the same test file — use aliases
7. **DO NOT** add `getPackageAliases()` to `TestCase.php` — auto-discovery handles it
8. **DO NOT** modify any `src/` files other than creating the new rule class
9. **DO NOT** add event dispatching in the validation rule — events are the service's responsibility
10. **DO NOT** consume the token during validation — the rule only calls `verify()`, not `consume()`

### Project Structure Notes

Only these files should be created:

| File | Action |
|---|---|
| `src/Rules/ValidCancellationToken.php` | CREATE — Laravel validation rule implementing `ValidationRule` |
| `tests/Feature/ValidationRuleTest.php` | CREATE — feature tests for the validation rule |

No changes to existing `src/` files are expected.

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Failure Handling Pattern]
- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.3]
- [Source: _bmad-output/project-context.md#Laravel / Package Rules — Validation]
- [Source: _bmad-output/project-context.md#Critical Don't-Miss Rules — Security Anti-Patterns]
- [Source: vendor/laravel/framework/src/Illuminate/Contracts/Validation/ValidationRule.php — interface definition]

## Dev Agent Record

### Agent Model Used

Claude Sonnet 4.6 (claude-sonnet-4-6)

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- ✅ Created `src/Rules/ValidCancellationToken.php` implementing `ValidationRule` interface
- ✅ Rule resolves `CancellationTokenContract` from container, calls `verify()`, stores failure reason on public property
- ✅ Generic validation message `The :attribute is invalid.` — no failure-specific wording exposed
- ✅ Public `?TokenVerificationFailure $failureReason` property available for developer inspection
- ✅ Created `tests/Feature/ValidationRuleTest.php` with 7 tests covering all 5 ACs
- ✅ All 108 tests pass (101 existing + 7 new), no regressions
- ✅ PHPStan passes clean, Pint passes clean
- ✅ No existing `src/` files modified — only new files created

### File List

- `src/Rules/ValidCancellationToken.php` — CREATED — Laravel validation rule implementing `ValidationRule` interface
- `tests/Feature/ValidationRuleTest.php` — CREATED — feature tests for the validation rule (7 tests)

### Review Findings

- [x] [Review][Decision] Translation key vs hardcoded error string — resolved: adopted `$fail('cancellation-tokens::validation.cancellation_token')->translate()` with `resources/lang/en/validation.php` and `->hasTranslations()` in service provider
- [x] [Review][Patch] Test description "with Managed reason" should be "with Consumed reason" [`tests/Feature/ValidationRuleTest.php:57`] — false positive, file already correct
- [x] [Review][Patch] `failureReason` not reset at start of `validate()` — fixed: added `$this->failureReason = null;` at top of `validate()` [`src/Rules/ValidCancellationToken.php`]
- [x] [Review][Patch] Non-string value passed to `verify(string $plainToken)` causes uncaught TypeError — fixed: added `is_string()` guard before `verify()` call [`src/Rules/ValidCancellationToken.php`]
- [x] [Review][Defer] Uncaught non-TokenVerificationException from `verify()` (DB exceptions, container binding errors, infrastructure failures) [`src/Rules/ValidCancellationToken.php:19`] — deferred, pre-existing

## Change Log

- 2026-04-01: Story 3.3 implementation complete — ValidCancellationToken validation rule with full test coverage
- 2026-04-01: Code review complete — 1 decision needed, 3 patches, 1 deferred, 4 dismissed
