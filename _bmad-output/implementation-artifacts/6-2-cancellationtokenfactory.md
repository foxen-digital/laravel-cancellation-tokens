# Story 6.2: CancellationTokenFactory

Status: done

## Story

As a developer,
I want a model factory for `CancellationToken`,
so that I can seed realistic token records in feature tests without writing my own factory.

## Acceptance Criteria

1. **Default creation** — Given the package is installed, when `CancellationToken::factory()->create()` is called in a feature test, then a valid token record is persisted with a hashed `token` value, polymorphic morph columns populated, and a future `expires_at`

2. **Consumed state** — Given the factory is used, when `CancellationToken::factory()->consumed()->create()` is called, then the created record has a non-null `used_at` timestamp

3. **Expired state** — Given the factory is used, when `CancellationToken::factory()->expired()->create()` is called, then the created record has an `expires_at` in the past

4. **Morph association** — Given the factory is used, when `CancellationToken::factory()->for($cancellable, 'cancellable')->create()` is called, then the created record is associated with the given cancellable model

## Tasks / Subtasks

- [ ] **Task 1: Add HasFactory trait to CancellationToken model** (AC: 1)
  - [ ] 1.1 Add `use Illuminate\Database\Eloquent\Factories\HasFactory` import to `src/Models/CancellationToken.php`
  - [ ] 1.2 Add `use HasFactory;` trait to the model class (before `use Prunable;`)

- [ ] **Task 2: Create CancellationTokenFactory** (AC: 1, 2, 3, 4)
  - [ ] 2.1 Replace `database/factories/ModelFactory.php` stub with `CancellationTokenFactory.php`
  - [ ] 2.2 Set `$model = CancellationToken::class`
  - [ ] 2.3 Override `newModel()` to use `forceFill()` (bypasses `$guarded = ['*']`)
  - [ ] 2.4 Define `definition()` with defaults: hashed `token`, morph columns, future `expires_at`, null `used_at`
  - [ ] 2.5 Add `consumed()` factory state — sets `used_at` to `now()`
  - [ ] 2.6 Add `expired()` factory state — sets `expires_at` to past timestamp

- [ ] **Task 3: Write feature tests** (AC: 1, 2, 3, 4)
  - [ ] 3.1 Create `tests/Feature/FactoryTest.php` using `RefreshDatabase`
  - [ ] 3.2 Test default `create()` produces valid record with hashed token and future expiry (AC 1)
  - [ ] 3.3 Test `consumed()` state sets `used_at` (AC 2)
  - [ ] 3.4 Test `expired()` state sets past `expires_at` (AC 3)
  - [ ] 3.5 Test `for($cancellable, 'cancellable')` association (AC 4)
  - [ ] 3.6 Test `for($tokenable, 'tokenable')` association (AC 4 extended)
  - [ ] 3.7 Test token hash is HMAC-SHA256 (verifiable with `hash_equals`)

- [ ] **Task 4: Run quality checks** (AC: all)
  - [ ] 4.1 Run `composer test` — all tests pass (no regressions)
  - [ ] 4.2 Run `composer analyse` — PHPStan passes
  - [ ] 4.3 Run `composer format` — Pint passes

## Dev Notes

### CRITICAL: Files to Create and Modify

| File | Action |
|---|---|
| `src/Models/CancellationToken.php` | MODIFY — add `HasFactory` trait |
| `database/factories/ModelFactory.php` | DELETE — replace with `CancellationTokenFactory.php` |
| `database/factories/CancellationTokenFactory.php` | CREATE — model factory with states |
| `tests/Feature/FactoryTest.php` | CREATE — feature tests with RefreshDatabase |

### Model HasFactory Addition

The `CancellationToken` model currently uses only `Prunable`. Add `HasFactory` to enable `CancellationToken::factory()`:

```php
// src/Models/CancellationToken.php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CancellationToken extends Model
{
    use HasFactory;
    use Prunable;
    // ... rest unchanged
}
```

**IMPORTANT:** Add `use HasFactory;` BEFORE `use Prunable;` to follow Laravel convention.

### Factory Implementation Guide

The factory must handle the model's `$guarded = ['*']` policy by overriding `newModel()`:

