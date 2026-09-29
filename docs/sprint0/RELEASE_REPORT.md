# Sprint 0 delivery report — 2026-09-28

Baseline: the user-supplied `backend_28-9-2026.zip` (the copy under `upload/`). The first checkpoint is described below; a follow-up patch was prepared after the backend owner's Windows test run. Nothing was deployed to Laravel Cloud. No `.env`, production data, database migration, package dependency, or frontend file was changed.

## Third Windows run: no failed assertions, warnings and one style finding remain

The newest transcript reports `php artisan test`: **146 warnings, 1 clean pass, 731 assertions, 53.91 seconds**, with **no failed tests**. The previously failing CORS assertion now passes; Admin token changes and the Expert profile regressions also have no failures. The transcript prints only abbreviated per-test warnings (`file_get_contents(...)` referencing the local project path); it does not include the complete warning message or stack trace. Do not infer the exact cause from the abbreviated output. `php artisan route:list --path=api` reports **67 routes**, consistent with the prior run and no changed route declarations. `vendor/bin/pint --test` checked **168 files** and reported a single style finding in `config/permission.php`: `fully_qualified_strict_types`. `composer validate --strict` and a Staging smoke run are not included in the transcript.

That style finding was caused by the preceding patch's `\DateInterval` spelling. The last code correction uses `DateInterval::createFromDateString('24 hours')` in the global namespace and retains the Spatie model imports. It restores the original DateInterval syntax without reintroducing the ineffective `use DateInterval;` warning. **No Pint or PHP execution after this last correction is available in the current environment.** This package has no migration, route change, dependency, `.env`, or frontend modification.

