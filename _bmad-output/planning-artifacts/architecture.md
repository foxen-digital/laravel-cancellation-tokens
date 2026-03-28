---
stepsCompleted:
  - step-01-init
  - step-02-context
  - step-03-starter
  - step-04-decisions
  - step-05-patterns
  - step-06-structure
  - step-07-validation
  - step-08-complete
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/product-brief-laravel-cancellation-tokens.md
  - _bmad-output/planning-artifacts/product-brief-laravel-cancellation-tokens-distillate.md
  - docs/laravel-cancellation-token-package.md
workflowType: 'architecture'
lastStep: 8
status: 'complete'
completedAt: '2026-03-28'
project_name: 'laravel-cancellation-tokens'
user_name: 'Mrdth'
date: '2026-03-28'
notes:
  - 'Project will be scaffolded using spatie/package-laravel-cancellation-tokens-laravel'
---

# Architecture Decision Document

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Project Context Analysis

### Requirements Overview

**Functional Requirements (38 total):**

- Token Lifecycle: create, verify, consume, retrieve actor and subject (FR1–FR6)
- Token Security: hash storage only, plain-text returned once, prefix, single-use enforcement, time expiry, timing-safe comparison (FR7–FR12)
- Model Trait: `HasCancellationTokens` for cancellable models, polymorphic `tokenable` + `cancellable` associations (FR13–FR17)
- Facade API: static `create`/`verify`/`consume`, swappable implementation for tests (FR18–FR19)
- Validation: `ValidCancellationToken` rule with distinguishable not-found / expired / consumed failure states (FR20–FR22)
- Events: `TokenCreated`, `TokenVerified`, `TokenConsumed`, `TokenExpired` (FR23–FR26)
- Cleanup: `Prunable` on model, `model:prune` integration, configurable criteria (FR27–FR28)
- Testing: `CancellationTokenFake` (no DB), assertion helpers, `CancellationTokenFactory` (FR29–FR32)
- Package setup: publishable migration and config, table name / prefix / expiry configuration, auto-discovery (FR33–FR38)

**Non-Functional Requirements:**

- Performance: single DB write on create, single indexed lookup on verify (hash column), HMAC-SHA256 (bcrypt explicitly prohibited), chunked pruning
- Security: no plain-text persistence ever, HMAC-SHA256 keyed with `APP_KEY`, `hash_equals()` mandatory, 64 bytes entropy post-prefix, failure reasons available internally but not distinguishable externally for enumeration protection
- Integration: PHP 8.3+, Laravel 12/13, no third-party dependencies, no morph map required, auto-discovery, no side effects without trait adoption
- Quality: ≥95% line coverage, `CancellationTokenFactory` ships with package

**Scale & Complexity:**

- Primary domain: PHP/Laravel Composer package
- Complexity level: Low — focused lifecycle operations, one migration, no web layer
- Estimated architectural components: ~8 (Model, Service, Facade, Trait, Validation Rule, Events ×4, Factory, ServiceProvider, Config, Migration)

### Technical Constraints & Dependencies

- **Scaffolding:** `spatie/package-laravel-cancellation-tokens-laravel` — defines directory layout, Pest test runner, Orchestra Testbench, CI pipeline; architecture decisions must align
- **PHP 8.3+** — readonly properties, typed constants, match expressions available
- **Laravel 12/13** — current Eloquent, event, prunable, and validation APIs
- **Zero external dependencies** — only `illuminate/*` framework packages
- **No custom Artisan commands** — cleanup via `model:prune` only
- **No routes, views, or controllers** shipped — consuming application owns those

### Cross-Cutting Concerns Identified