```php
// database/factories/CancellationTokenFactory.php
namespace Foxen\CancellationToken\Database\Factories;

use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CancellationTokenFactory extends Factory
{
    protected $model = CancellationToken::class;

    public function definition(): array
    {
        $plainToken = config('cancellation-tokens.prefix', 'ct_').Str::random(64);

        return [
            'token' => hash_hmac('sha256', $plainToken, config('app.key')),
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => 1,
            'cancellable_type' => 'App\Models\Booking',
            'cancellable_id' => 1,
            'expires_at' => now()->addDays(7),
            'used_at' => null,
        ];
    }

    public function newModel(array $attributes = []): CancellationToken
    {
        return $this->model::new()->forceFill($attributes);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(7),
        ]);
    }
}
```

### Key Design Decisions

1. **Morph column defaults** — Default to `'App\Models\User'` / `'App\Models\Booking'` as placeholder strings. These are valid database values (just strings + ints) that satisfy the schema. Users override via `for($model, 'cancellable')` or explicit attributes. The factory does NOT reference test fixtures because it ships in `database/factories/` (production autoload), not `tests/`.

2. **`newModel()` override with `forceFill()`** — The model has `$guarded = ['*']` for security (prevents mass-assignment of sensitive columns via HTTP). Factories are the exception: they bypass mass-assignment protection via `forceFill()`. This is standard Laravel practice for guarded models.

3. **Token hash generation** — The factory generates a realistic HMAC-SHA256 hash using the same algorithm as `CancellationTokenService::hashToken()`. This ensures factory-created records are indistinguishable from real service-created records in the database.

4. **No plain-text token exposure** — The factory generates a plain token internally, hashes it, and discards the plain text. It does NOT return or expose the plain-text token. If tests need to verify/consume a factory-created token, they should use the real service to create one instead.

### Factory Name Resolution

`TestCase.php` already configures factory name resolution:

```php
Factory::guessFactoryNamesUsing(
    fn (string $modelName) => 'Foxen\\CancellationToken\\Database\\Factories\\'.class_basename($modelName).'Factory'
);
```

This means `CancellationToken::factory()` resolves to `Foxen\CancellationToken\Database\Factories\CancellationTokenFactory`. No changes needed to `TestCase.php`.

### Morph Association via `for()`

Laravel's Factory `for()` method supports polymorphic `morphTo` relationships:

```php
// Set cancellable association
CancellationToken::factory()
    ->for($booking, 'cancellable')
    ->create();
// Sets cancellable_type = TestBooking::class, cancellable_id = $booking->id

// Set tokenable association
CancellationToken::factory()
    ->for($user, 'tokenable')
    ->create();
// Sets tokenable_type = TestUser::class, tokenable_id = $user->id

// Both
CancellationToken::factory()
    ->for($booking, 'cancellable')
    ->for($user, 'tokenable')
    ->create();
```

### Feature Test Pattern — Uses Database

`tests/Feature/FactoryTest.php` uses `RefreshDatabase` and creates test tables, same as existing feature tests:

```php
<?php

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

it('creates a valid token record with hashed token and future expiry', function () {
    $token = CancellationToken::factory()->create();

    expect($token->token)->toMatch('/^[0-9a-f]{64}$/')   // HMAC-SHA256 hex
        ->and($token->expires_at)->toBeInstanceOf(Carbon::class)
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and($token->used_at)->toBeNull();
});

it('creates a consumed token via state', function () {
    $token = CancellationToken::factory()->consumed()->create();

    expect($token->used_at)->not->toBeNull();
});

it('creates an expired token via state', function () {
    $token = CancellationToken::factory()->expired()->create();

    expect($token->expires_at->isPast())->toBeTrue();
});

it('associates with a cancellable model via for()', function () {
    $booking = TestBooking::create();

    $token = CancellationToken::factory()
        ->for($booking, 'cancellable')
        ->create();

    expect($token->cancellable_type)->toBe(TestBooking::class)
        ->and($token->cancellable_id)->toBe($booking->id);
});
```

### Deleting the Old ModelFactory.php

The existing `database/factories/ModelFactory.php` is a commented-out stub from the spatie skeleton. Delete it and create `CancellationTokenFactory.php` in its place. Do NOT keep both files — the factory directory should contain only the new factory.

