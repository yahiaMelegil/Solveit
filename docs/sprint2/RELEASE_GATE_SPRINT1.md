> Historical v1 delivery record. Superseded for deployment/integration by [Case Core v2](../sprint2-v2/README.md). The fixture clock is now fixed; country/service policy comes from the database catalog.

# Sprint 1 Release Gate status at Sprint 2 delivery
Scope: Core Profile/Context/Consent/Privacy + Expert single-scope Renewal R3.
Source confirmed against backend-30-9-2026 and frontend handoff1.1.0; contracts each1.0.0;108 baseline API routes.

| Gate | Status |
|---|---|
|R3 source and66 handoff fixtures present|confirmed|
|Composer/Pint baseline|passed locally|
|Baseline full suite|226/227,1530 assertions; one known temporal fixture failure|
|Known failure fix|explicitly deferred by user; original file unchanged|
|Privacy/renewal additive seeders|present, verified on local disposable SQLite|
|Staging migrations and preservation|NOT RUN: no deployment access/base URL|
|Approved data_rights.due_days|NOT PROVIDED; baseline config null|
|Real policy versions|NOT PROVIDED; dev seeder prohibited outside local/testing|
|Private shared exports/KYC disk and APP_KEY consistency|NOT VERIFIED on staging|
|Queue/scheduler/export lifecycle|local coverage; staging not run|
|CORS actual origins/headers|source configured; browser deployment check pending|
|Auth,revocation,per-token step-up|local coverage; staging acceptance pending|
|15 renewal routes + private evidence|local coverage; staging acceptance pending|
|Same-scope competing create/reviewer decisions on real DB|NOT RUN|
|React E2E and Sprint0 open issues|NOT PROVIDED|

No staging credentials/test accounts were invented. No production deployment, migration or permission change
was made. Sprint 2 implementation does not close this gate or imply React end-to-end acceptance.
