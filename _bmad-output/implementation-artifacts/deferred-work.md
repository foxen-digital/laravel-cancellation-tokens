# Deferred Work

## Deferred from: code review of 1-1-package-bootstrap-service-provider-config-and-migration (2026-03-28)

- Nullable `$tokenable` parameter vs non-nullable `morphs('tokenable')` DB columns — null $tokenable MUST be handled during implementation of story 2.3 (schema or contract adjustment)
- Migration `down()` reads live config at rollback time, not the value used at `migrate` time — potential config drift MUST be documented in README at story 6.3
- No index on `expires_at` or `used_at` in `cancellation_tokens` table — primary token lookup patterns (valid tokens, unconsumed tokens) will be full-table scans at scale. Add indexes when performance becomes a concern.
- Hardcoded absolute local developer path in `_bmad/core/config.yaml` (`output_folder` contains `/home/mrdth/...` with unresolved `{project-root}` placeholder) — BMAD tooling config issue, out of scope for package development.
- `TokenVerificationException` extends bare `Exception` with no error code, reason, or context — callers cannot distinguish between expired, already-used, not-found, and ownership-mismatch without parsing message strings. Add structured context in Story 2.x when the exception is actually thrown.

## Deferred from: code review of 2-5-token-consumption (2026-04-01)

- **TOCTOU race condition in `consume()`** — `verify()` read and `$token->save()` are not atomic; two concurrent requests can both pass `verify()` before either sets `used_at`. No DB-level lock (`lockForUpdate()`) exists. Pre-existing architectural design; address only if concurrency safety requirements arise.
- **SQL equality used for hash lookup in `verify()`, not `hash_equals()`** — `CancellationToken::where('token', $computedHash)->first()` compares hashes via DB `=`, not PHP `hash_equals()`. Spec prescribes this lookup strategy. Tracked in deferred issues for Story 2.4 as well.
- **Returned model from `consume()` is in-memory only** — `used_at` is set on the PHP object and saved, but the returned instance is not refreshed from DB. DB-applied precision/casting differences (e.g. MySQL timestamp truncation) will not be reflected. Low risk; add `$token->refresh()` if strict round-trip fidelity is ever required.
- **`app.key` empty/null silently accepted by `hash_hmac()`** — pre-existing from Story 2.3; HMAC runs with empty key without error; tests are internally consistent. Address in Story 6.x test infrastructure.
- **Pruner deletes token between `verify()` and `save()` in `consume()`** — if model:prune runs in the narrow window between `verify()` returning and `save()` persisting `used_at`, the token may be deleted and `save()` silently re-inserts or updates 0 rows. Pre-existing architectural TOCTOU variant.

## Deferred from: code review of 2-4-token-verification (2026-04-01)

- **AC 5 has no feature test** — no test asserts `hash_equals()` is used or that `===` is never used on token hashes; deferred to Story 6.3 architecture tests.
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
- **Unsaved model passed to `create()` causes null `getKey()` and DB rejection** — `create()` has no guard against un-persisted models; DB will reject the null FK. Pre-existing contract gap; document in service docblock or add guard in a future story.
- **TestCase never sets `app.key` — all HMAC tests run against an empty key** — tests are internally consistent (both sides use the same empty key) but do not prove production-safe key handling. Address in Story 6.x test infrastructure work.

## Deferred from: code review of 2-2-cancellationtoken-eloquent-model (2026-03-28)

- **`static::` in `prunable()` misdirects if model is subclassed** — `static::where(...)` will query the subclass table if called on a subclass instance. Low risk currently; address if subclassing is ever introduced.
- **Architecture assertions for absent `SoftDeletes`/`$fillable`** — spec anti-patterns 1 and 3 (no SoftDeletes, no $fillable) have no automated test. Add assertions to story 6-3 (architecture tests).
- **`getTable()` re-reads config on every Eloquent call** — no `$this->table` caching; config lookup is repeated per query. Negligible at current scale; consider setting `$this->table` in the constructor if profiling reveals it as a hotspot.

## Deferred from: code review of 2-1-core-types-contract-enum-and-exception (2026-03-28)

- **`CancellationToken` model mass-assignable sensitive columns** — `token`, `used_at`, `expires_at`, `tokenable_type`, `cancellable_type` are all in `$fillable`. A mass-assignment call can overwrite token hashes, mark tokens consumed, extend expiry, or redirect morphable types. Address in Story 2.2 by auditing `$fillable` and considering `$guarded`.
- **Model hardcodes table name** — `protected $table = 'cancellation_tokens'` ignores the configurable `table` key in `config/cancellation-tokens.php`. Story 2.2 should read `config('cancellation-tokens.table', 'cancellation_tokens')` instead.
- **`@property int $id` PHPDoc mismatch** — if the project uses UUID/ULID primary keys, the `int` type hint is wrong. Confirm key type in Story 2.2 and update PHPDoc accordingly.
- **`create()` accepts a past `$expiresAt` without validation** — a caller passing `Carbon::yesterday()` creates a token expired at creation time. Story 2.3 should validate that `$expiresAt` is in the future (or null).
- **`match` in `TokenVerificationException` not enforced as exhaustive** — adding a new `TokenVerificationFailure` case without updating the `match` expression throws `UnhandledMatchError` at runtime. Consider a PHPStan/Psalm `@psalm-seal-cases` annotation or architecture test in Story 6.3.
