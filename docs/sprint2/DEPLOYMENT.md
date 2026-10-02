> Historical v1 delivery record. Superseded for deployment/integration by [Case Core v2](../sprint2-v2/README.md). The fixture clock is now fixed; country/service policy comes from the database catalog.

# Deployment and rollback
## Gates
This branch is not deployed. Sprint 1 Staging/React/private-storage/production-engine-concurrency gate remains
open. Owner authorized local Sprint 2 implementation while deferring the existing ContractFixturesTest fix.
Do not relabel full-suite failure as green. No existing test exclusion or skip has been introduced.

## Configuration, no secrets
- CASE_INTAKE_COUNTRIES: comma-separated approved ISO codes. Default empty intentionally blocks submission.
  Test PS is synthetic configuration, not product or legal authorization.
- CASE_DOCUMENT_SCANNER_BINARY: absolute executable path for provisioned ClamAV, e.g. /usr/bin/clamscan.
  Install/update scanner and signatures operationally; no application package was added. Test unavailable and
  rejected outcomes. Never use a fake scanner in Staging/production.
- CASE_TRIAGE_URL: approved HTTPS human-triage/support destination, if actually operated. Empty means no claim
  that human triage is available. Ambiguous high-risk cases stop pending an approved operational route.
- config/case_intake.php: service/domain/language catalog, upload/revision limits, rate limits, grace period.
- filesystems.disks.case-documents: private, encrypted bytes, shared durable disk across web/queue instances.
  The bundled disk is local. Provision shared volume/storage configuration for multiple instances.
- Fixed existing APP_KEY across workers/instances; encrypted scan scratch/storage and restricted OS access.
- Existing queue connection with supervised workers, shared cache for distributed rate limits/idempotent
  operations, scheduler. Worker timeout60; configure queue retry_after greater than timeout.
- Existing CORS allow Idempotency-Key; expose Retry-After,Idempotency-Replayed,X-Request-ID,Content-Disposition.
No .env file was edited by this delivery. Supply approved settings through the deployment system.

## Upgrade commands after release approval
Back up DB/private storage and inspect migration status first. Do not run migrate:fresh or AuthorizationSeeder.
```bash
composer validate --strict
php artisan migrate --force
php artisan db:seed --class=CasePermissionsSeeder --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
php artisan route:list --name=cases
php artisan route:list --name=user.case-intake.bootstrap
php artisan schedule:list
```
New migration: 2026_09_30_100000_create_case_core_tables.php. Adds seven tables, no old table rewrite.
`php artisan case-documents:cleanup` removes only unreferenced upload leftovers and stale scanner scratch files.
`php artisan case-documents:retry VERSION_ID` queues failed/stale-pending scans after an operator fixes the cause.
It cannot make a rejected/clean revision clean or edit a frozen case.

## Smoke acceptance
Use real User/Expert/Admin test accounts. Verify draft/resume/conflict/assessment/confirm/submit/cancel,
metadata-only Admin access and permission revocation with the SAME token, Expert403, foreign404,
CORS preflight, private file paths inaccessible directly, clean/rejected/failed scanner results and protected Blob
reads. Run competing autosaves/submit and upload retries on the actual engine; SQLite is not lock evidence.

## Rollback
Before any Case writes, rollback only this migration in an isolated verified deployment window if needed.
After writes, prefer reverting application code while preserving the new tables and private files.
Do NOT run migration down against populated Case tables merely to roll back code: it deletes case history.
Stop affected workers first, restore previous app/config/routes consistently, retain APP_KEY and private bytes.
Restore backups only under a separately approved recovery procedure. No purge command is delivered.

Opt-in concurrency runner: `python scripts/verify_case_concurrency.py`. Supply CASE_TEST_BASE_URL ending
in /api, CASE_TEST_USER_TOKEN and CASE_TEST_COUNTRY through secure process environment. Use only a dedicated
verified test User with Terms/Privacy grants and approved enabled country. It concurrently tests same-key create,
competing autosave and competing submit, then cancels its synthetic Case. It has NOT been run against Staging.
Record actual database engine/version and deployment revision alongside its output; this script cannot infer them.
