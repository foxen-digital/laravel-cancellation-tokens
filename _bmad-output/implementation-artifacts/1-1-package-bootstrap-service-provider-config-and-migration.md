# Story 1.1: Package Bootstrap — Service Provider, Config, and Migration

Status: done

## Story

As a developer,
I want to install the package and publish its migration and config,
So that the `cancellation_tokens` table is available in my application with zero manual wiring.

## Acceptance Criteria

1. **Auto-discovery** — Given the package is required via Composer, when the application bootstraps, then the service provider is auto-discovered via `composer.json` `extra.laravel` — no manual registration required

2. **Migration publish** — Given the package is installed, when `php artisan vendor:publish --tag=cancellation-tokens-migrations` is run, then the migration file is copied to the application's `database/migrations/` directory

3. **Migration schema** — Given the migration is published, when `php artisan migrate` is run, then the `cancellation_tokens` table exists with: `id` (BIGINT PK), `token` (VARCHAR 255, unique index), `tokenable_type`, `tokenable_id`, `cancellable_type`, `cancellable_id`, `expires_at` (nullable timestamp), `used_at` (nullable timestamp), `created_at`, `updated_at` — and **NO `deleted_at` column**

4. **Config publish** — Given the package is installed, when `php artisan vendor:publish --tag=cancellation-tokens-config` is run, then `config/cancellation-tokens.php` is available in the application with keys `table` (default: `'cancellation_tokens'`), `prefix` (default: `'ct_'`), and `default_expiry` (default: `10080`)

5. **Config customization** — Given config is published and `table`, `prefix`, or `default_expiry` are changed, when the application runs, then the package reads and uses the customized values

6. **Custom table name** — Given the config is published and the `table` value is changed to a custom name, when `php artisan migrate` is run, then the table is created using the custom name, not the default `cancellation_tokens`

## Tasks / Subtasks

- [x] **Task 1: Configure Service Provider** (AC: 1, 2, 4)
  - [x] 1.1 Update `src/CancellationTokenServiceProvider.php` to extend `Spatie\LaravelPackageTools\PackageServiceProvider`
  - [x] 1.2 Configure package name, config file, and migration in `configurePackage()` method
  - [x] 1.3 Remove `->hasViews()` call (package ships no views)
  - [x] 1.4 Register the `CancellationTokenContract` binding in the service container (bind to `CancellationTokenService` — service class will be created in Story 2.1, but the binding setup belongs here)

- [x] **Task 2: Create Config File** (AC: 4, 5, 6)
  - [x] 2.1 Update `config/cancellation-tokens.php` with the required schema
  - [x] 2.2 Add `table` key with default `'cancellation_tokens'`
  - [x] 2.3 Add `prefix` key with default `'ct_'`
  - [x] 2.4 Add `default_expiry` key with default `10080` (minutes = 7 days)

- [x] **Task 3: Create Migration File** (AC: 2, 3, 6)
  - [x] 3.1 Create migration file in `database/migrations/` with naming pattern `create_cancellation_tokens_table.php`
  - [x] 3.2 Define schema: `id` BIGINT unsigned auto-increment PK, `token` VARCHAR(255) unique indexed, `tokenable_type`/`tokenable_id` morph columns, `cancellable_type`/`cancellable_id` morph columns, `expires_at` nullable timestamp, `used_at` nullable timestamp, standard timestamps
  - [x] 3.3 Use table name from config: `config('cancellation-tokens.table')` in the migration
  - [x] 3.4 **NO soft deletes** — do not add `deleted_at` column

- [x] **Task 4: Fix Facade Accessor** (AC: 1)
  - [x] 4.1 Update `src/Facades/CancellationToken.php` facade accessor to resolve against `CancellationTokenContract` (not the concrete class)
  - [x] 4.2 The accessor string should be the contract interface class name

- [x] **Task 5: Feature Tests** (AC: 1-6)
  - [x] 5.1 Create `tests/Feature/PackageBootstrapTest.php`
  - [x] 5.2 Test migration publishes correctly
  - [x] 5.3 Test config publishes correctly with expected keys and defaults
  - [x] 5.4 Test migration creates expected table schema
  - [x] 5.5 Test custom table name from config is used

## Dev Notes

### Critical Architecture Rules

**Namespace:** `Foxen\CancellationToken` — the architecture doc originally stated `FoxenDigital\LaravelCancellationTokens` but this was corrected in AR4a. All implementation must use `Foxen\CancellationToken` as the root namespace.

**PSR-4 autoloading:** `Foxen\CancellationToken\` → `src/`

**Service Provider Pattern:** Extend `Spatie\LaravelPackageTools\PackageServiceProvider` — NOT Laravel's base `ServiceProvider`. Register migration, config via `configurePackage(Package $package)`.

**No Views:** Remove `->hasViews()` from the service provider. The package ships no views, no lang files, no assets. No `resources/` directory should exist.

### Migration Schema Details

```php
Schema::create(config('cancellation-tokens.table'), function (Blueprint $table) {
    $table->id(); // BIGINT unsigned auto-increment PK
    $table->string('token', 255)->unique(); // HMAC-SHA256 hash storage, unique indexed
    $table->morphs('tokenable'); // tokenable_type, tokenable_id with index
    $table->morphs('cancellable'); // cancellable_type, cancellable_id with index
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('used_at')->nullable();
    $table->timestamps();
    // NO deleted_at / soft deletes
});
```

**Why no soft deletes?** `used_at` and `expires_at` serve as the audit trail for consumed and expired tokens. `Prunable` handles cleanup. Adding `deleted_at` would be redundant.

### Config Schema

```php
// config/cancellation-tokens.php
return [
    'table'          => 'cancellation_tokens',
    'prefix'         => 'ct_',
    'default_expiry' => 10080, // minutes — 7 days
];
```

### Service Container Binding

The service provider must bind the contract to the concrete implementation:

```php
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\CancellationTokenService;

