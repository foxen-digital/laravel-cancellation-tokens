---
stepsCompleted: ["step-01-document-discovery", "step-02-prd-analysis", "step-03-epic-coverage-validation", "step-04-ux-alignment", "step-05-epic-quality-review", "step-06-final-assessment"]
documentsIncluded:
  prd: "_bmad-output/planning-artifacts/prd.md"
  architecture: "_bmad-output/planning-artifacts/architecture.md"
  epics: "_bmad-output/planning-artifacts/epics.md"
  ux: null
---

# Implementation Readiness Assessment Report

**Date:** 2026-03-28
**Project:** laravel-cancellation-tokens

## Document Inventory

| Type | File | Size | Modified |
|------|------|------|----------|
| PRD | `_bmad-output/planning-artifacts/prd.md` | 23K | 2026-03-28 10:03 |
| Architecture | `_bmad-output/planning-artifacts/architecture.md` | 28K | 2026-03-28 13:50 |
| Epics & Stories | `_bmad-output/planning-artifacts/epics.md` | 36K | 2026-03-28 14:23 |
| UX | N/A (backend package — skipped) | — | — |

---

## PRD Analysis

### Functional Requirements

**Token Lifecycle Management**
- FR1: Developer can create a cancellation token associated with both a tokenable actor and a cancellable subject
- FR2: Developer can specify an expiry timestamp at token creation time
- FR3: Developer can verify a presented plain-text token string against stored tokens
- FR4: Developer can consume a token, recording it as used
- FR5: Developer can retrieve the tokenable actor from a token record
- FR6: Developer can retrieve the cancellable subject from a token record

**Token Security**
- FR7: The system stores only a hashed representation of the token — the plain-text value is never persisted
- FR8: The system returns a plain-text token string to the caller at creation time for inclusion in URLs
- FR9: The system prepends a configurable prefix to generated tokens before returning them to the caller
- FR10: The system enforces single-use tokens by tracking a consumption timestamp
- FR11: The system enforces time-based expiry by comparing against a stored expiry timestamp
- FR12: The system uses timing-safe comparison when verifying token hashes

**Model Integration (Trait)**
- FR13: Developer can add cancellation token capabilities to any Eloquent model via a trait
- FR14: Developer can associate a token with any Eloquent model as the tokenable actor — not limited to User
- FR15: Developer can associate a token with any Eloquent model as the cancellable subject
- FR16: Developer can access all cancellation tokens for a cancellable model via an Eloquent relationship
- FR17: Developer can create a token directly on a cancellable model instance via the trait

**Facade API**
- FR18: Developer can create, verify, and consume tokens via a static Facade without adding a trait to any model
- FR19: Developer can swap the Facade's underlying implementation in tests without modifying production code

**Validation**
- FR20: Developer can validate a token string using a Laravel validation rule in a form request or controller
- FR21: The validation rule distinguishes between not-found, expired, and consumed failure states
- FR22: Developer can determine the specific reason a token failed validation to provide appropriate user feedback

**Event System**
- FR23: The application can listen for an event when a token is successfully created
- FR24: The application can listen for an event when a token is successfully verified
- FR25: The application can listen for an event when a token is successfully consumed
- FR26: The application can listen for an event when an expired token is presented for verification

**Token Cleanup**
- FR27: The application can automatically prune expired and consumed tokens using Laravel's built-in `model:prune` command
- FR28: Developer can configure the criteria that determine which tokens are prunable

**Testing Support**
- FR29: Developer can replace the token service with an in-memory test double that requires no database
- FR30: Developer can assert that a token was created for a specific cancellable model in tests
- FR31: Developer can assert that a token was consumed in tests
- FR32: Developer can create token records using a model factory in feature tests

**Package Setup & Configuration**
- FR33: Developer can publish the database migration to their application
- FR34: Developer can publish the configuration file to their application
- FR35: Developer can configure the token table name
- FR36: Developer can configure the token prefix
- FR37: Developer can configure the default token expiry duration
- FR38: The package registers automatically via Laravel's service provider auto-discovery

**Total FRs: 38**

---

### Non-Functional Requirements

