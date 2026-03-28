# Deferred Work

## Deferred from: code review of 1-1-package-bootstrap-service-provider-config-and-migration (2026-03-28)

- Nullable `$tokenable` parameter vs non-nullable `morphs('tokenable')` DB columns — null $tokenable MUST be handled during implementation of story 2.3 (schema or contract adjustment)
- Migration `down()` reads live config at rollback time, not the value used at `migrate` time — potential config drift MUST be documented in README at story 6.3
- No index on `expires_at` or `used_at` in `cancellation_tokens` table — primary token lookup patterns (valid tokens, unconsumed tokens) will be full-table scans at scale. Add indexes when performance becomes a concern.
- Hardcoded absolute local developer path in `_bmad/core/config.yaml` (`output_folder` contains `/home/mrdth/...` with unresolved `{project-root}` placeholder) — BMAD tooling config issue, out of scope for package development.
- `TokenVerificationException` extends bare `Exception` with no error code, reason, or context — callers cannot distinguish between expired, already-used, not-found, and ownership-mismatch without parsing message strings. Add structured context in Story 2.x when the exception is actually thrown.