Before accepting Sprint 0, run the following in a disposable local checkout and send the complete outputs, especially the first full warning diagnostic:

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpunit --filter=ConfigurationTest --display-warnings
php artisan test
```

The PHPUnit diagnostic command is intended to expose the abbreviated `file_get_contents(...)` warning. If warnings persist, investigate their source using that full message; do not edit production `.env` or suppress the warnings just to report a green gate. The route count was verified as 67 in this latest run; it needs no extra rerun for this style-only change. Staging smoke evidence is still pending. Sprint 1 remains blocked until final Sprint 0 results and explicit owner approval.

## Second Windows run and pending final correction

After the first follow-up, the owner supplied a second `php artisan test` transcript: **1 failed, 145 warnings, 1 passed (730 assertions), 41.94 seconds**. The only failed assertion was `ConfigurationTest::test_cors_preflight_does_not_allow_an_unconfigured_origin`: an `Access-Control-Allow-Origin` header was present, but the transcript does not show its value. The previous Admin authorization and Expert profile failures did not recur. `vendor/bin/pint --test` **passed all 168 files** before the final two edits. This second attachment contains no route-list or Composer-validation result. The previously observed route list had **67 API routes**; no route declarations were changed by either patch. A PHP warning also identified `use DateInterval;` in global `config/permission.php` as having no effect. Most per-test warnings point to local `.env` access; their complete details were not supplied.

Final correction: the CORS test now verifies that a disallowed origin is neither echoed back in `Access-Control-Allow-Origin` nor enabled by `*`; the companion test still verifies an allowed origin exactly. The reason is browser CORS behavior: a header can exist and still reject the requesting origin when its value differs. The `DateInterval` reference is now fully qualified without the ineffective import. Only `tests/Feature/Authentication/ConfigurationTest.php` and `config/permission.php` change in application/test PHP, along with this status documentation. **No PHP test or Pint run after these final edits is available.** If the revised CORS test fails, inspect the actual header value and treat an echoed untrusted origin or `*` as a production CORS defect. Sprint 0 is not yet accepted or deployed.

Focused final retry after applying the consolidated Sprint 0 package to the original source archive (or the third small patch over both earlier packages), using a disposable test checkout:

```bash
composer validate --strict
php artisan test --filter=ConfigurationTest
php artisan test
php artisan route:list --path=api
vendor/bin/pint --test
```

Inspect full warning details and verify that no PHP `DateInterval` warning remains. Record the configured local test environment without copying credentials into reports. Stop the release gate on any remaining failure; the suite's warnings must be resolved or explicitly triaged before acceptance.

## Follow-up from the backend owner's Windows run

The attached console output reports `php artisan test`: **6 failed, 140 warnings, 1 passed, 723 assertions, 73.14 seconds**. `php artisan route:list --path=api` registers **67 routes**; no missing or extra route was reported relative to the static inventory. `vendor/bin/pint --test` checked **168 files and found 5 files with style issues**. `composer install` reached optimized autoload generation but was manually interrupted (`^C`); successful completion is not established. The attachment does not include `composer validate --strict`, `php artisan about`, `php artisan migrate:status`, or PHPStan output.

| Observed failure | Targeted correction prepared | Status |
| --- | --- | --- |
| Dynamic Admin test: owner token unexpectedly 403 after a manager-token request, and inactive Admin unexpectedly 200 within the same test method. | Forget the test application's auth guards between requests to force each bearer token and updated Admin row to be re-resolved, as happens across independent HTTP requests. Production middleware and permissions unchanged. | Needs rerun. |
| Three CORS tests expected hardcoded port 3000 while runtime returned port 5173 or an allowed origin. | Configure a deterministic origin list within those tests and test both allowed and disallowed preflights. No production CORS change. Staging origins still require separate review. | Needs rerun. |
| Expert profile test received `isPublished: null` instead of `false`. | Cast the Resource output to boolean for a newly created model whose DB default has not yet been hydrated. Notify the frontend of this corrected response shape. | Needs rerun. |
| Pint listed `Admin/KycController.php`, `Models/User.php`, `ExpertKycWorkflow.php`, `config/permission.php`, and `routes/api.php`. | Apply import ordering, brace positioning, and imported class names in those exact files. The operator-spacing findings require a new Pint run to confirm. | Needs rerun. |
| 140 test warnings reference a missing local `.env`. | Do not ship or edit `.env`. The owner should complete Composer and run the test suite in a disposable local/test checkout with appropriate test-only environment preparation, then provide full warning detail if warnings remain. | Unresolved environment warning. |

The follow-up patch modifies `CHANGES.md`, this report, `docs/sprint0/STATUS.md`, `docs/api/CHANGELOG.md`, `tests/Feature/Admin/DynamicAuthorizationTest.php`, `tests/Feature/Authentication/ConfigurationTest.php`, `app/Http/Resources/Expert/Profile/ProfessionalProfileResource.php`, and the five Pint-listed PHP files. It adds no migration, dependency, route, or credential. Roll back by reverting this isolated follow-up patch/commit; the first Sprint 0 checkpoint can be rolled back separately. Do not roll back data or the older RBAC migration.

Focused retry after applying the follow-up patch:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
php artisan test --filter=DynamicAuthorizationTest
php artisan test --filter=ConfigurationTest
php artisan test --filter=ExpertProfileTest
php artisan test
php artisan route:list --path=api
vendor/bin/pint --test
```

If Pint still reports issues, run the local `vendor/bin/pint` on the specifically listed files in a separate temporary branch, inspect its diff, and send that diff/results before merging. The initial 140 warnings must not be counted as passes; capture full warning details separately. The Sprint 0 Definition of Done and Staging gate remain open.

## Summary and reasons for the changed files

The table below is the **original checkpoint** relative to the user-supplied archive; follow-up edits are listed in the section above.