1. **Token hashing** — HMAC-SHA256 + `APP_KEY` used at creation and at every verification; must live in one place, used by service and fake alike
2. **Polymorphic associations** — `tokenable` + `cancellable` morph columns cut across migration, model, trait, and facade
3. **Shared contract (interface)** — `CancellationTokenFake` must satisfy the same interface as the real service to enable transparent test substitution
4. **Error classification** — not-found / expired / consumed states must be a shared value type (enum) consumed by the service, validation rule, and event dispatcher
5. **Configuration** — table name, prefix, default expiry feed into migration, model, service, and factory

## Starter Template Evaluation

### Primary Technology Domain

PHP/Laravel Composer package — no web layer, no frontend, pure library distribution via Packagist.

### Starter Options Considered

User-specified: `spatie/package-laravel-cancellation-tokens-laravel` (2.4k GitHub stars, actively maintained). No evaluation of alternatives required.

### Selected Starter: spatie/package-laravel-cancellation-tokens-laravel

**Rationale for Selection:** Industry-standard Laravel package scaffold. Establishes conventions the Laravel community recognises, with Spatie's full tooling suite pre-wired.

**Initialization Command:**

```bash
composer create-project spatie/package-laravel-cancellation-tokens-laravel laravel-cancellation-tokens --prefer-dist
cd laravel-cancellation-tokens
php configure.php
```

**configure.php selections:** Enable Pint, PHPStan, and Dependabot. Decline Ray.

**Architectural Decisions Provided by Starter:**