### Anti-Patterns to Avoid

1. **DO NOT** reference test fixtures (`TestUser`, `TestBooking`) in the factory — it's in production autoload
2. **DO NOT** expose or return the plain-text token from the factory — hash-only, same as the service
3. **DO NOT** use `bcrypt` or `password_hash()` for the token hash — HMAC-SHA256 only, same as the service
4. **DO NOT** modify `CancellationTokenService`, `CancellationTokenContract`, or `CancellationTokenServiceProvider`
5. **DO NOT** change `$guarded = ['*']` on the model — use `forceFill()` in the factory instead
6. **DO NOT** use `test()` syntax — use `it()` in all Pest tests
7. **DO NOT** use `assertX()` PHPUnit assertions in tests — use Pest `expect()`
8. **DO NOT** use `setUp()` — use `beforeEach()` in test files
9. **DO NOT** place factory in `tests/` — it must be in `database/factories/` for Composer autoload
10. **DO NOT** add the factory to `autoload-dev` — it's already in `autoload` via `database/factories/`

### Previous Story Intelligence (Story 6.1)

- Pint auto-fixes: `yoda_style`, `unary_operator_spaces`, `not_operator_with_space`, `fully_qualified_strict_types` — run `composer format` after implementation
- Test fixture pattern: `TestUser` and `TestBooking` with `$guarded = ['*']`, no timestamps, use `$model->id = 1` or `TestUser::create()` (needs test table)
- Feature test setup pattern: check/create tables in `beforeEach()` (see `TokenCreationTest.php` for reference)
- Factory name guessing already configured in `TestCase.php` — no changes needed there
- The `CancellationToken` model has `$guarded = ['*']` — factory must handle this via `forceFill()`
- All 135 existing tests pass — verify no regressions after changes

### Architecture Compliance

| Requirement | How Addressed |
|---|---|
| NFR23: Package ships with CancellationTokenFactory | Factory in `database/factories/` |
| FR32: Developer can create token records using a model factory | `CancellationToken::factory()->create()` |
| AR12: Pest `it()` syntax, `expect()` assertions | All tests follow convention |
| Token hash: HMAC-SHA256 | Factory uses `hash_hmac('sha256', ...)` |
| Namespace: `Foxen\CancellationToken\` | Factory in `Foxen\CancellationToken\Database\Factories\` |

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.2]
- [Source: _bmad-output/planning-artifacts/architecture.md#CancellationTokenFactory]
- [Source: _bmad-output/project-context.md#Testing Rules]
- [Source: src/Models/CancellationToken.php — model to add HasFactory to]
- [Source: src/CancellationTokenService.php — hash algorithm reference]
- [Source: database/factories/ModelFactory.php — stub to replace]
- [Source: tests/TestCase.php — factory name resolution already configured]
- [Source: tests/Feature/TokenCreationTest.php — feature test pattern reference]

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### File List

### Review Findings

- [x] [Review][Decision] `failOnRisky` downgraded to `false` in phpunit.xml.dist — Intentional: `CancellationTokenFake` assertion methods rely on thrown exceptions rather than PHPUnit assertions; PHPUnit marks these tests as risky with no assertions. Keeping `false` is correct. [phpunit.xml.dist]
- [x] [Review][Patch] HMAC test asserts `toBeFalse()` — trivially true, proves nothing [tests/Feature/FactoryTest.php:74] — Fixed: removed meaningless `toBeFalse()` assertion and renamed test to "stores token as a 64-character lowercase hex string".
- [x] [Review][Defer] `app.key` passed raw to `hash_hmac` (includes `base64:` prefix) [src/CancellationTokenService.php:110, database/factories/CancellationTokenFactory.php:18] — deferred, pre-existing
- [x] [Review][Defer] Factory defaults `tokenable_type`/`cancellable_type` to non-existent `App\Models\User`/`App\Models\Booking` [database/factories/CancellationTokenFactory.php:19-22] — deferred, pre-existing (intentional per spec design decision #1; consumers must use `->for()` to load relations)
- [x] [Review][Defer] `beforeEach` migration guard pattern fragile with `RefreshDatabase` [tests/Feature/FactoryTest.php:12] — deferred, pre-existing (same pattern across all feature tests)
