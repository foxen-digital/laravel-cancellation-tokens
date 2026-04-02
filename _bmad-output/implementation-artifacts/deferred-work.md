# Deferred Work

## Deferred from: code review of 6-3-architecture-tests (2026-04-02)

- **`toUse('hash_hmac')` cannot verify SHA-256 algorithm parameter** — pest-plugin-arch operates at function-usage level, not argument level; `hash_hmac('md5', ...)` would still pass this assertion. Would need a custom PHPStan rule to enforce the algorithm string. Pre-existing limitation.
- **No arch rule prevents direct `DB::` facade access from service classes** — preexisting architectural gap; a developer could bypass the Eloquent model entirely (undermining lifecycle events/observers) without any arch assertion catching it.
- **No arch rule confirms `Testing` namespace is `autoload-dev` only** — arch tests cannot inspect composer.json; `CancellationTokenFake` isolation is enforced at code-reference level only, not at build-artifact level.
- **No arch rule prevents internal code from type-hinting concrete `CancellationTokenService` instead of `CancellationTokenContract`** — preexisting gap; consumers within the package itself could depend on the concrete class, bypassing substitutability.

## Deferred from: code review of 6-2-cancellationtokenfactory (2026-04-02)

- **`app.key` passed raw to `hash_hmac` (includes `base64:` prefix)** — factory mirrors `CancellationTokenService::hashToken()` exactly; both use the raw value without decoding; consistent but reduces effective key entropy vs. decoded bytes. Pre-existing from Story 2.3.
- **Factory defaults `tokenable_type`/`cancellable_type` to non-existent `App\Models\User`/`App\Models\Booking`** — intentional per spec design decision #1 (placeholder strings satisfy the schema); consumers must use `->for()` when relation loading is needed; loading relations on default factory records will fail.
- **`beforeEach` migration guard pattern fragile with `RefreshDatabase`** — `Schema::hasTable` guard may prevent re-running the migration after a rollback; pre-existing pattern shared across all feature tests.

## Deferred from: code review of 6-1-cancellationtokenfake (2026-04-02)

- **`makeModel()` does not set `token` or `id` attributes** — expected fake fidelity gap; callers accessing `$model->token` or `$model->id` after `verify()`/`consume()` will receive null. Address if downstream code under test relies on these fields.
- **`expiresAt` exact boundary — `isPast()` returns false at exact expiry instant** — token expiring at precisely `now()` passes verification; pre-existing Carbon behavior shared with the real implementation.
- **`fake()` does not accept pre-seeded tokens or pre-configured state** — limits testing error paths (e.g. expired token scenarios) without calling `create()` and manually manipulating test state. Add a `withTokens()`/`withConsumed()` builder pattern if needed.
- **No `assertTokenVerified` tracking** — `verify()` calls are not recorded; tests cannot assert a token was read without being consumed. Add if verification-without-consumption assertions become a requirement.

## Deferred from: code review of 5-1-prunable-token-cleanup (2026-04-02)

- **`beforeEach` lacks error handling for missing migration file** — pre-existing pattern shared across all feature tests; if the migration file is renamed, `include` returns false and `->up()` crashes with unhelpful TypeError.
- **`expires_at = now()` boundary condition not tested** — strict `<` semantics make the expected behavior clear; not required by spec. Address if edge-case precision requirements arise.
- **Token both expired AND consumed has no dedicated test** — covered implicitly by the mixed-scenario test via OR semantics; add an explicit test if the prunable query changes to use AND or more complex logic.
- **No model event assertions (`pruning`/`pruned`)** — out of scope for story 5.1; no AC requires event dispatch. Add if event coverage becomes a requirement.
- **AC3 chunk test proves the API parameter, not actual DB-level chunking** — a small chunk size with 5 tokens proves the API accepts the parameter; actual per-chunk query behavior is a framework guarantee from Laravel's `Prunable` trait. Only addressable via DB-query spying.
- **`test_users`/`test_bookings` fixture tables have only `id` column** — pre-existing pattern; would need updating if fixture models require additional columns.

## Deferred from: code review of 4-1-token-lifecycle-events (2026-04-02)

