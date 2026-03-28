---
project_name: 'laravel-cancellation-tokens'
user_name: 'Mrdth'
date: '2026-03-28'
sections_completed: ['technology_stack', 'language_rules', 'framework_rules', 'testing_rules', 'quality_rules', 'workflow_rules', 'anti_patterns']
status: 'complete'
rule_count: 47
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

**Runtime:**
- PHP `^8.3`
- Laravel 11/12 (`illuminate/contracts ^11.0||^12.0`)

**Package Tooling:**
- `spatie/laravel-package-tools` `^1.16` — ServiceProvider base via `PackageServiceProvider`

**Testing:**
- Pest `^4.0` + `pest-plugin-arch ^4.0` + `pest-plugin-laravel ^4.0`
- Orchestra Testbench `^10.0.0||^9.0.0`

**Static Analysis:**
- Larastan `^3.0` + PHPStan level 5 (`phpstan.neon.dist`)
- `phpstan/phpstan-deprecation-rules ^2.0` + `phpstan/phpstan-phpunit ^2.0`

**Code Quality:**
- Laravel Pint `^1.14` (enforced on CI)

**CI Matrix:** PHP 8.3/8.4 × Laravel 12/13 (GitHub Actions)

**Scripts:**
- `composer test` → `vendor/bin/pest`
- `composer test-coverage` → `vendor/bin/pest --coverage`
- `composer analyse` → `vendor/bin/phpstan analyse`
- `composer format` → `vendor/bin/pint`

## Critical Implementation Rules

### PHP Rules

- PHP 8.1+ backed enums are used (`TokenVerificationFailure`) — use `enum Foo: string` syntax, not class-based enums or constants
- Readonly classes are used for events (PHP 8.2+) — all event classes must be declared `readonly`
- Use `match` expressions (not `switch`) for exhaustive `TokenVerificationFailure` case handling — the type system enforces exhaustiveness
- `Str::random(64)` is backed by `random_bytes()` — never use `rand()`, `mt_rand()`, or `uniqid()` for token entropy
- Token hashing: always `hash_hmac('sha256', $plainToken, config('app.key'))` — bcrypt is explicitly prohibited for this use case
- Token comparison: always `hash_equals($stored, $computed)` — never `===` or `==` on hash strings
- Never store, log, or return the plain-text token after the initial `create()` return value
- Config is accessed via `config('cancellation-tokens.key')` — never read `env()` directly inside the package
- PSR-4 autoloading: `Foxen\CancellationToken\` → `src/`

### Laravel / Package Rules

**Service Provider:**
- Extend `Spatie\LaravelPackageTools\PackageServiceProvider` — not Laravel's base `ServiceProvider`
- Register migration, config, and asset publication inside `configurePackage(PackageConfig $package)`
- Bind `CancellationTokenContract` in the service container — the Facade accessor must resolve against the contract, not `CancellationTokenService` directly
- `CancellationToken::fake()` swaps the container binding for `CancellationTokenContract` to `CancellationTokenFake` — no other mechanism is used for test substitution

**Eloquent Model:**
- `CancellationToken` model implements `Prunable` — pruning criteria: `expires_at < now()` OR `used_at IS NOT NULL`
- No soft deletes — `used_at` and `expires_at` are the audit trail; `deleted_at` must not be added
- Primary key is BIGINT auto-increment (`id`) — the token string is the public identifier, PK is never exposed
- Token column stores HMAC-SHA256 hash only — `VARCHAR(255)`, unique indexed

**Polymorphic Relationships:**
- Two morph pairs on the model: `tokenable` (actor) and `cancellable` (subject)
- No morph map is required — consuming applications own their own morph resolution

**Facade:**
- Facade accessor: `CancellationToken` (alias registered in `composer.json` `extra.laravel.aliases`)
- Delegates all calls to `CancellationTokenContract` binding — never calls `CancellationTokenService` directly

**Validation:**
- `ValidCancellationToken` implements Laravel's `ValidationRule` interface
- Catches `TokenVerificationException` internally — surfaces `$e->reason` for distinguishable failure states; must never expose failure reasons externally in ways that enable enumeration

**Events:**
- Events are dispatched via `event()` helper — not `Event::dispatch()`
- Event dispatch fires **before** throwing `TokenVerificationException` on all failure paths
- No event listeners are registered by the package — consuming applications own their own listeners

**Configuration:**
```php
// config/cancellation-tokens.php
return [
    'table'          => 'cancellation_tokens',
    'prefix'         => 'ct_',
    'default_expiry' => 10080, // minutes — 7 days
];
```

### Testing Rules

**Framework & Style:**
- All tests use Pest 4.x — `it('description')` syntax only, never `test()`
- Use `expect()` assertions — not PHPUnit's `assertX()` methods where Pest equivalents exist
- Use `beforeEach()` — not `setUp()`
- Test descriptions are lowercase sentences: `it('rejects an expired token')`

**Directory Structure:**
```
tests/
├── ArchTest.php              # pest-plugin-arch structural assertions
├── TestCase.php              # extends Orchestra\Testbench\TestCase
├── Unit/                     # no DB — use CancellationTokenFake
│   ├── ServiceTest.php
│   ├── FakeTest.php
│   └── RuleTest.php
└── Feature/                  # real SQLite in-memory; uses RefreshDatabase
    ├── TokenLifecycleTest.php
    ├── PrunableTest.php
    └── TraitTest.php
