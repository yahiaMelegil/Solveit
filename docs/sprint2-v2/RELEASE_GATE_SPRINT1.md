# Sprint 1 release gate — updated 2026-10-01
Core Profile/Context/Consent/Privacy and Expert Single-Scope Renewal R3 remain in source and regression coverage.
The previously deferred ContractFixturesTest timing defect is now fixed. See VERIFICATION.md for actual full results.

| Gate | Current evidence |
|---|---|
|Complete latest baseline inspected|Full supplied project, R3 and original Sprint2 present|
|Local regression and fixture timing|Passed; current evidence attached|
|Additive catalog migration/up-down preservation|Passed on disposable SQLite; not a Staging proof|
|PrivacyPermissionsSeeder + Renewal four permissions|Existing additive seeders retained; Staging execution not performed|
|Staging migration and data preservation|Not run: deployment access/Base URL not supplied|
|Approved data_rights.due_days|Not supplied; do not substitute test SLA|
|Actual Terms/Privacy/Marketing/AI versions|Not supplied; no Development seeder on production|
|Private shared export/KYC storage and stable APP_KEY|Deployment verification pending|
|Queue/scheduler/export lifecycle|Local regression; deployment verification pending|
|CORS origins/exposed headers|Source present; actual browser verification pending|
|Auth, permission revoke and per-token reauth|Local regression; deployment smoke pending|
|15 Renewal routes/private evidence|Local regression; real-account smoke pending|
|Same-scope create/reviewer concurrency|Actual Staging DB engine test pending|
|React integration/Sprint0 open issues|Acceptance inputs/results not supplied|

Reference upgrade for R3, when appropriate to that deployed version:
```bash
php artisan migrate --force
php artisan db:seed --class=PrivacyPermissionsSeeder --force
php artisan db:seed --class=ExpertRenewalPermissionsSeeder --force
php artisan route:cache
php artisan route:list --name=renewals
```
Do not rerun AuthorizationSeeder if it synchronizes custom role grants. No deployment or external account creation
was performed in this task. No credentials, Base URL or staging success has been invented.