**Performance**
- NFR1: Token generation completes in a single database write — no multi-query overhead at creation time
- NFR2: Token verification completes in a single database lookup — the token hash is the lookup key; no table scans
- NFR3: HMAC-SHA256 is the required hashing algorithm — bcrypt is explicitly disallowed
- NFR4: The `cancellation_tokens` table must be indexed on the token hash column to guarantee O(log n) lookups
- NFR5: Token pruning via `model:prune` must operate in batches to avoid memory exhaustion on large tables

**Security**
- NFR6: The plain-text token must never be persisted to any storage medium (database, cache, logs)
- NFR7: Token hashes must be computed using HMAC-SHA256 keyed with the application's `APP_KEY`
- NFR8: Token verification must use `hash_equals()` for timing-safe comparison — `===` comparison is prohibited
- NFR9: Generated tokens must use cryptographically secure random bytes (minimum 64 bytes of randomness post-prefix)
- NFR10: Token failure responses must not expose distinguishable error states externally in a way that enables enumeration attacks
- NFR11: Plain-text tokens must not be intentionally logged or written to any persistent storage

**Scalability**
- NFR12: All database queries issued by the package must use indexed columns — no unindexed WHERE clauses
- NFR13: Pruning operations must support chunked deletion to avoid locking the table during bulk cleanup

**Integration**
- NFR14: Compatible with Laravel 12 and 13, PHP 8.3+
- NFR15: Must not conflict with or override any Laravel core behaviour (events, validation, Eloquent, pruning)
- NFR16: Service provider must register via Composer `extra.laravel` auto-discovery — no manual registration
- NFR17: Must not require or enforce a morph map in the consuming application
- NFR18: Must not introduce side effects on any Eloquent model that does not explicitly use the `HasCancellationTokens` trait
- NFR19: `CancellationTokenFake` must implement the same interface as the real token service to enable transparent substitution in tests

**Quality & Maintainability**
- NFR20: Test coverage ≥ 95% (unit + integration), measured by line coverage
- NFR21: All public API methods covered by tests exercising both success and failure paths
- NFR22: Package passes a security review with no token-handling vulnerabilities before v1.0.0 release
- NFR23: Package ships with `CancellationTokenFactory` so consuming applications can generate test data without writing their own factory

**Total NFRs: 23**

---

### Additional Requirements / Constraints

- PHP 8.3 minimum, Laravel 12 minimum (Laravel 13 compatible at release)
- No dependencies beyond Laravel's own framework packages (`illuminate/database`, `illuminate/support`, etc.)
- Installation: `composer require` + two `vendor:publish` commands + `php artisan migrate`
- Token prefix: `ct_` (configurable); 64 random bytes post-prefix
- MorphMap: package must not require or enforce a morph map — document optional configuration
- Config surface: table name, token prefix, default expiry duration
- Entity-specific expiry is **out of scope** — consuming apps pass `expiresAt` explicitly
- README must cover: basic booking flow (trait), multi-model flow (Facade), expired/consumed handling via events, testing with Fake, configuring `model:prune`

---

### PRD Completeness Assessment

The PRD is **comprehensive and well-structured**. Requirements are numbered, grouped logically, and clearly written. FRs are atomic and testable. NFRs are specific with measurable criteria (e.g., 64 random bytes, HMAC-SHA256, `hash_equals()`). No ambiguous requirements detected. The PRD clearly delineates in-scope vs out-of-scope items, which reduces implementation risk significantly.

---

## Epic Coverage Validation

### Coverage Matrix

