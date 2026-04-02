---
title: 'Replace app.key with dedicated hash_key config'
type: 'refactor'
created: '2026-04-02'
status: 'done'
baseline_commit: 'da20290'
context:
  - '_bmad-output/project-context.md'
  - '_bmad-output/planning-artifacts/sprint-change-proposal-2026-04-02.md'
  - '_bmad-output/planning-artifacts/architecture.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The package uses `config('app.key')` for HMAC-SHA256 token hashing, which reduces key entropy (raw `base64:` prefix), couples token security to the app encryption key, and silently accepts null/empty keys.

**Approach:** Introduce a dedicated `cancellation-tokens.hash_key` config entry sourced from `CANCELLATION_TOKEN_HASH_KEY` env var. Guard against null/empty values with a `RuntimeException`. Update service, factory, and tests. Update planning artifacts to reflect the change. Resolve 3 deferred work items.

## Boundaries & Constraints

**Always:**
- Use `config('cancellation-tokens.hash_key')` — never `config('app.key')` for token hashing
- Throw `RuntimeException` when hash_key is null or empty (no silent fallback)
- Use `hash_hmac('sha256', $plainToken, $key)` — bcrypt still prohibited

**Ask First:** None — all decisions are in the approved proposal.

**Never:**
- No decoding of base64 prefix — consumer provides the raw key material
- No fallback to `app.key` — the guard exists precisely to prevent this

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Token creation with valid hash_key | `hash_key` set to non-empty string | Token created normally, HMAC hashed with hash_key | N/A |
| Token verify/consume with valid hash_key | Same hash_key used at creation | Token verifies/consumes correctly | N/A |
| Missing hash_key | `config('cancellation-tokens.hash_key')` is null | No token operation succeeds | `RuntimeException`: "A hash key must be configured via cancellation-tokens.hash_key before tokens can be created or verified." |
| Empty hash_key | `config('cancellation-tokens.hash_key')` is `''` | No token operation succeeds | Same `RuntimeException` |

</frozen-after-approval>

## Code Map

- `config/cancellation-tokens.php` -- Package config; add `hash_key` entry
- `src/CancellationTokenService.php` -- `hashToken()` at line 116; change config key + add guard
- `database/factories/CancellationTokenFactory.php` -- `definition()` at line 18; change config key
- `tests/TestCase.php` -- `getEnvironmentSetUp()` at line 32; change config key
- `tests/Feature/TokenCreationTest.php` -- line 137; change config key in hash verification test
- `_bmad-output/project-context.md` -- Update hash rule, config schema, anti-patterns
- `_bmad-output/planning-artifacts/prd.md` -- Security NFR and innovation sections
- `_bmad-output/planning-artifacts/architecture.md` -- 6 sections referencing app.key
- `_bmad-output/planning-artifacts/epics.md` -- Story 2.3 AC updates
- `_bmad-output/implementation-artifacts/deferred-work.md` -- Resolve 3 items

## Tasks & Acceptance

**Execution:**
- [x] `config/cancellation-tokens.php` -- Add `'hash_key' => env('CANCELLATION_TOKEN_HASH_KEY')` entry
- [x] `src/CancellationTokenService.php` -- Update `hashToken()`: read `cancellation-tokens.hash_key`, throw `RuntimeException` on null/empty, hash with new key
- [x] `database/factories/CancellationTokenFactory.php` -- Update `definition()` to use `config('cancellation-tokens.hash_key')`
- [x] `tests/TestCase.php` -- Replace `config()->set('app.key', ...)` with `config()->set('cancellation-tokens.hash_key', ...)`
- [x] `tests/Feature/TokenCreationTest.php` -- Update hash verification test to use `config('cancellation-tokens.hash_key')`
- [x] New test in `tests/Feature/TokenCreationTest.php` -- Assert `RuntimeException` thrown when `hash_key` is null or empty
- [x] `_bmad-output/project-context.md` -- Update hash rule, config schema, add anti-pattern for app.key
- [x] `_bmad-output/planning-artifacts/prd.md` -- Update security NFR and innovation sections: app.key → hash_key
- [x] `_bmad-output/planning-artifacts/architecture.md` -- Update all app.key references to cancellation-tokens.hash_key
- [x] `_bmad-output/planning-artifacts/epics.md` -- Update Story 2.3 AC + add new AC for RuntimeException guard
- [x] `_bmad-output/implementation-artifacts/deferred-work.md` -- Mark 3 items resolved (base64 prefix, empty key, factory mirror), update 1 (key rotation scoped to hash_key)

**Acceptance Criteria:**
- Given `hash_key` is configured, when `create()` is called, then the token hash uses `cancellation-tokens.hash_key` (not `app.key`)
- Given `hash_key` is configured, when `verify()`/`consume()` is called, then the hash computation uses the same `cancellation-tokens.hash_key`
- Given `hash_key` is null or empty, when any token operation is attempted, then `RuntimeException` is thrown with message containing "cancellation-tokens.hash_key"
- Given the test suite runs, then all existing tests pass
- Given PHPStan runs, then analysis passes at level 5
- Given Pint runs, then no formatting changes needed

## Spec Change Log

## Verification

**Commands:**
- `composer test` -- expected: all tests pass (0 failures)
- `composer analyse` -- expected: PHPStan level 5 clean
- `composer format` -- expected: no changes needed

## Suggested Review Order

**Hash key guard & core logic**

- Central hash method — config read, whitespace guard, RuntimeException, HMAC call
  [`CancellationTokenService.php:117`](../../src/CancellationTokenService.php#L117)

- Factory mirrors the same guard — consistent failure mode with the service
  [`CancellationTokenFactory.php:17`](../../database/factories/CancellationTokenFactory.php#L17)

- New config entry wiring hash_key to env
  [`cancellation-tokens.php:8`](../../config/cancellation-tokens.php#L8)

**Test coverage**

- Test base sets hash_key (was app.key)
  [`TestCase.php:32`](../../tests/TestCase.php#L32)

- RuntimeException guards for null and empty hash_key
  [`TokenCreationTest.php:208`](../../tests/Feature/TokenCreationTest.php#L208)

- Hash verification test updated to new config key
  [`TokenCreationTest.php:137`](../../tests/Feature/TokenCreationTest.php#L137)

- Bootstrap test asserts hash_key key present in published config
  [`PackageBootstrapTest.php:63`](../../tests/Feature/PackageBootstrapTest.php#L63)

**Planning artifact updates**

- Hash rule, config schema, anti-pattern for app.key
  [`project-context.md`](../project-context.md)

- Security NFR and innovation: key isolation from APP_KEY
  [`prd.md`](../planning-artifacts/prd.md)

- Six sections: requirements, cross-cutting, token gen, verification, decision impact, config gap
  [`architecture.md`](../planning-artifacts/architecture.md)

- Story 2.3 AC updated + new RuntimeException guard AC
  [`epics.md`](../planning-artifacts/epics.md)

- 3 items resolved, 1 updated (key rotation scoped to hash_key)
  [`deferred-work.md`](deferred-work.md)