```

**Unit vs Feature Boundary:**
- Unit tests must never hit the database — use `CancellationTokenFake` for all service isolation
- Feature tests use real SQLite in-memory DB via `RefreshDatabase` trait
- Do not mix `assertX()` and `expect()` styles within the same test file

**CancellationTokenFake Assertion API:**
```php
CancellationTokenFake::assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null): void
CancellationTokenFake::assertTokenConsumed(): void
CancellationTokenFake::assertTokenNotConsumed(): void
CancellationTokenFake::assertNoTokensCreated(): void
```

**Expired token assertions** are tested via `Event::fake()` + `Event::assertDispatched(TokenExpired::class)` in feature tests, or by catching `TokenVerificationException(TokenVerificationFailure::Expired)` in unit tests — not via `CancellationTokenFake`.

**Coverage Target:** ≥95% line coverage (`composer test-coverage`)

**ArchTest** enforces structural rules via `pest-plugin-arch` — e.g., nothing in `src/` uses `===` on token strings; `Testing/` classes only loaded in test environments.

### Code Quality & Style Rules

**Formatting:**
- Laravel Pint enforces code style — run `composer format` before committing
- Pint is auto-applied on PRs via `fix-php-code-style-issues.yml` GitHub Actions workflow

**Static Analysis:**
- PHPStan level 5 via Larastan (`composer analyse`)
- Analysed paths: `src/`, `config/`, `database/` (defined in `phpstan.neon.dist`)
- Baseline file: `phpstan-baseline.neon` — do not suppress new errors by adding to baseline; fix the actual issue

**Naming Conventions:**

| Element | Convention | Example |
|---|---|---|
| PHP classes | PascalCase | `CancellationTokenService` |
| Methods | camelCase | `createToken()` |
| DB columns | snake_case | `cancellable_type`, `used_at` |
| Config keys | snake_case | `default_expiry` |
| Event classes | PascalCase, past-tense verb + noun | `TokenConsumed` |
| Enum cases | PascalCase | `TokenVerificationFailure::Consumed` |
| Test descriptions | lowercase sentence | `it('rejects an expired token')` |

**No docblocks** on internal methods unless the logic is genuinely non-obvious — PHPStan + typed signatures provide the contract.

**No `resources/` directory** — the package ships no views, lang files, or assets.

### Development Workflow Rules

**CI Pipeline (GitHub Actions):**
- `run-tests.yml` — PHP 8.3/8.4 × Laravel 12/13 matrix; all combinations must pass
- `fix-php-code-style-issues.yml` — Pint auto-fix runs on PR; do not fight the formatter

**Quality Gates (must all pass before merge):**
1. `composer test` — full Pest suite
2. `composer analyse` — Larastan PHPStan level 5
3. Pint formatting check

**Published Assets (via `vendor:publish`):**
- Migration → consuming app's `database/migrations/`; tag: `cancellation-tokens-migrations`
- Config → consuming app's `config/cancellation-tokens.php`; tag: `cancellation-tokens-config`
- Never auto-publish — always require explicit `vendor:publish` by the consuming app

**No custom Artisan commands** — pruning is handled exclusively via `model:prune` using the `Prunable` trait; do not add a dedicated prune command.

**No routes, views, or controllers** ship with the package — the consuming application owns all of those.

**Dependabot** is enabled for automated dependency updates.

### Critical Don't-Miss Rules

**Security Anti-Patterns (never do these):**
- Never use `===` or `==` to compare token hashes — always `hash_equals()`
- Never store, log, cache, or return the plain-text token after `create()` returns it
- Never use `bcrypt`, `password_hash()`, or any slow hash for token storage — HMAC-SHA256 only
- Never read `env()` directly in package code — always `config('cancellation-tokens.*')`
- Never expose `TokenVerificationFailure` enum cases in HTTP responses — they are for internal developer use only (enumeration protection)

**Service Contract Anti-Patterns:**
- Never return `null`, `bool`, or a `Result` object from `verify()` or `consume()` — always succeed or throw `TokenVerificationException`
- Never depend on `CancellationTokenService` directly — always depend on `CancellationTokenContract`
- Never instantiate `CancellationTokenFake` in production code paths

**Event Anti-Patterns:**
- Never throw `TokenVerificationException` before dispatching the relevant failure event — event fires first, exception throws second
- Never register package event listeners in the ServiceProvider — consuming apps own listeners

**Test Anti-Patterns:**
- Never hit the database in Unit tests — use `CancellationTokenFake`
- Never mix `assertX()` and `expect()` in the same test file
- Never use `test()` instead of `it()` in Pest files

**Structure Anti-Patterns:**
- Never place new classes outside the defined namespace/directory map in the architecture doc
- Never add `deleted_at` / soft deletes to the `CancellationToken` model
- Never add a `resources/` directory to the package
- Never add custom Artisan commands — `model:prune` only

**`CancellationTokenFake` Boundary:**
- Lives in `src/Testing/` (not `tests/`) so consuming apps can include it in their own test suites
- Must never be loaded in production — enforced by ArchTest

---

## Usage Guidelines

**For AI Agents:**
- Read this file before implementing any code in this project
- Follow ALL rules exactly as documented — no improvisation on patterns
- When in doubt, prefer the more restrictive option
- The architecture doc (`_bmad-output/planning-artifacts/architecture.md`) is the authoritative reference for directory structure, method signatures, and data flow

**For Humans:**
- Keep this file lean and focused on agent needs
- Update when technology stack or patterns change
- Remove rules that become obvious over time

_Last Updated: 2026-03-28_