**Language & Runtime:**
- PHP 8.x, PSR-4 autoloading: `FoxenDigital\LaravelCancellationTokens\` → `src/`

**Service Provider:**
- `spatie/laravel-package-tools` — `PackageServiceProvider` base handles migration, config, and asset publication via `configurePackage()`

**Testing Framework:**
- **Pest 4.x** (`pestphp/pest`, `pest-plugin-arch`, `pest-plugin-laravel`)
- Orchestra Testbench 10.x for Laravel integration
- `composer test` → `vendor/bin/pest`
- `composer test-coverage` → `vendor/bin/pest --coverage`

**Static Analysis:**
- Larastan 3.x + PHPStan deprecation rules
- `composer analyse` → `vendor/bin/phpstan analyse`

**Code Quality:**
- Laravel Pint (enabled) — formatting enforced via CI
- Dependabot (enabled) — automated dependency updates

**CI/CD:**
- GitHub Actions: `run-tests.yml` (PHP/Laravel version matrix), `fix-php-code-style-issues.yml`
- Automatic changelog updater (optional — decision deferred)

**Code Organization:**
- `src/` — all package classes
- `config/` — publishable config stub
- `database/migrations/` — publishable migration stubs
- `database/factories/` — model factory stubs
- `tests/` — Pest test files
- `.github/workflows/` — CI pipeline

**Note:** Project initialization via `configure.php` should be the first implementation story.

## Core Architectural Decisions

### Decision Priority Analysis

**Critical Decisions (Block Implementation):**
- Token generation strategy and encoding
- Service architecture pattern
- Error classification type
- Database schema key/delete strategy

**Important Decisions (Shape Architecture):**
- CI version matrix

**Deferred Decisions (Post-MVP):**
- None identified — all critical decisions resolved

### Data Architecture

**Primary Key:** BIGINT auto-increment (`id`). The token string is the public identifier; the PK is never exposed, so enumeration risk is zero.

**Soft Deletes:** None. `used_at` and `expires_at` serve as the audit trail for consumed and expired tokens respectively. `Prunable` handles cleanup. Adding `deleted_at` would be redundant.

**Schema (cancellation_tokens):**
- `id` — BIGINT unsigned, auto-increment, primary key
- `token` — VARCHAR(255), unique indexed — stores HMAC-SHA256 hash only
- `tokenable_type` / `tokenable_id` — polymorphic morph columns (actor)
- `cancellable_type` / `cancellable_id` — polymorphic morph columns (subject)
- `expires_at` — TIMESTAMP nullable
- `used_at` — TIMESTAMP nullable
- `created_at` / `updated_at` — standard Laravel timestamps
- Index on `token` (lookup key)
- No soft-delete column

**Migration:** Published via `vendor:publish --tag=cancellation-tokens-migrations`. Table name configurable via config.

### Token Generation & Security

**Generator:** `Str::random(64)` — backed by `random_bytes()`, cryptographically secure, URL-safe alphanumeric output. No hex or base64 encoding step required.

**Token format:** `{prefix}{Str::random(64)}` — e.g. `ct_abc123...` (67 chars total at default prefix length). Prefix is configurable.

**Storage:** Token is hashed immediately on creation via HMAC-SHA256 keyed with `APP_KEY`. The plain-text value is returned to the caller once and never persisted.

**Verification:** `hash_equals(hash_hmac('sha256', $plainToken, config('app.key')), $storedHash)`

### Service Architecture

**Pattern:** Lean service — `CancellationTokenService` interacts with the `CancellationToken` Eloquent model directly. No repository layer.

**Rationale:** The `CancellationTokenFake` is the test isolation boundary (FR19, FR29). A repository would add indirection without adding testability value given the fake already covers that seam.

**Contract:** `CancellationTokenService` and `CancellationTokenFake` both implement a shared `CancellationTokenContract` interface. The Facade accessor resolves against this contract.

### Error Classification

**Type:** PHP 8.1+ Backed Enum — `TokenVerificationFailure`

```php
enum TokenVerificationFailure: string
{
    case NotFound = 'not_found';
    case Expired  = 'expired';
    case Consumed = 'consumed';
}
```

Consumed by the service layer, `ValidCancellationToken` rule (for failure reason surfacing), and event dispatching. Exhaustive `match` enforced by the type system.

### Infrastructure & Deployment

**CI Version Matrix:** PHP 8.3 / 8.4 × Laravel 12 / 13 — pre-configured by `spatie/package-laravel-cancellation-tokens-laravel`. No changes required.

**Code Quality Gates (CI):**
- `composer test` — Pest 4.x full suite
- `composer test-coverage` — coverage report (target ≥95%)
- `composer analyse` — Larastan 3.x
- Pint formatting check on PR

### Decision Impact Analysis

**Implementation Sequence:**
1. Migration schema (foundation for everything)
2. `CancellationTokenContract` interface (service + fake share this)
3. `CancellationToken` Eloquent model + `TokenVerificationFailure` enum
4. `CancellationTokenService` (core logic)
5. `CancellationTokenFake` (test double)
6. `HasCancellationTokens` trait
7. `CancellationToken` Facade
8. `ValidCancellationToken` rule
9. Events (4)
10. `CancellationTokenFactory`
11. `CancellationTokenServiceProvider`

**Cross-Component Dependencies:**
- Enum → Service, Rule, Events (all depend on it)
- Contract → Service, Fake, Facade (all depend on it)
- Config → Service, Model, Factory, Migration (table name + prefix + expiry)
- APP_KEY → Service hashing (runtime dependency, not package dependency)

## Implementation Patterns & Consistency Rules

### Pattern Categories Defined

**Critical Conflict Points Identified:** 5 areas where AI agents could make different choices without explicit guidance.

### Namespace / Directory Structure

All agents must place classes in the following locations. No deviation:

```
src/
├── CancellationTokenService.php
├── CancellationTokenServiceProvider.php
├── Contracts/
│   └── CancellationTokenContract.php
├── Enums/
│   └── TokenVerificationFailure.php
├── Events/
│   ├── TokenCreated.php
│   ├── TokenVerified.php
│   ├── TokenConsumed.php
│   └── TokenExpired.php
├── Exceptions/
│   └── TokenVerificationException.php
├── Facades/
│   └── CancellationToken.php
├── Models/
│   └── CancellationToken.php
├── Rules/
│   └── ValidCancellationToken.php
├── Testing/
│   └── CancellationTokenFake.php
└── Traits/
    └── HasCancellationTokens.php
