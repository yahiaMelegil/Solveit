> Historical core-privacy release evidence. The2026-09-30 Expert renewal addition supersedes renewal deferral below; latest cumulative results: [renewal verification](../expert-renewal/VERIFICATION.md).

# Local verification — 2026-09-29

| Check executed | Actual result |
|---|---|
| composer validate --strict | Exit 0; composer.json valid |
| vendor/bin/pint --test | Passed |
| vendor/bin/phpunit tests/Feature/Privacy --display-warnings | 55 tests; 469 assertions; passed |
| vendor/bin/phpunit --display-warnings | 211 tests; 1333 assertions; passed |
| Real database queue/cache/private-disk integration across PHP processes |1 test;51 assertions; passed; see QUEUE_INTEGRATION.md |
| Baseline regression included above | All 156 original tests retained; no baseline test edits |
| php artisan route:list --path=api --json |93 routes:67 existing +26 additive |
| php artisan route:list --path=api/user --json |23 new User routes |
| php artisan route:list --path=api/admin/data-requests --json |2 new Admin routes |
| php artisan route:list --path=api/admin/users --json |Existing user routes plus new consent metadata route |
| Migration existing schema -> up -> down(last1) -> up | Passed in disposable SQLite; existing User unchanged; 0 FK violations |
| Fixture generation through ContractFixturesTest |46 actual response examples,51 assertions in that test |
| Final diff / package review | Additive source and documented shared-file edits; existing Auth/KYC contracts, composer files and .env unchanged |

Runtime: PHP8.3.6, Laravelv13.33.0, Sanctumv4.3.3, Spatie Permission8.3.0, PHPUnit12.5.35, Pintv1.32.1. Locked Composer dependencies were installed; no package was added to composer.json/composer.lock. GD was installed in the disposable runtime to execute the existing avatar tests, not added as a project package. PHPUnit uses the repository's SQLite in-memory testing configuration; migration smoke used a separate disposable SQLite file. No project .env was created/modified.

The expanded suite covers Profile snapshots/validation/no-op/version races, Context CRUD/isolation/conflicts/history, policy version and withdrawal, privacy preferences, real encrypted export/download/corruption/expiry, duplicate requests/jobs, token/purpose/time-bound confirmation, deletion deferral/cancellation, invalid transitions, expired worker leases/recovery, audit atomicity/redaction, explicit Admin permissions/revocation, additive permission seeding and preserved custom role grants, wrong account types/guest401,429/CORS and bounded list query behavior.

Full regression includes Auth/email/reset, Expert/Admin separation, KYC, Verified Scopes, profile publishing and RBAC. There were no reported PHPUnit warnings in the final run. The first run exposed missing runtime GD and a new Context validation mistake; both were resolved before the final passing runs. Only final results are release evidence.

Machine-readable results: [verification/results.json](verification/results.json), [route inventory](verification/api-routes.json), [migration steps](verification/migrations.json).

**Not tested:** Staging, React/browser integration, production DB engine row-lock/concurrency, multi-node cache/queue/storage, production policy publication, legal erasure execution or credential renewal. Row-lock design and local sequential/lease tests are not represented as proof of production concurrency. This delivery is a locally verified backend contract and implementation within the scoped deferrals, not end-to-end completion.

Delivery revision2 adds one integration test and its isolated child-process helper. Runtime application code and contract1.0.0 are unchanged from revision1. The actual queue worker, route cache and cleanup command now have local separate-process evidence.
