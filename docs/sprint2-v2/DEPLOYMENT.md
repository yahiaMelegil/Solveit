# Deployment and rollback — v2
## Prerequisites
Sprint 1 Staging gate remains open; see RELEASE_GATE_SPRINT1.md. This artifact does not grant production launch.
Target baseline is the complete backend containing Sprint1 Renewal R3 and original Sprint2.
Back up DB/private storage, record code SHA and database engine/version. Preserve APP_KEY across all instances.
Do not edit .env via this patch, run migrate:fresh, or resync AuthorizationSeeder on an existing environment.

## Upgrade
```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=CatalogPermissionsSeeder --force
php artisan db:seed --class=ServiceCatalogSeeder --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
php artisan route:list --name=v2.
php artisan route:list --name=catalog
php artisan schedule:list
php artisan catalog:review-cases
```
ServiceCatalogSeeder is additive and preserves custom edits. It imports initial **templates**, not live launch:
41 initial service entries remain draft with operational/commercial availability false. Countries/jurisdictions
are draft until explicitly enabled. Only selected base taxonomy/safety/delivery records are enabled.
Existing legacy ready cases lacking explicit policy coverage become requires_review when the command runs.
Preview counts/back up and inform operators before this audit migration step; it does not invent policy snapshots.

If upgrading from an earlier base, apply the matching prerequisite Sprint1/Sprint2 migrations and additive
PrivacyPermissionsSeeder, ExpertRenewalPermissionsSeeder, CasePermissionsSeeder exactly once/as documented.
This changes-only package does not contain all earlier project files. Use the named full baseline.

## Operational launch per service
Create/enable country/jurisdiction/taxonomy where needed. Create a complete NEW policy version from template,
with approved intake/docs/delivery/rules/effective dates and availability. Review by independent people for
regulated services, explicitly map approved evidence to each exact service/jurisdiction, preview impact, publish
pilot for inspection, then enabled when the operational decision permits. Pilot never permits live Case readiness.
Publishing itself does not require supply; cases wait until minimum eligible coverage exists everywhere.
All initial domains/services must be reviewed; unknown policies fail closed. Do not enable all services by country.

## Non-secret configuration and infrastructure
- CASE_INTAKE_COUNTRIES is obsolete; DB catalog is the source. No runtime country/domain/service allowlists.
- CASE_DOCUMENT_SCANNER_BINARY: provision real scanner executable and updated signatures. Fake test scanner
  is never a deployment option. Failed/unavailable scanning blocks downloads/submission.
- CASE_TRIAGE_URL: approved HTTPS operated support destination. Empty means no staffed triage claim.
- case-documents, exports, KYC disks private/durable/shared across web+workers when multiple instances run.
- APP_KEY fixed across instances and backups; never rotate without encrypted-data migration plan.
- Queue workers supervised; scheduler every minute drives catalog review every5min and existing cleanup.
- Shared cache/rate limits; safe retry_after/timeout settings as existing queue contract.
- CORS allow Idempotency-Key; expose Retry-After,Idempotency-Replayed,X-Request-ID,Content-Disposition.
- Policy versions and data_rights.due_days require actual operational approval; no test values or dev policy seeder.

## Smoke / concurrency on deployment engine
Use dedicated verified User and Admins with exact permissions plus a KYC-approved Expert with explicit grant.
Verify same-key create, conflicting autosaves, competing submit/cancel, publish vs submit, grant revoke vs submit,
renewal vs submit, permission revocation using SAME token, privacy/renewal regression, and protected file access.
Record real DB engine/version. Local SQLite and process tests do not validate row-lock semantics on MySQL/Postgres.
Use the bundled v2 concurrency runner only against dedicated disposable test cases and already enabled fixture
policy/grants. Never use the old v1 submit runner to claim v2 concurrency verification.

## Rollback
Before new records: tested SQLite rollback of just the catalog migration, then re-upgrade, preserved old User row.
After catalog/case writes: prefer code-forward fix or keep new schema while reverting code under maintenance.
`migrate:rollback --step=1` destroys catalog versions/grants/scope snapshots: DO NOT run it on populated production
merely to undo application code. Stop affected workers, keep APP_KEY/private files, restore reviewed backups only
through an approved recovery procedure. Old v1 code may bypass new gates; do not reopen writes with old code.

Runner environment (secret token only through secure process environment): CASE_TEST_BASE_URL ending `/api`,
CASE_TEST_USER_TOKEN, CASE_TEST_COUNTRY, CASE_TEST_CATALOG_ENTRY_ID, CASE_TEST_CATALOG_VERSION,
CASE_TEST_DELIVERY_MODE, CASE_TEST_JURISDICTIONS_JSON (`[]` for GLOBAL), CASE_TEST_ANSWERS_JSON (`{}` if empty).
Run `python scripts/verify_catalog_v2_concurrency.py`. It creates and attempts to cancel one synthetic Case;
use a policy requiring no document upload, current consents and explicit eligible grants. It tests same-key create,
competing autosave and competing submit only. The publish/revoke/renewal races still need an operator scenario.