```

### Service Method Return Types

The `CancellationTokenContract` interface defines the exact signatures all agents must follow:

```php
interface CancellationTokenContract
{
    // Returns plain-text token string — the only moment it exists unencrypted
    public function create(
        Model $cancellable,
        Model $tokenable,
        Carbon $expiresAt,
    ): string;

    // Returns CancellationToken model on success; throws TokenVerificationException on failure
    public function verify(string $plainToken): CancellationToken;

    // Returns CancellationToken model on success; throws TokenVerificationException on failure
    public function consume(string $plainToken): CancellationToken;
}
```

**Anti-pattern:** Returning `null`, `bool`, or `Result` objects from `verify()` or `consume()`. These methods always succeed or throw.

### Failure Handling Pattern

**Pattern:** Throw `TokenVerificationException` carrying a `TokenVerificationFailure` enum case.

```php
// Throwing
throw new TokenVerificationException(TokenVerificationFailure::Expired);

// Catching and inspecting
try {
    $token = CancellationToken::verify($plainToken);
} catch (TokenVerificationException $e) {
    $e->reason; // TokenVerificationFailure enum case
}
```

`ValidCancellationToken` catches `TokenVerificationException` internally and surfaces `$e->reason` as the distinguishable failure state (FR21/FR22). Failure reasons must never be exposed externally in a way that enables enumeration — the enum is for internal developer use only.

**Anti-pattern:** Returning `TokenVerificationFailure` cases directly from service methods, or using nullable return types to signal failure.

### Event Payload Structure

All events are `readonly` classes with public constructor properties (no getters):

```php
readonly class TokenCreated
{
    public function __construct(
        public CancellationToken $token,
    ) {}
}

// Same structure for TokenVerified, TokenConsumed, TokenExpired
```

`TokenExpired` fires when an expired token is presented to `verify()` or `consume()`, before the `TokenVerificationException` is thrown. The model is accessible (hydrated from DB); `used_at` is null, `expires_at` is in the past.

**Event dispatch order:** Events fire before the exception is thrown on failure paths.

### Pest Test Conventions

**Directory structure:**

```
tests/
├── ArchTest.php              # pest-plugin-arch architecture assertions
├── TestCase.php              # extends Orchestra\Testbench\TestCase
├── Unit/                     # fast, no DB — use CancellationTokenFake
│   ├── ServiceTest.php
│   ├── FakeTest.php
│   └── RuleTest.php
└── Feature/                  # real SQLite in-memory DB
    ├── TokenLifecycleTest.php
    ├── PrunableTest.php
    └── TraitTest.php
