# Deployment and operations

This is a changes-only patch over backend-29-8-2026.zip. Apply relative paths at the Laravel project root; do not copy vendor, runtime databases, secrets or test fixtures into public storage. Compare CHANGED_FILES.md and verify SPRINT1_MANIFEST.sha256. Preserve the existing APP_KEY and all existing account data. No .env file was changed in this delivery.

## Pre-deployment gates

- Review the fixed contract1.0.0, selected context schemas and the separate User/Expert/Admin boundaries.
- Back up the database and encryption key using existing secret-management procedures. Confirm target DB engine/version and test concurrent Profile/Context updates and duplicate rights requests there.
- Set config/data_rights.php due_days to an approved positive integer 1..365. The shipped null deliberately prevents new requests until an operational SLA is supplied. Do not adopt the fixture30-day value as a legal conclusion.
- Provision approved policy texts explicitly (below). Set production debug off. Review proxy/telemetry redaction for currentPassword, Authorization and private content.
- Configure a persistent shared cache for rate limits and reauthentication across app nodes. The array cache used in tests is not a production setting. Use a durable async queue and existing jobs/failed_jobs tables. Confirm queue retry_after exceeds the60s job timeout (the default90s satisfies this) and worker settings match job tries/timeouts.
- Protect the dedicated data-exports disk. Its local default is storage/app/private/data-exports, serve=false, visibility=private. Multi-node workers/web nodes must share the same protected storage or a configured private object-storage disk. Never expose it through a public storage symlink. Limit infrastructure request sizes and provision worker memory for representative account histories.

## Commands

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
php artisan migrate --force
php artisan db:seed --class=PrivacyPermissionsSeeder --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

Only the new migration is added. PrivacyPermissionsSeeder additively registers the 3 permissions and grants them only to an existing super_admin role; it does not reset role customizations or direct grants. AuthorizationSeeder was also updated for fresh installations. Its existing syncPermissions behavior resets built-in role permission lists, so use the additive seeder above for an existing database. Run under the existing deployment process/maintenance policy; no credential/bootstrap command is part of this patch.

Run the existing queue supervisor with, for example, `php artisan queue:work --tries=3 --timeout=60`. Configure the normal scheduler (`php artisan schedule:run` each minute); privacy:cleanup is registered hourly with overlap prevention. Alert on failed jobs, requested/processing requests overdue for dueAt, cleanup nonzero exits and deferred deletions due for review. There is no outbound email notification for completed exports; clients poll status within rate limits.

Operational commands:

```bash
php artisan privacy:cleanup
php artisan privacy:retry REQUEST_ID
```

Retry accepts requested/failed only and audits requeue. It never activates a deferred deletion. For queue transport failures after DB commit, inspect the persisted request and explicitly requeue by ID. Do not set states or paths with SQL. Jobs carry IDs only; stale worker artifacts are fenced and later cleaned.

## Policy provisioning

Production legal text was not supplied. There is no public/admin policy-management endpoint. Use a reviewed deployment seeder/script running under authorized infrastructure access. For each published immutable version, explicitly create:

```php
$text = /* exact approved UTF-8 policy text */;
(new \App\Models\PolicyVersion)->forceFill([
    'policy_key' => 'marketing',
    'purpose' => 'marketing',
    'version' => /* approved version string */,
    'locale' => 'ar',
    'content' => $text,
    'content_hash' => hash('sha256', $text),
    'effective_at' => /* approved UTC instant */,
    'requires_reconsent' => true,
    'is_published' => true,
    'created_at' => now(),
])->save();
```

The comments above are required owner-supplied values, not runnable production defaults. Use a stable key per purpose and check duplicate (policy_key,version,locale). Any corrected/material text requires a new version; never update a published record. Current selection uses latest effective_at then ID per purpose+locale. Mark material changes requires_reconsent=true; locales with equivalent versions require deliberate publishing coordination. With no registry, policies returns an empty array and consent remains not_recorded.

For isolated local/testing demos only: `php artisan db:seed --class=PrivacyDevelopmentSeeder`. The seeder explicitly refuses non-local/non-testing environments. Do not seed synthetic policies in Staging acceptance or production.

## Smoke checks and rollback

Validate all 26 new routes with `php artisan route:list --path=api/user` and `php artisan route:list --path=api/admin/data-requests`; inspect the separate Admin user-consent path. Confirm unauthorized401, wrong-account403, foreign404, stale409, invalid422, throttled429, real queued encrypted export/download/expiry and deferred deletion. Re-run Sprint0 Auth/KYC/RBAC checks. Verify CORS from the actual React origin.

The migration up/down/up cycle was tested locally against a disposable SQLite DB with an existing User preserved. Its down() drops the 11 new tables and destroys Sprint1 data. Never roll it back after receiving real data without an approved backup/retention plan. Prefer rolling back application code while preserving additive tables; assess already-updated users.name and encrypted history. The unchanged legacy manifest is not this release's authority; use SPRINT1_MANIFEST.sha256.

No deployment, Staging or React test was performed by this delivery. After Staging acceptance, schedule production rollout with a database-specific migration/concurrency check and an approved final-erasure policy for the subsequent executor sprint.

## Repeatable local queue acceptance check

Run `vendor/bin/phpunit tests/Feature/Privacy/QueuedPrivacyIntegrationTest.php --display-warnings` before infrastructure acceptance. It automatically creates/removes its own isolated database/cache/export storage and uses the actual queue worker in a separate process. See QUEUE_INTEGRATION.md for exact coverage and limits. It does not replace Staging/React/production-engine checks.
