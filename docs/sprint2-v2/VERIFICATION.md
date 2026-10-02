# Actual local verification — 2026-10-01
Baseline: complete backend-30-9-2026 (1).zip, SHA256
`5cbfeb68ec9f76287bb5b49261708fc33e0a485eb2f6d4f77b6e8661953dc128`.
PHP8.3.6, Laravel13.33.0, Sanctum4.3.3, Spatie permission8.3.0, SQLite disposable test DB.

| Command / check | Actual result |
|---|---|
|composer validate --strict|Pass; composer.json valid|
|vendor/bin/pint --test|Pass|
|vendor/bin/phpunit --display-warnings|282 tests, 282 passed, 2548 assertions; no reported warnings|
|Focused Catalog suite before final fixture expansion|26 passed, 356 assertions|
|Final CatalogContractTest fixture generation|1 passed, 49 assertions; 32 fixtures|
|Focused replay + Privacy boundary regression|16 passed, 159 assertions|
|phpstan analyse (phpstan.neon)|0 errors; level5 new Catalog/Case v2 modules|
|phpstan analyse -c phpstan-legacy.neon|0 errors; level0 whole app|
|php -l changed PHP sources|70 checked, no syntax errors|
|Additive migration/seeders/route cache/schedule/rollback/up|14 checks passed on disposable SQLite|
|Baseline synthetic User after upgrade/rollback/re-upgrade|1 preserved; foreign-key violations []|
|Observed new routes|35: 23 Catalog and12 v2 Case/Admin|
|Python concurrency runner syntax|Passed; runner NOT executed against Staging|

The full suite includes original Auth/KYC/RBAC/Privacy/Renewal and Case regressions. Baseline R3 had227 tests,
original Case Sprint2 added29 (256 total); this expansion adds26 (282 total), with no skipped/deleted tests.
Authorized v1 submit/self-only expectations were updated to v2/catalog behavior; original authentication realms
and security tests remain. The former ContractFixturesTest failure was a clock-before-policy-seeding defect,
now fixed, not excluded. The complete suite includes the fixed test.

CaseQueuedIntegrationTest runs route-cache + a real database queue worker + encrypted document processing and
v2 submission across separate PHP processes. Its scanner executable is synthetic: this is not real ClamAV proof.
Migration verification applied all baseline migrations, inserted a synthetic User, applied the new migration,
seeded additively twice, cached/listed routes and scheduler, rolled back one catalog migration and migrated again.
This proves local preservation only, not production-engine DDL compatibility under load.

No staging credentials/base URL were supplied. Real database concurrent create/submit/publish/revoke/renewal,
private multi-instance storage, actual CORS, real scanner and React end-to-end verification remain NOT RUN.
No staging or production database was touched. Strict whole-app level5 is NOT claimed: inherited Eloquent
annotation/type debt remains beyond the new-module level5 scope. No baseline-ignore file hides it.

Raw outputs: verification/. Migration command output includes local workspace paths and synthetic setup only.
New fixtures come from tests, with a fixed clock and no real credentials. Random request/idempotency IDs are
intentional; fixtures are protocol examples, not deterministic byte-for-byte file snapshots.