```

**Style rules:**
- Use `it('...')` — not `test('...')`
- Use `beforeEach()` — not `setUp()`
- Use `expect()` assertions — not `assertX()` where Pest equivalents exist
- Unit tests must not hit the database — use `CancellationTokenFake` for isolation
- Feature tests use `RefreshDatabase` trait

**Anti-pattern:** Database access in Unit tests. Mixing `assertX()` and `expect()` styles within the same file.

### Naming Conventions

| Element | Convention | Example |
|---|---|---|
| PHP classes | PascalCase | `CancellationTokenService` |
| Methods | camelCase | `createToken()` |
| DB columns | snake_case | `cancellable_type`, `used_at` |
| Config keys | snake_case | `default_expiry` |
| Event classes | PascalCase, past-tense verb + noun | `TokenConsumed` |
| Enum cases | PascalCase | `TokenVerificationFailure::Consumed` |
| Test descriptions | lowercase sentence | `it('rejects an expired token')` |

### Enforcement Guidelines

**All AI agents MUST:**
- Place new classes in the defined namespace/directory map above
- Never return `null` from `verify()` or `consume()` — always throw
- Never store or log the plain-text token string at any point
- Use `hash_equals()` for all token hash comparisons — never `===`
- Dispatch events before throwing exceptions on failure paths
- Use `it()` syntax and `expect()` assertions in all Pest test files

**Pattern verification:** `tests/ArchTest.php` enforces structural rules via `pest-plugin-arch` (e.g., nothing in `src/` uses `===` on token strings, `Testing/` classes are only loaded in test environments).

## Project Structure & Boundaries

### Complete Project Directory Structure

```
laravel-cancellation-tokens/
├── .github/
│   └── workflows/
│       ├── run-tests.yml                        # PHP 8.3/8.4 × Laravel 12/13 matrix
│       └── fix-php-code-style-issues.yml        # Pint auto-fix on PR
├── config/
│   └── cancellation-tokens.php                  # table, prefix, default_expiry
├── database/
│   ├── factories/
│   │   └── CancellationTokenFactory.php         # FR32 — model factory for feature tests
│   └── migrations/
│       └── create_cancellation_tokens_table.php # FR33 — publishable
├── src/
│   ├── CancellationTokenService.php             # FR1–FR12 — core lifecycle + security
│   ├── CancellationTokenServiceProvider.php     # FR38 — auto-discovery, binds contract
│   ├── Contracts/
│   │   └── CancellationTokenContract.php        # FR19 — shared interface (service + fake)
│   ├── Enums/
│   │   └── TokenVerificationFailure.php         # FR21/FR22 — NotFound, Expired, Consumed
│   ├── Events/
│   │   ├── TokenCreated.php                     # FR23
│   │   ├── TokenVerified.php                    # FR24
│   │   ├── TokenConsumed.php                    # FR25
│   │   └── TokenExpired.php                     # FR26
│   ├── Exceptions/
│   │   └── TokenVerificationException.php       # carries TokenVerificationFailure case
│   ├── Facades/
│   │   └── CancellationToken.php                # FR18 — static API, delegates to contract
│   ├── Models/
│   │   └── CancellationToken.php                # Eloquent model, Prunable (FR27)
│   ├── Rules/
│   │   └── ValidCancellationToken.php           # FR20–FR22 — Laravel validation rule
│   ├── Testing/
│   │   └── CancellationTokenFake.php            # FR29–FR31 — in-memory test double
│   └── Traits/
│       └── HasCancellationTokens.php            # FR13–FR17 — on cancellable models
├── tests/
│   ├── ArchTest.php                             # pest-plugin-arch structural assertions
│   ├── TestCase.php                             # extends Orchestra\Testbench\TestCase
│   ├── Unit/                                    # no DB; uses CancellationTokenFake
│   │   ├── ServiceTest.php
│   │   ├── FakeTest.php
│   │   └── RuleTest.php
│   └── Feature/                                 # real SQLite in-memory; RefreshDatabase
│       ├── TokenLifecycleTest.php
│       ├── PrunableTest.php
│       └── TraitTest.php
├── .editorconfig
├── .gitattributes
├── .gitignore
├── CHANGELOG.md
├── composer.json
├── LICENSE.md
├── phpstan.neon.dist
├── pint.json
└── README.md
```

### Architectural Boundaries

**Package boundary:** Everything inside `src/` is the package's public and internal surface. Only these classes form the public API:
- `CancellationToken` Facade
- `HasCancellationTokens` trait
- `ValidCancellationToken` rule
- Events (4)
- `CancellationTokenContract` interface
- `CancellationTokenFake` (test environments only)
- `TokenVerificationException` + `TokenVerificationFailure` enum

Everything else (`CancellationTokenService`, `CancellationToken` model internals) is implementation detail — consumers should never depend on it directly.

**Test double boundary:** `CancellationTokenFake` lives in `src/Testing/` (not `tests/`) so it can be included by consuming applications in their own test suites via `require-dev`. It must never be loaded in production — enforced by the ArchTest.

**Config boundary:** `config/cancellation-tokens.php` is the only surface for consumer configuration. Internals read via `config('cancellation-tokens.table')` etc. No env vars read directly by the package.

### Requirements to Structure Mapping

| FR Category | Primary Files |
|---|---|
| Token Lifecycle (FR1–FR6) | `CancellationTokenService`, `CancellationTokenContract` |
| Token Security (FR7–FR12) | `CancellationTokenService` (hashing, `hash_equals`) |
| Model Trait (FR13–FR17) | `HasCancellationTokens`, `Models/CancellationToken` |
| Facade API (FR18–FR19) | `Facades/CancellationToken`, `CancellationTokenContract` |
| Validation (FR20–FR22) | `Rules/ValidCancellationToken`, `Enums/TokenVerificationFailure` |
| Events (FR23–FR26) | `Events/Token*.php` |
| Cleanup (FR27–FR28) | `Models/CancellationToken` (Prunable) |
| Testing (FR29–FR31) | `Testing/CancellationTokenFake` |
| Factory (FR32) | `database/factories/CancellationTokenFactory` |
| Package Setup (FR33–FR38) | `CancellationTokenServiceProvider`, `config/`, `database/migrations/` |

### Integration Points & Data Flow

**Happy path (token creation):**
```
Consuming app
  → CancellationToken::create($cancellable, $tokenable, $expiresAt)   [Facade]
  → CancellationTokenContract::create()                                [Contract]
  → CancellationTokenService::create()                                 [Service]
    → Str::random(64) prefixed, HMAC-SHA256 hashed
    → CancellationToken::create([...])                                 [Model → DB]
    → event(new TokenCreated($token))
  → returns plain-text token string to caller (only occurrence)
