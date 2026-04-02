# Sprint Change Proposal — 2026-04-02

**Change Scope:** Minor
**Status:** Approved
**Author:** Mrdth

---

## Section 1: Issue Summary

The package uses `config('app.key')` as the HMAC-SHA256 key for token hashing. This creates three problems:

1. **Reduced key entropy** — Laravel's `APP_KEY` config value includes the `base64:` prefix in its raw form; the package uses this raw string without decoding, reducing effective HMAC key material.
2. **Security coupling** — Rotating `APP_KEY` (a standard post-breach practice) silently invalidates all cancellation tokens. The package's security boundary should not be entangled with the application's encryption key.
3. **Silent failure** — An empty or null `APP_KEY` is silently accepted by `hash_hmac()`, producing tokens with no real key protection.

These issues were identified during implementation of Stories 2.3, 2.4, 2.5, and 6.2, and deferred at the time. The decision has been made to implement now rather than defer to a future version.

---

## Section 2: Impact Analysis

### Epic Impact

All 6 epics are complete. No epics are blocked, invalidated, or require resequencing. The change is a refinement to existing implementation.

### Story Impact

- **Story 2.3 (Token Creation)** — AC references `config('app.key')`; new AC for `RuntimeException` guard
- **Story 2.4 (Token Verification)** — flows through `hashToken()`; covered by the centralized change
- **Story 2.5 (Token Consumption)** — flows through `hashToken()`; covered by the centralized change
- **Story 6.2 (CancellationTokenFactory)** — mirrors `hashToken()` pattern; update to new config key

### Artifact Conflicts

| Artifact | Impact |
|---|---|
| `config/cancellation-tokens.php` | New `hash_key` entry |
| `src/CancellationTokenService.php` | `hashToken()` reads new config + guard |
| `database/factories/CancellationTokenFactory.php` | Reads new config key |
| `tests/TestCase.php` | Sets new config key |
| `tests/Feature/TokenCreationTest.php` | Asserts against new config key |
| New test coverage | `RuntimeException` on missing `hash_key` |
| PRD (security NFR + innovation) | 2 sentence rewrites |
| Architecture (6 sections) | `APP_KEY` → `cancellation-tokens.hash_key` |
| Epics (Story 2.3) | AC update + new AC |
| project-context.md | 3 updates (hash rule, config schema, anti-patterns) |
| deferred-work.md | 3 items resolved, 1 updated |

### Technical Impact

- **Breaking change for existing consumers** — upgrading requires setting `CANCELLATION_TOKEN_HASH_KEY` in `.env` and republishing config. Existing tokens hashed with `app.key` will NOT verify against the new key. Consumers must plan migration (see handoff).
- **No new dependencies** — pure config + code change.
- **CI unaffected** — test setup already configures a key; just a different config path.

---

## Section 3: Recommended Approach

**Direct Adjustment** — Modify existing code and documentation in place.

- **Effort:** Low — 4 code files + config + 5 planning documents
- **Risk:** Low — focused, well-scoped change
- **Timeline impact:** None — all epics complete; this is post-implementation refinement
- **Justification:** Centralized `hashToken()` method means the code change is a single function body. The config and documentation updates are straightforward text replacements. No architectural restructuring required.

---

## Section 4: Detailed Change Proposals

### Code Changes

**1. Config file — new `hash_key` entry**
```php
// config/cancellation-tokens.php
return [
    'table'          => 'cancellation_tokens',
    'prefix'         => 'ct_',
    'default_expiry' => 10080,
    'hash_key'       => env('CANCELLATION_TOKEN_HASH_KEY'),
];
```

**2. CancellationTokenService::hashToken() — new config key + guard**
```php
private function hashToken(string $plainToken): string
{
    $key = config('cancellation-tokens.hash_key');

    if ($key === null || $key === '') {
        throw new RuntimeException(
            'A hash key must be configured via cancellation-tokens.hash_key before tokens can be created or verified.'
        );
    }

    return hash_hmac('sha256', $plainToken, $key);
}
```

**3. CancellationTokenFactory — update config reference**
```php
'token' => hash_hmac('sha256', $plainToken, config('cancellation-tokens.hash_key')),
```

**4. Test files — update key setup and assertions**
- `tests/TestCase.php`: `config()->set('cancellation-tokens.hash_key', ...)` instead of `app.key`
- `tests/Feature/TokenCreationTest.php`: assert against `config('cancellation-tokens.hash_key')`
- New test: `RuntimeException` thrown when `hash_key` is not configured

### Planning Artifact Changes

**5. PRD** — Security NFR: `APP_KEY` → `cancellation-tokens.hash_key` (dedicated, no fallback). Innovation section updated to reflect key isolation.

**6. Architecture** — 6 sections updated: requirements overview, cross-cutting concerns, token generation/security, verification code block, decision impact analysis, config schema gap.

**7. Epics** — Story 2.3 AC updated to reference new config key. New AC added for `RuntimeException` guard.

**8. project-context.md** — Hash rule updated, config schema updated, new anti-pattern added (`Never use config('app.key')`).

**9. Deferred work** — 3 items resolved (2-3 base64 prefix, 2-5 empty key, 6-2 factory mirror). 1 item updated (2-4 key rotation scoped to `hash_key`).

---

## Section 5: Implementation Handoff

**Change scope:** Minor — direct implementation by development team.

**Breaking change advisory for consumers:**
1. Add `CANCELLATION_TOKEN_HASH_KEY=<64+ byte random string>` to `.env`
2. Republish config: `php artisan vendor:publish --tag=cancellation-tokens-config`
3. Existing tokens hashed with `app.key` will not verify against the new key — consumers with outstanding tokens should plan accordingly (e.g. let existing tokens expire naturally before switching, or re-hash during migration)

**Success criteria:**
- [ ] `config('cancellation-tokens.hash_key')` used in service, factory, and tests
- [ ] `RuntimeException` thrown when `hash_key` is null or empty
- [ ] New test covering the `RuntimeException` guard
- [ ] All existing tests pass
- [ ] PHPStan analysis passes
- [ ] Pint formatting passes
- [ ] Planning artifacts (PRD, Architecture, Epics, project-context) updated
- [ ] Deferred work items resolved