- **`readonly` event classes expose mutable Eloquent model payload** — `readonly` only prevents property reassignment; listeners can call `$event->token->used_at = null; $event->token->save()` and corrupt state. Inherent to how Laravel events carry Eloquent models; address if immutability guarantees become a requirement (e.g. by dispatching a DTO/value object snapshot instead of the live model).
- **No `ShouldDispatchAfterCommit` interface on event classes** — if a caller wraps `create()` or `consume()` in a DB transaction, events fire before the transaction commits; queue workers handling the event may not yet see the token in the DB. Low risk for current usage; add interface if queue-driven listeners are introduced.

## Deferred from: code review of 3-3-validcancellationtoken-validation-rule (2026-04-01)

- **Uncaught non-TokenVerificationException from `verify()`** — DB exceptions, container binding errors, and other infrastructure failures propagate unhandled through the validation rule. The rule only catches `TokenVerificationException`; anything else (e.g. `QueryException`, `BindingResolutionException`) will surface as a 500 instead of a validation error. Pre-existing concern shared with service layer; address if resilience requirements arise.

## Deferred from: code review of 3-2-cancellationtoken-facade-and-service-provider-binding (2026-04-01)

- **Expiry string comparison fragile** — `toDateTimeString()` truncates to second-precision; sub-second drift between captured `$expiresAt` and DB-stored value may produce false failures. Pre-existing pattern from TraitTest.php; address if precision requirements arise.
- **`beforeEach` migration include path hardcoded** — if migration file is renamed or moved, `include` silently returns false and `->up()` crashes with unhelpful TypeError. Pre-existing copy-paste from TraitTest.php.
- **Migration `include` return not checked** — `$migration = include __DIR__...` not guarded before `->up()` call. Same root as above; address when resolving beforeEach pattern.

## Deferred from: code review of 3-1-hascancellationtokens-trait (2026-04-01)

- **Trait has no host type constraint** — `HasCancellationTokens` calls `$this->morphMany(...)` which requires the using class to be an Eloquent Model; PHP traits cannot enforce this. A non-Model class using the trait will fatal at runtime with no helpful error message. PHP language limitation; document in trait docblock if/when a doc story runs.
- **Morph map interference** — If an application registers morph aliases via `Relation::morphMap()`, `cancellable_type` stores the alias rather than the full class name. The tests assume no morph map and assert `TestBooking::class` directly. Real-world usage with morph maps will diverge. Address in Story 6.4 (README documentation).

## Deferred from: code review of 1-1-package-bootstrap-service-provider-config-and-migration (2026-03-28)

- **Migration `down()` reads live config at rollback time, not the value used at `migrate` time** — potential config drift. Document in Story 6.4 (README documentation).
- **No index on `expires_at` or `used_at` in `cancellation_tokens` table** — primary token lookup patterns (valid tokens, unconsumed tokens) will be full-table scans at scale. Add indexes when performance becomes a concern.
- **Hardcoded absolute local developer path in `_bmad/core/config.yaml`** — BMAD tooling config issue, out of scope for package development.

## Deferred from: code review of 2-5-token-consumption (2026-04-01)

- **TOCTOU race condition in `consume()`** — `verify()` read and `$token->save()` are not atomic; two concurrent requests can both pass `verify()` before either sets `used_at`. No DB-level lock (`lockForUpdate()`) exists. Pre-existing architectural design; address only if concurrency safety requirements arise.
- **SQL equality used for hash lookup in `verify()`, not `hash_equals()`** — `CancellationToken::where('token', $computedHash)->first()` compares hashes via DB `=`, not PHP `hash_equals()`. Spec prescribes this lookup strategy. Tracked in deferred issues for Story 2.4 as well.
- **Returned model from `consume()` is in-memory only** — `used_at` is set on the PHP object and saved, but the returned instance is not refreshed from DB. DB-applied precision/casting differences (e.g. MySQL timestamp truncation) will not be reflected. Low risk; add `$token->refresh()` if strict round-trip fidelity is ever required.
- **`app.key` empty/null silently accepted by `hash_hmac()`** — pre-existing from Story 2.3; HMAC runs with empty key without error; tests are internally consistent. Address if production-safe key handling becomes a requirement.
- **Pruner deletes token between `verify()` and `save()` in `consume()`** — if model:prune runs in the narrow window between `verify()` returning and `save()` persisting `used_at`, the token may be deleted and `save()` silently re-inserts or updates 0 rows. Pre-existing architectural TOCTOU variant.

