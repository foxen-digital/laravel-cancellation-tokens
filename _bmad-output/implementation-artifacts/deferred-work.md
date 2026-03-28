# Deferred Work

## Deferred from: code review of 1-1-package-bootstrap-service-provider-config-and-migration (2026-03-28)

- Nullable `$tokenable` parameter vs non-nullable `morphs('tokenable')` DB columns — null $tokenable MUST be handled during implementation of story 2.3 (schema or contract adjustment)
- Migration `down()` reads live config at rollback time, not the value used at `migrate` time — potential config drift MUST be documented in README at story 6.3
- No index on `expires_at` or `used_at` in `cancellation_tokens` table — primary token lookup patterns (valid tokens, unconsumed tokens) will be full-table scans at scale. Add indexes when performance becomes a concern.
- Hardcoded absolute local developer path in `_bmad/core/config.yaml` (`output_folder` contains `/home/mrdth/...` with unresolved `{project-root}` placeholder) — BMAD tooling config issue, out of scope for package development.
- `TokenVerificationException` extends bare `Exception` with no error code, reason, or context — callers cannot distinguish between expired, already-used, not-found, and ownership-mismatch without parsing message strings. Add structured context in Story 2.x when the exception is actually thrown.

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