| FR | PRD Requirement (summary) | Epic / Story | Status |
|----|--------------------------|--------------|--------|
| FR1 | Create token with tokenable + cancellable | Epic 2 / Story 2.3 | ✓ Covered |
| FR2 | Specify expiry at creation time | Epic 2 / Story 2.3 | ✓ Covered |
| FR3 | Verify plain-text token against stored tokens | Epic 2 / Story 2.4 | ✓ Covered |
| FR4 | Consume token, recording it as used | Epic 2 / Story 2.5 | ✓ Covered |
| FR5 | Retrieve tokenable actor from token record | Epic 2 / Story 2.2 + 2.4 | ✓ Covered |
| FR6 | Retrieve cancellable subject from token record | Epic 2 / Story 2.2 + 2.4 | ✓ Covered |
| FR7 | Store only hashed token — no plain-text persistence | Epic 2 / Story 2.3 | ✓ Covered |
| FR8 | Return plain-text token to caller at creation | Epic 2 / Story 2.3 | ✓ Covered |
| FR9 | Prepend configurable prefix to returned token | Epic 2 / Story 2.3 | ✓ Covered |
| FR10 | Enforce single-use via consumption timestamp | Epic 2 / Story 2.5 | ✓ Covered |
| FR11 | Enforce time-based expiry | Epic 2 / Story 2.4 | ✓ Covered |
| FR12 | Use timing-safe hash comparison | Epic 2 / Story 2.4 | ✓ Covered |
| FR13 | Add token capabilities to any model via trait | Epic 3 / Story 3.1 | ✓ Covered |
| FR14 | Tokenable actor can be any Eloquent model | Epic 3 / Story 3.1 | ✓ Covered |
| FR15 | Cancellable subject can be any Eloquent model | Epic 3 / Story 3.1 | ✓ Covered |
| FR16 | Access all tokens via Eloquent relationship | Epic 3 / Story 3.1 | ✓ Covered |
| FR17 | Create token directly on cancellable model instance | Epic 3 / Story 3.1 | ✓ Covered |
| FR18 | Create/verify/consume via static Facade | Epic 3 / Story 3.2 | ✓ Covered |
| FR19 | Swap Facade implementation in tests | Epic 3 / Story 3.2 | ✓ Covered |
| FR20 | Validate token via Laravel validation rule | Epic 3 / Story 3.3 | ✓ Covered |
| FR21 | Validation rule distinguishes not-found/expired/consumed | Epic 3 / Story 3.3 | ✓ Covered |
| FR22 | Developer can access specific failure reason | Epic 3 / Story 3.3 | ✓ Covered |
| FR23 | Listen for event when token is created | Epic 4 / Story 4.1 | ✓ Covered |
| FR24 | Listen for event when token is verified | Epic 4 / Story 4.1 | ✓ Covered |
| FR25 | Listen for event when token is consumed | Epic 4 / Story 4.1 | ✓ Covered |
| FR26 | Listen for event when expired token is presented | Epic 4 / Story 4.1 | ✓ Covered |
| FR27 | Prune tokens via `model:prune` | Epic 5 / Story 5.1 | ✓ Covered |
| FR28 | Configure prunable criteria | Epic 5 / Story 5.1 | ✓ Covered |
| FR29 | In-memory test double requiring no database | Epic 6 / Story 6.1 | ✓ Covered |
| FR30 | Assert token created for specific cancellable | Epic 6 / Story 6.1 | ✓ Covered |
| FR31 | Assert token was consumed | Epic 6 / Story 6.1 | ✓ Covered |
| FR32 | Model factory for feature tests | Epic 6 / Story 6.2 | ✓ Covered |
| FR33 | Publish database migration | Epic 1 / Story 1.1 | ✓ Covered |
| FR34 | Publish configuration file | Epic 1 / Story 1.1 | ✓ Covered |
| FR35 | Configure token table name | Epic 1 / Story 1.1 | ✓ Covered |
| FR36 | Configure token prefix | Epic 1 / Story 1.1 | ✓ Covered |
| FR37 | Configure default token expiry duration | Epic 1 / Story 1.1 | ✓ Covered |
| FR38 | Auto-discovery via service provider | Epic 1 / Story 1.1 | ✓ Covered |

### Missing Requirements

None identified. All 38 PRD Functional Requirements are covered.

### Coverage Statistics

- Total PRD FRs: 38
- FRs covered in epics: 38
- Coverage percentage: **100%**

---

## UX Alignment Assessment

### UX Document Status

Not found — intentionally absent.

### Alignment Issues

None. The PRD explicitly states: *"Not applicable — this package ships no views, routes, or UI components."* The epics document confirms the same. This is a backend Composer package; no user-facing UI is implied or expected.

### Warnings

None.

---

## Epic Quality Review

### Best Practices Compliance Checklist