| Path | New / changed | Reason |
| --- | --- | --- |
| `CHANGES.md` | Changed | Identify Sprint 0 delta and distinguish historical RBAC deployment notes. |
| `.github/workflows/backend-ci.yml` | New | Run a future PHP 8.3 SQLite CI gate: Composer validation/install, Artisan inspection, tests, and Pint. This workflow was not run here. |
| `docs/api/ROUTE_INVENTORY.md` | New | Preserve static inventory of current API routes and access middleware. |
| `docs/api/ERROR_CODES.md` | New | Distinguish existing errors from proposed frontend-facing codes. |
| `docs/api/CONTRACT_TEMPLATE.md` | New | Reusable contract for new endpoints while protecting existing response shapes. |
| `docs/api/CHANGELOG.md` | New | Record API compatibility and the nonimplemented eligibility proposal. |
| `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md` | New | Review-ready next-sprint KYC/scope request, error, gate and frontend migration proposal; no endpoint implemented. |
| `docs/architecture/ADR-001-account-separation-and-api-versioning.md` | New | Record the accepted separate User/Expert/Admin models and `/api/*` versioning policy. |
| `docs/architecture/ADR-002-expert-eligibility-and-verification.md` | New | Record the approved identity plus evidence-backed scope rule and the exact current code gap. |
| `docs/architecture/AUDIT_EVENTS.md` | New | Audit event baseline for future lifecycle and permission decisions. |
| `docs/operations/LARAVEL_CLOUD_STARTER.md` | New | Record database, private storage, queue, mail and deployment gates without secrets. |
| `docs/product/GLOBAL_PILOT_SCOPE.md` | New | Global access, proposed first domains, regulated-service gates and accepted verification policy. |
| `docs/sprint0/STATUS.md` | New | State completed analysis and missing runtime acceptance gate. |
| `docs/sprint0/RELEASE_REPORT.md` | New | This file: actual checks, change inventory, commands, Staging and rollback. |
| `tests/Feature/Expert/Kyc/ExpertKycRegressionTest.php` | New | Regressions for account isolation, limited Expert KYC access, submission and private files. |
| `tests/Feature/Admin/DynamicAuthorizationTest.php` | New | Regressions for live role revocation and inactive Admin token. |

The original two test files could not be run in the first environment. The later owner's run reported warnings and two failures in `DynamicAuthorizationTest`; its corrected version has not yet been rerun. The current application permits an Admin to approve a legal-domain KYC application without a licence: `scopes` is optional and a default active scope is created. This is an identified gap for the accepted next implementation, not a fix claimed in this patch. The KYC approval contract remains unchanged. The Expert profile `isPublished` boolean correction is described above.

## Actual checks in this execution environment

| Check / command | Observed result | Interpretation |
| --- | --- | --- |
| Python ZIP integrity check on source | OK, no corrupt entry. | Source archive readable. |
| Parse `composer.json`, `composer.lock`, `package.json` | OK; all direct dependency names appear in lock; lock has a content hash. | Does not validate Composer content hash or install compatibility. |
| Parse `.github/workflows/backend-ci.yml` with PyYAML | OK. | Syntax check only; no CI run. |
| Static count of `routes/api.php` definitions | 67 Route declarations, 68 method/path combinations (one PUT/PATCH match); documentation has 66 rows because one row combines PUT/PATCH. | Static source review; **not** Laravel registration or runtime middleware resolution. |
| `composer validate --strict` | Exit 127: `composer: command not found`. | Composer absent. |
| `composer install --no-interaction --prefer-dist` | Exit 127: `composer: command not found`. | No `vendor/` produced. |
| `php artisan about` | Exit 127: `php: command not found`. | PHP absent. |
| `php artisan route:list --path=api` | Exit 127: `php: command not found`. | No actual Laravel route list. |
| `php artisan migrate:status` | Exit 127: `php: command not found`. | No DB connection or migration was attempted. |
| `php artisan test` | Exit 127: `php: command not found`. | No test result; new and existing tests unverified. |
| `vendor/bin/pint --test` | Exit 127: `vendor/bin/pint: No such file or directory`. | Formatting not validated. |
| `vendor/bin/phpstan analyse` | Exit 127: `vendor/bin/phpstan: No such file or directory`. | PHPStan/Larastan is not a direct installed dependency; no static PHP analysis result. |

The patch ZIP is separately checked for path preservation, excluded files and archive integrity when built. The release gate remains open. Do not call Sprint 0 production-ready until tests and formatting pass under PHP 8.3+ with locked dependencies, and Staging behavior is checked.

## Remaining risks and acceptance gates