```

**Happy path (verification):**
```
Consuming app
  → CancellationToken::verify($plainToken)
  → CancellationTokenService::verify()
    → hash_equals(hash_hmac(...), $stored->token)                      [DB lookup by hash]
    → checks expires_at, used_at
    → event(new TokenVerified($token))
  → returns CancellationToken model
```

**Failure path:**
```
  → expired token presented
  → event(new TokenExpired($token))                                    [before throw]
  → throw new TokenVerificationException(TokenVerificationFailure::Expired)
  → ValidCancellationToken catches, surfaces $e->reason internally
```

**Test substitution:**
```
CancellationToken::fake()
  → swaps CancellationTokenContract binding in service container
  → CancellationTokenFake intercepts all calls in-memory
  → assertion helpers available: assertTokenCreatedFor(), assertTokenConsumed()
```

### File Organization Patterns

**Config files at root:** `phpstan.neon.dist`, `pint.json`, `composer.json` — no subdirectory nesting for root tooling config.

**Published assets** (consumed app receives via `vendor:publish`):
- Migration → app's `database/migrations/`
- Config → app's `config/cancellation-tokens.php`

**No `resources/` directory** — package ships no views, lang files, or assets.

## Architecture Validation Results

### Coherence Validation ✅

**Decision Compatibility:**
- PHP 8.3+ satisfies all feature requirements: backed enums (8.1+), readonly classes (8.2+), typed constants (8.3+)
- Pest 4.x + Orchestra Testbench 10.x + Laravel 12/13 are mutually compatible
- `spatie/laravel-package-tools` supports Laravel 12/13 service provider pattern
- Larastan 3.x targets PHP 8.x + Laravel 12/13 — compatible
- No version conflicts detected

**Pattern Consistency:**
- Exception-based failure pattern flows coherently from service → validation rule → events
- `TokenVerificationFailure` enum is the single source of truth across all three consumers
- Contract interface method signatures are consistent with data flow diagrams
- Pest `it()` / `expect()` style rule matches test file structure

**Structure Alignment:**
- `src/Testing/` boundary enforces fake-not-in-production requirement, verified by ArchTest
- Config boundary (no direct env reads) is structurally enforced
- No `resources/` directory — consistent with no views/lang/assets scope decision

### Requirements Coverage Validation ✅

All 38 FRs architecturally supported (mapped in Project Structure section).

**Non-Functional Requirements:**
- Performance: single DB write, single indexed lookup, HMAC-SHA256 (not bcrypt), chunked `Prunable` — all addressed
- Security: plain-text never persisted (pattern rule + ArchTest), `hash_equals()` mandatory, `Str::random(64)` via `random_bytes()`, failure reasons internal-only — all addressed
- Integration: auto-discovery, no morph map enforced, no side effects without trait — all addressed
- Quality: 95% coverage target, test structure + factory defined — addressed

### Implementation Readiness Validation ✅

**Decision Completeness:** All 6 major decisions documented with versions and rationale.

**Structure Completeness:** Every file named, every directory defined, all 38 FRs mapped to specific files.

**Pattern Completeness:** All 5 conflict areas resolved, naming convention table covers all element types, 6 mandatory enforcement rules specified.

### Gap Analysis & Resolutions

**Gap 1 — `CancellationTokenFake` assertion API (resolved):**

```php
// Complete assertion API
CancellationTokenFake::assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null): void
CancellationTokenFake::assertTokenConsumed(): void
CancellationTokenFake::assertTokenNotConsumed(): void
CancellationTokenFake::assertNoTokensCreated(): void
```

Expired token assertions are excluded from the fake intentionally — the expired path is tested via `Event::fake()` + `Event::assertDispatched(TokenExpired::class)` in feature tests, or by catching `TokenVerificationException(TokenVerificationFailure::Expired)` in unit tests.

**Gap 2 — Default `pruneQuery()` criteria (resolved):**

Default: delete where `expires_at < now()` **OR** `used_at IS NOT NULL`. Once expired or consumed, a token has no further utility.

**Gap 3 — Config file schema (resolved):**

```php
// config/cancellation-tokens.php
return [
    'table'          => 'cancellation_tokens',
    'prefix'         => 'ct_',
    'default_expiry' => 10080, // minutes — 7 days
];
```

### Architecture Completeness Checklist

**✅ Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed
- [x] Technical constraints identified
- [x] Cross-cutting concerns mapped

**✅ Architectural Decisions**
- [x] Critical decisions documented with versions
- [x] Technology stack fully specified
- [x] Integration patterns defined
- [x] Performance and security considerations addressed

**✅ Implementation Patterns**
- [x] Naming conventions established
- [x] Namespace/directory structure defined
- [x] Service method return types specified
- [x] Failure handling pattern defined
- [x] Event payload structure specified
- [x] Pest test conventions documented

**✅ Project Structure**
- [x] Complete directory structure defined
- [x] Component boundaries established
- [x] Integration points and data flow mapped
- [x] All 38 FRs mapped to specific files

### Architecture Readiness Assessment

**Overall Status:** READY FOR IMPLEMENTATION

**Confidence Level:** High — narrow, well-defined scope with no ambiguous decisions remaining.

**Key Strengths:**
- Every public API method has an exact signature
- Every potential AI agent conflict point is resolved with a concrete rule
- Security model matches Laravel core conventions (password reset pattern)
- Test isolation strategy is clean: fake for unit, real DB for feature

**Areas for Future Enhancement (Post-MVP):**
- `model:prune` cleanup report (Phase 2 — visible pruned token counts)
- Laravel Telescope integration (Phase 3)

### Implementation Handoff

**AI Agent Guidelines:**
- Follow all architectural decisions exactly as documented — no improvisation on patterns
- Use `CancellationTokenContract` as the implementation target, not `CancellationTokenService` directly
- Never store, log, or return a plain-text token after the initial `create()` return
- Dispatch events before throwing exceptions on all failure paths
- All Pest tests use `it()` syntax and `expect()` assertions

**First Implementation Story:** Scaffold the project using `spatie/package-laravel-cancellation-tokens-laravel` with Pint, PHPStan, and Dependabot enabled. Vendor namespace: `FoxenDigital\LaravelCancellationTokens`.