| Epic | User Value | Independent | Stories Sized | No Forward Deps | ACs Clear | FR Traceable |
|------|-----------|-------------|---------------|-----------------|-----------|-------------|
| Epic 1 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Epic 2 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Epic 3 | ✓ | ✓ | ✓ | ⚠️ | ✓ | ✓ |
| Epic 4 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Epic 5 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Epic 6 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

---

### 🔴 Critical Violations

None identified.

---

### 🟠 Major Issues

#### Issue 1: Forward Dependency — Story 3.2 → Story 6.1 (CancellationTokenFake)

**Location:** Epic 3 / Story 3.2 — "CancellationToken Facade and Service Provider Binding"

**Problem:** The last acceptance criterion in Story 3.2 states:

> *Given `CancellationToken::fake()` is called in a test / When the service container resolves `CancellationTokenContract` / Then it returns the `CancellationTokenFake` instance*

`CancellationTokenFake` is defined and implemented in Epic 6 / Story 6.1. Story 3.2 cannot be fully verified — specifically, the `fake()` AC — until Story 6.1 is complete. This is a forward dependency.

**Impact:** If a developer implements Story 3.2 before Epic 6, the `CancellationToken::fake()` method has nothing to return. The story cannot be considered "done" as written until after Epic 6 is complete.

**Recommendation:** Two options:
- **Option A (preferred):** Split Story 3.2. Move the `CancellationToken::fake()` AC to Story 6.1 alongside the `CancellationTokenFake` implementation — since both pieces must land together. Keep Story 3.2 focused on the Facade's `create/verify/consume` delegation.
- **Option B:** Keep Story 3.2 as-is but add an explicit note that the `fake()` AC is conditionally verified after Story 6.1, and implement a stub `fake()` method that throws `RuntimeException` until Story 6.1 completes.

---

#### Issue 2: Contract Signature Discrepancy — Story 2.1 vs AR4 / Story 2.3

**Location:** Epic 2 / Story 2.1 — "Core Types — Contract, Enum, and Exception"

**Problem:** The AC in Story 2.1 specifies the contract signature as:
```php
create(Model $cancellable, Model $tokenable, Carbon $expiresAt): string
```
However, Architecture Requirement AR4 and Story 2.3 both specify:
```php
create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string
```
The `expiresAt` parameter is nullable with a default of `null` (falling back to `config('cancellation-tokens.default_expiry')`). Story 2.1's AC requires a non-nullable `Carbon`, making it impossible to satisfy Story 2.3's AC for the "no expiresAt argument" case without violating the contract.

**Impact:** A developer implementing the contract per Story 2.1 AC will have a signature mismatch with the behaviour tested in Story 2.3. This will cause type errors or test failures.

**Recommendation:** Update Story 2.1's AC to specify the contract signature as `create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string` to match AR4 and Story 2.3.

---

### 🟡 Minor Concerns

#### Concern 1: Story 6.3 (Architecture Tests) — Technical Task, Not a User Story

**Location:** Epic 6 / Story 6.3

**Problem:** "As a developer, I want automated architecture assertions enforced in CI" — this is a quality-assurance task rather than a feature delivering user value. The ACs are entirely technical assertions (`ArchTest.php` must pass, class structure rules). For a package, this is more of a definition-of-done quality gate than a user story.

**Impact:** Low — this will still be implemented and tested. The concern is classification, not coverage.

**Recommendation:** Consider moving the ArchTest requirements into a Definition of Done checklist for Epic 6, or accept it as-is with acknowledgment that developer-tool packages have legitimate "developer-value" stories that look like technical tasks from a consumer-app perspective.

---

#### Concern 2: Single-Story Epics — Epic 1 and Epic 5

**Location:** Epic 1 (1 story), Epic 5 (1 story)

**Problem:** Both epics contain only a single story. While there's nothing technically wrong with this, single-story epics sometimes indicate the epic is too narrow, or alternatively, the story is too large.

**Assessment:** Epic 1 / Story 1.1 covers installation, migration, config publication, and auto-discovery in one story. This is 6 FRs (FR33–FR38) in a single story. Story 1.1 has 6 distinct ACs, each testing a different aspect of the setup.

**Impact:** Low. All ACs are discrete and testable. The risk is that if one AC blocks (e.g., migration issue), the whole story is held up.