## Deferred from: code review of 2-4-token-verification (2026-04-01)

- **AC 5 has no arch test for `hash_equals()` usage or `===` prohibition on token hashes** — ArchTest.php checks `hash_hmac` usage but does not assert `hash_equals()` is used or that `===` is never used on token strings. Would require a custom arch assertion or PHPStan rule.
- **TOCTOU race on expiry/consumption** — a token could expire or be consumed between the DB query and the status check; inherent read-then-act design; address only if concurrency safety requirements arise.
- **Timezone handling in `isPast()`** — no test verifies behavior when app timezone differs from DB timezone; pre-existing concern, not introduced by this diff.
- **Millisecond-precision expiry boundary** — token expiring at exactly `now()` may behave inconsistently across Carbon versions; system-level edge case, out of scope.
- **Empty string `$plainToken` input not validated** — an empty string hashes and returns NotFound; add input validation if a future story introduces stricter API contracts.
- **Orphaned tokenable/cancellable relationships not guarded** — `verify()` returns the model without checking related models still exist; lazy-load failure surfaces to the caller; deferred to a future story covering model lifecycle.
- **App key rotation between creation and verification** — already tracked in Known Deferred Issues in the story; HMAC uses `config('app.key')` at runtime; key rotation silently invalidates all tokens.
- **`RefreshDatabase` + `Schema::hasTable` guards redundant but intentional** — pre-existing pattern from TokenCreationTest.php; no action needed unless the test setup strategy is revisited.
- **`new CancellationTokenService` constructed directly in tests** — bypasses container DI; pre-existing convention; refactor if constructor gains dependencies.
- **Timing oracle on DB token lookup** — querying `WHERE token = $computedHash` leaks hit-vs-miss timing; the spec prescribes this lookup strategy; a lookup-by-ID + hash-compare architecture would fully mitigate but is out of scope.

## Deferred from: code review of 2-3-token-creation (2026-03-28)

- **`app.key` base64 prefix not stripped before HMAC** — `config('app.key')` returns the raw `base64:<encoded>` string; using it as the HMAC key reduces entropy vs. the decoded bytes. Deferred: future enhancement will introduce a dedicated configurable hash key instead of relying solely on `app.key`.

## Deferred from: code review of 2-2-cancellationtoken-eloquent-model (2026-03-28)

- **`static::` in `prunable()` misdirects if model is subclassed** — `static::where(...)` will query the subclass table if called on a subclass instance. Low risk currently; address if subclassing is ever introduced.
- **`getTable()` re-reads config on every Eloquent call** — no `$this->table` caching; config lookup is repeated per query. Negligible at current scale; consider setting `$this->table` in the constructor if profiling reveals it as a hotspot.

## Deferred from: code review of 2-1-core-types-contract-enum-and-exception (2026-03-28)

- **`match` in `TokenVerificationException` not enforced as exhaustive** — adding a new `TokenVerificationFailure` case without updating the `match` expression throws `UnhandledMatchError` at runtime. Consider a PHPStan/Psalm `@psalm-seal-cases` annotation or a custom arch test. Pre-existing limitation of pest-plugin-arch scope.

## Resolved in deferred-item cleanup (2026-04-02)

- **Unsaved model passed to `create()` causes null `getKey()` and DB rejection** — resolved: `CancellationTokenService::create()` now throws `InvalidArgumentException` with a clear message if either model is not persisted.
- **No negative test: `TokenVerified` NOT dispatched on failure paths** — resolved: added tests asserting `TokenVerified` is not dispatched on NotFound, Consumed, and Expired failure paths.
- **No negative test: `TokenConsumed` NOT dispatched on failure paths** — resolved: added tests asserting `TokenConsumed` is not dispatched on NotFound, Consumed, and Expired failure paths.
- **`match` in `TokenVerificationException` not enforced as exhaustive** — resolved: added regression guard in ArchTest.php pinning the enum case set to `['not_found', 'expired', 'consumed']`; adding a new case without updating handlers will fail the test.