public function packageBooted(): void
{
    $this->app->bind(CancellationTokenContract::class, CancellationTokenService::class);
}
```

**Note:** `CancellationTokenService` and `CancellationTokenContract` will be created in Story 2.1. For this story, set up the binding structure but comment out or skip if the classes don't exist yet.

### Facade Accessor Fix

Current (incorrect):
```php
protected static function getFacadeAccessor(): string
{
    return \Foxen\CancellationToken\CancellationToken::class;
}
```

Required (correct):
```php
use Foxen\CancellationToken\Contracts\CancellationTokenContract;

protected static function getFacadeAccessor(): string
{
    return CancellationTokenContract::class;
}
```

The facade must resolve against the contract, not the concrete service. This enables `CancellationToken::fake()` to swap in the test double.

### Testing Conventions (Pest)

- Use `it('...')` syntax — NOT `test()`
- Use `beforeEach()` — NOT `setUp()`
- Use `expect()` assertions — NOT PHPUnit `assertX()` where Pest equivalents exist
- Feature tests use `Orchestra\Testbench\TestCase` + `RefreshDatabase` trait
- Test descriptions are lowercase sentences: `it('publishes the migration file')`

### Existing Files (from spatie skeleton)

These files already exist and need modification:
- `src/CancellationTokenServiceProvider.php` — needs proper package configuration
- `src/Facades/CancellationToken.php` — needs accessor fix
- `config/cancellation-tokens.php` — exists but empty, needs content

These files need to be created:
- `database/migrations/create_cancellation_tokens_table.php`

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#Data Architecture]
- [Source: _bmad-output/planning-artifacts/architecture.md#Service Architecture]
- [Source: _bmad-output/planning-artifacts/architecture.md#Project Structure]
- [Source: _bmad-output/project-context.md#Laravel / Package Rules]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 1.1]

## Dev Agent Record

### Agent Model Used

Claude glm-5

### Debug Log References

N/A

### Completion Notes List

- Configured service provider to extend PackageServiceProvider with proper package name, config file, and migration registration
- Removed hasViews() call as package ships no views
- Added service container binding for CancellationTokenContract → CancellationTokenService
- Created config file with table, prefix, and default_expiry keys
- Created migration with proper schema (no soft deletes, uses config for table name)
- Fixed facade accessor to resolve against CancellationTokenContract
- Created stub CancellationTokenContract interface and CancellationTokenService class for container binding
- Created TokenVerificationException stub for PHPStan compliance
- Created comprehensive feature tests for all acceptance criteria

### File List

- src/CancellationTokenServiceProvider.php (modified)
- src/CancellationTokenService.php (created)
- src/Contracts/CancellationTokenContract.php (created)
- src/Exceptions/TokenVerificationException.php (created)
- src/Facades/CancellationToken.php (modified)
- config/cancellation-tokens.php (modified)
- database/migrations/create_cancellation_tokens_table.php (created)
- tests/Feature/PackageBootstrapTest.php (created)
- src/CancellationToken.php (deleted - empty class no longer needed)

## Review Findings

### Decision Needed

_(all resolved)_

### Patches

- [x] [Review][Patch] Migration `up()`/`down()` use `config()` with no fallback — returns null if config not published [database/migrations/create_cancellation_tokens_table.php:11,24]
- [x] [Review][Patch] `CancellationTokenService::create()` returns `''` instead of throwing `RuntimeException('Not implemented')` like the other stubs [src/CancellationTokenService.php:13]
- [x] [Review][Patch] Feature tests lack `RefreshDatabase` trait — schema tests create tables via `$migration->up()` with no teardown, polluting subsequent tests [tests/Feature/PackageBootstrapTest.php]
- [x] [Review][Patch] `it('resolves the facade accessor correctly')` only checks method metadata via reflection, never calls `getFacadeAccessor()` to assert the return value equals `CancellationTokenContract::class` [tests/Feature/PackageBootstrapTest.php:115-121]
- [x] [Review][Patch] Missing explicit `use Foxen\CancellationToken\CancellationTokenService` import in service provider — implicit same-namespace resolution works but is inconsistent with how `CancellationTokenContract` is imported [src/CancellationTokenServiceProvider.php]
- [x] [Review][Patch] Schema test does not assert `token` column is VARCHAR(255) — only checks column existence, not its length constraint specified in AC 3 [tests/Feature/PackageBootstrapTest.php:56-84]

### Deferred

- [x] [Review][Defer] Nullable `$tokenable` vs non-nullable `morphs('tokenable')` in schema — null $tokenable MUST be handled during implementation of story 2.3
- [x] [Review][Defer] Migration `down()` reads live config at rollback time — potential config drift MUST be documented in README at story 6.3
- [x] [Review][Defer] No index on `expires_at` or `used_at` [database/migrations/create_cancellation_tokens_table.php] — deferred, performance optimization not required by story scope
- [x] [Review][Defer] Hardcoded absolute local path in `_bmad/core/config.yaml` [_bmad/core/config.yaml] — deferred, BMAD tooling config outside story scope
- [x] [Review][Defer] `TokenVerificationException` has no error code or context structure [src/Exceptions/TokenVerificationException.php] — deferred, exception detail is a concern for Story 2.x when it is actually thrown

## Change Log

- 2026-03-28: Completed Story 1.1 - Package Bootstrap
- 2026-03-28: Code review — 2 decision-needed, 6 patch, 3 deferred, 7 dismissed