**Recommendation:** Accept as-is. The ACs are clear and independent enough to guide implementation. Alternatively, split into "Story 1.1: Service Provider & Auto-Discovery" and "Story 1.2: Publishable Migration & Config" if finer granularity is desired.

---

### Epic-by-Epic Summary

| Epic | Stories | Issues | Status |
|------|---------|--------|--------|
| Epic 1: Package Foundation | 1 | None | ✅ Ready |
| Epic 2: Core Token Lifecycle | 5 | Issue 2 (contract signature) | ⚠️ Fix before implementing |
| Epic 3: Developer API Surface | 3 | Issue 1 (forward dep on Story 6.1) | ⚠️ Fix before implementing |
| Epic 4: Event System | 1 | None | ✅ Ready |
| Epic 5: Token Cleanup | 1 | None | ✅ Ready |
| Epic 6: Testing & Documentation | 4 | Concern 1 (Story 6.3 classification) | ✅ Ready (concern only) |

---

## Summary and Recommendations

### Overall Readiness Status

**✅ READY — All issues resolved (2026-03-28)**

The planning artifacts are of high quality overall. The PRD is comprehensive and complete (38 FRs, 23 NFRs, all well-specified). FR coverage across epics is 100%. The architecture is well-defined with explicit namespace, schema, and API surface decisions. No UX gaps exist (backend package, as expected). Two issues — one major forward dependency and one contract signature discrepancy — must be fixed before implementation to avoid rework mid-sprint.

---

### Critical Issues Requiring Immediate Action

#### ~~1. Fix Contract Signature in Story 2.1 (Issue 2 — Signature Discrepancy)~~ ✅ RESOLVED

**Location:** Epic 2 / Story 2.1, AC for `CancellationTokenContract`

**Current:** `create(Model $cancellable, Model $tokenable, Carbon $expiresAt): string`

**Required:** `create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string`

**Why critical:** Story 2.3 tests the default-expiry path (no `expiresAt` argument). If the contract is implemented with a non-nullable `Carbon $expiresAt`, Story 2.3's default-expiry AC cannot pass. This will be discovered immediately upon implementing the service but creates unnecessary confusion if not corrected in the story spec first.

#### ~~2. Resolve Forward Dependency in Story 3.2 → Story 6.1 (Issue 1 — Forward Dependency)~~ ✅ RESOLVED

**Location:** Epic 3 / Story 3.2, last AC (`CancellationToken::fake()`)

**Problem:** Story 3.2's `fake()` AC depends on `CancellationTokenFake` from Epic 6 / Story 6.1.

**Recommended fix:** Move the `fake()` AC from Story 3.2 into Story 6.1. Story 6.1 already owns `CancellationTokenFake` — it is the natural home for the AC that verifies the Facade's `fake()` method wires up the fake correctly. Add a note to Story 3.2 that `fake()` is implemented as a stub/placeholder pending Story 6.1.

---

### Recommended Next Steps

1. **Fix Story 2.1:** Update the `CancellationTokenContract` signature AC to use `?Carbon $expiresAt = null`.
2. **Fix Story 3.2:** Move the `CancellationToken::fake()` acceptance criterion to Story 6.1. Add a brief note to Story 3.2 that `fake()` is a stub returning `null` until Story 6.1 is complete.
3. **Proceed with implementation** in epic order (Epic 1 → 2 → 3 → 4 → 5 → 6). No other blockers exist.
4. **Optional:** Consider splitting Story 1.1 into two stories (service provider/auto-discovery vs migration/config publish) if you want finer granularity — not required.
5. **Optional:** Reclassify Story 6.3 (ArchTest) as a Definition of Done requirement rather than a standalone user story — not required.

---

### Final Note

This assessment identified 2 major issues and 2 minor concerns across the planning artifacts. Both major issues were resolved on 2026-03-28. The PRD and architecture documents are production-quality. Epic and story coverage is complete at 100%. The project is **fully ready for implementation**.

**Assessment completed:** 2026-03-28
**Assessor:** Claude Code (PM/Scrum Master role)
**Report file:** `_bmad-output/planning-artifacts/implementation-readiness-report-2026-03-28.md`