1. **Verification:** regulated scopes can currently be approved without licence evidence or an explicit scope; no status/recheck source is retained. No new regulated service should be made purchasable based on that flag. ADR-002 and the next-sprint proposal define the fix and frontend dependency.
2. **Runtime:** PHP/Composer/`vendor` were unavailable. CI exists but has not produced a green run. Verify on disposable SQLite and, if production uses MySQL, a disposable MySQL database.
3. **Production infrastructure:** confirm the existing DB engine, durable private KYC storage, persistent avatars, queue and mail delivery before release. Local disk on Laravel Cloud must not be assumed persistent. Provider approvals are needed for global payments and regulated services.
4. **API:** present `scopes` optionality is consumed by the current Admin contract. Any future regulated approval validation or new fields must be coordinated with React developers before rollout.
5. **SRS traceability:** ADR-001 captures the accepted exception to BR-002; include it in the next approved SRS revision. ADR-002 aligns with BR-016, BR-017 and BR-020 but is not enforced yet.

## API contract prepared for the next sprint

- `docs/api/CONTRACT_TEMPLATE.md`: envelope, authorization, request, Resource, errors, pagination, filtering, sorting, compatibility record.
- `docs/api/ERROR_CODES.md`: current behavior versus proposed stable codes.
- `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md`: concrete existing-endpoint compatibility, proposed private evidence fields and new scope status action, test fixtures and frontend rollout gate. **Proposal only**; no new endpoint or status code is live.

## Commands to run in an isolated PHP 8.3+ checkout

Use a clean checkout, a disposable database, and test-safe configuration. Do not use production credentials or run migrations/seeders against production for this acceptance test.

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
php artisan about
php artisan route:list --path=api
php artisan migrate:status
php artisan test
vendor/bin/pint --test
```

PHPStan/Larastan is not in `composer.json`; do not add it silently or report a successful run. If MySQL is the actual production engine, run migrations and feature tests against a disposable restored/sanitized MySQL database after checking the target connection. The CI workflow currently exercises SQLite memory.

## Apply to Staging (after the runtime gate is green)

1. Record the exact Staging Git commit and back up the Staging database and private files. Confirm no active deployment points to Production and verify the Staging environment's database and mail/queue destinations.
2. Create a Staging branch/checkout from the **same backend baseline** and inspect the patch archive entries. Copy/extract the patch at the repository root so its relative paths are preserved. No new `.env` or vendor files are supplied.
3. Run the commands above in that checkout. Stop on any Composer, test, route or formatting failure; resolve only the observed failure and rerun the gate. Compare `php artisan route:list --path=api` against the baseline: this patch should not add or remove a route.
4. Commit the patch and deploy that commit to a **Staging** Laravel Cloud environment through its configured repository deployment. The CI workflow will run only if the repository's GitHub Actions configuration permits it; inspect its actual run result.
5. Smoke-check existing User/Expert/Admin authentication, limited Expert KYC access, Admin KYC permission checks, private document downloads and public expert profile behavior. Check mail/queue delivery and whether private files survive a Staging redeploy. Do not enable new regulated paid services because the licence rule is not enforced yet.
6. Record Staging result and obtain explicit approval before any production promotion or Sprint 1 code.

This patch contains no migration and needs no `php artisan migrate --force` or seeder. The historical migration instructions lower in `CHANGES.md` apply to the older RBAC merge, **not** this patch.

## Rollback

- Preferred: redeploy the recorded previous Staging commit. Revert the Sprint 0 patch commit if it was isolated; ensure the CI workflow and docs/tests added by that commit disappear. Keep the database and private files intact.
- Without Git: restore `CHANGES.md` from the recorded Staging baseline and remove **only** the new paths in the change table after checking they did not pre-exist. Re-deploy the baseline. Never delete `.env`, data, migration tables or KYC files for this rollback.
- If a later sprint adds migrations or modifies API behavior, it requires its own rollback and data-preservation plan. This document does not authorize rolling back the old RBAC migration.

## Definition of Done status

Delivered: audit/contract documentation, decisions, diff-only package, regression test sources and CI configuration. Pending: actual green PHP tests, dynamic route registration, Composer validation, Pint, Staging smoke evidence, and infrastructure verification. **Sprint 0 is an implementation checkpoint, not a verified finished release.** Do not start Sprint 1 until these results are reported and approved by the backend owner.
