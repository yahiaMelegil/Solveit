# Actual local verification — 2026-09-30 Asia/Jerusalem

| Executed check | Result |
|---|---|
| composer validate --strict | Passed, exit0; no dependency manifest/lock changes |
| vendor/bin/pint --test | Passed, exit0 |
| vendor/bin/phpunit tests/Feature/Expert/Renewal --display-warnings |16 tests,231 assertions; passed |
| vendor/bin/phpunit --display-warnings |227 tests,1564 assertions; passed |
| Original regression | All156 Sprint0 tests retained unmodified; prior55 privacy tests included |
| Renewal route list --name=renewals --json |15 new routes:8 Expert,7 Admin |
| Full route list --path=api --json |108 API routes (67 Sprint0 +26 privacy +15 renewal) |
| Disposable migration: prior schema -> up -> down(last1) -> up | Passed SQLite; existing User,Expert,KYC application,scope rows unchanged;0 FK violations |
| Existing separate-process queue/cache/private-disk/route-cache test | Included and passed in full suite |
| Final git diff --check and scope review | Passed; no original test, Composer or authentication contract rewrite |
| Fixture capture |20 real HTTP response examples; synthetic data only |

Runtime PHP8.3.6, Laravel13.33.0, Sanctum4.3.3, Spatie Permission8.3.0.
No PHPUnit warnings reported. Composer emitted its root-user/plugin safety notice; strict validation succeeded.
No .env changes or project packages added. Tests use existing SQLite configuration.

Coverage includes regulated and nonregulated approval, immutable encrypted evidence revisions,
additional information/new evidence/resubmission, rejection preserving old validity, window edge,
expired workspace and expiry during review, suspension/legacy/unknown-domain rejection,
one-open database uniqueness, stale/duplicate decisions, invalid transitions, scope preservation,
country/expiry/next-review enforcement, corruption/foreign/missing private document isolation,
user/expert/admin separation, real token, permission separation and additive upgrade seed,
audit events and audit-failure rollback/file cleanup, redacted errors, pagination validation and429.

The first focused run found that the existing PrivacyException renderer only enrolled privacy URL
prefixes. Renewal409 conflicts were rendered as500. Rendering is now scoped to explicit SprintOneApi
middleware enrollment, and all renewal conflict tests and existing privacy regression pass.

Artifacts under verification/ are the captured final test/route/migration outputs. This is local
backend evidence only. Production DB concurrency, Staging, React and external regulator checks have
not been executed. Final deletion/anonymization have not been implemented.
