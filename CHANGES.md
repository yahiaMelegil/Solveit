# Solveit backend change history — 2026-09-28

## Sprint 1 — Expert KYC evidence-backed approval (source patch)

The owner explicitly requested implementation after reviewing the Sprint 0 results. New Admin KYC approvals now require an explicit scope and reviewed evidence from the same application. Regulated domains additionally require an active licence, reviewed linked document, a verified country matching the scope's licensed country (which can differ from residence), regulator, registration number, authoritative HTTPS source, and next review within one year. The validated scope ends no later than licence expiry or next review. Unknown domains fail closed until classified in `config/expert_verification.php`; nonregulated domains accept reviewed qualification, credential, or experience evidence. Approval validation takes place inside the existing database transaction before changing the application status. Private review data is stored on the scope and returned only to the Admin KYC detail view. Public and Expert scope responses gain `jurisdictionCountry` (nullable for old scopes). Historic approved scopes are preserved unchanged, with review metadata null; they need manual re-review before regulated booking. No new route, package, account merge, or frontend code is included.

**This is a breaking Admin approval request change:** an empty `POST /api/admin/kyc/applications/{application}/approve` now returns 422; an Admin without the existing KYC permission receives 403 before validation. Update the Admin frontend before deploying this backend change. See `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md`, `docs/sprint1/EXPERT_VERIFICATION_RULE_AR.md`, and `docs/sprint1/FRONTEND_HANDOFF_AR.md`. A new additive migration is required. The owner's second Sprint 1 run confirmed lint passes and 18 of 19 Admin KYC tests pass, but one test expected residence `JO` from a random-country Expert factory and failed with `VA`; the fixture now explicitly uses `JO`. Pint also found one `braces_position` issue in the test file. The helper signature and nested return are formatted conservatively, but Pint has **not been rerun** against this third package. Full PHPUnit, migration, CI and Staging are pending. Do not deploy before verification. `docs/sprint1/RELEASE_REPORT.md` lists exact files, commands and rollback.

## Historical Sprint 0 checkpoint

## Verified Sprint 0 code checkpoint

The owner's final Windows verification passed direct PHPUnit: **147 tests, 731 assertions, no warnings**; Pint: **168 files passed**; `composer validate --strict`: valid; and Laravel route registration: **67 API routes**. The alternative `php artisan test` invocation displayed 146 abbreviated `file_get_contents(...)` warnings even though its assertions did not fail. Its exact warning source is not proven; CI now runs `vendor/bin/phpunit --display-warnings`, the complete warning-free suite actually verified. CI and Laravel Cloud Staging still need their own results. No new migration, feature, dependency, `.env`, or frontend file is in this patch. The final manifest, staging sequence and rollback are in `docs/sprint0/RELEASE_REPORT.md`. The sections below record the earlier troubleshooting sequence and the older RBAC baseline, not the current acceptance state.

## Third Windows verification and single style correction

The owner reported `php artisan test`: **0 failures, 146 warnings, 1 clean pass (731 assertions)**. `php artisan route:list --path=api` reports **67 routes**. `vendor/bin/pint --test` checks 168 files but reports one `fully_qualified_strict_types` issue in `config/permission.php`. The ineffective global `DateInterval` import was already removed; the remaining style fix uses `DateInterval::createFromDateString(...)` unqualified in this global-namespace configuration file, as it was in the original source. No permission-cache behavior changes. **Pint after this single-line correction and the full warning diagnostics are still pending.** See `docs/sprint0/RELEASE_REPORT.md` for the remaining checks.

## Second Windows test run and final small correction

After the first follow-up, the owner reported `php artisan test`: **1 failed, 145 warnings, 1 passed (730 assertions)**. The Admin token and Expert profile regressions no longer failed, and `vendor/bin/pint --test` passed all **168 files**. The sole remaining failure asserted that a disallowed CORS preflight had no `Access-Control-Allow-Origin` header; the response did have one, but the log did not print its value. The corrected test now checks that the header is neither the untrusted request origin nor `*`, while retaining the successful allowed-origin assertion. This checks whether a browser could accept the disallowed origin. The second run also showed a PHP warning from the imported global `DateInterval`; the config now uses `\DateInterval` without an ineffective `use` declaration. **These two final edits have not been rerun** in PHP. The 145 test warnings still need diagnosis in a disposable local environment; the visible per-test warning points to missing local `.env`.

## Follow-up after the first Windows test run

The backend owner supplied actual `artisan test`, `route:list`, and Pint output: 6 failed, 140 warnings, 1 passed; 67 API routes; 5 style issues. That follow-up patch fixed the proven `isPublished: null` response, isolated bearer-token and CORS regression tests from cached guard/configuration state, and applied targeted formatting to the five Pint-listed files. No auth policy, route definition, migration, dependency, or `.env` was changed. The new response for a newly created, unpublished expert profile is the boolean `false` rather than `null`; notify any frontend consumer that checked for null. The results after that patch are recorded above; the final correction still needs a rerun.

The original Sprint 0 checkpoint was relative to the supplied `backend_28-9-2026.zip`. It added docs, two regression test files, and a GitHub CI workflow. The follow-up above now modifies one profile Resource's initial response and formatting in application PHP files. `docs/sprint0/RELEASE_REPORT.md` gives the actual checks, Staging order, rollback and file lists. Neither patch alone completes the runtime Definition of Done.

Accepted product decision: `docs/architecture/ADR-002-expert-eligibility-and-verification.md` defines identity plus at least one evidence-backed effective scope as the Expert approval rule. The existing legal-domain approval fallback does **not** enforce professional evidence. It is a documented gap requiring a compatible, tested implementation after the Sprint 0 runtime gate, not a claim of an implemented fix. The KYC approval request and current `/api/*` response shape remain unchanged.

Do not run migrations or seeders because of this patch: it contains neither. Do not infer a passing PHP test suite from the presence of new test files. Validate and run them in a clean PHP 8.3+ environment with a disposable database before promoting this patch.

## Historical baseline: authentication and authorization merge

The remainder of this document was shipped with the source archive and describes **earlier** changes already present in that baseline. Its migration and seeder steps are not part of the Sprint 0 delta.

## Summary

This change set fixes regular-user email-verification enforcement and merges
administrator RBAC into the latest backend without replacing its KYC or Expert
Verified Scope implementation.

## Deployment order

1. Back up the application database.
2. Copy the delivered files while preserving their paths.
3. Validate the existing lock file and install its pinned dependencies in a clean environment:

   ```bash
   composer validate --strict
   composer install --no-interaction --prefer-dist
   ```

4. Clear cached configuration and discovered services:

   ```bash
   php artisan optimize:clear
   ```

5. Create the permission tables:

   ```bash
   php artisan migrate --force
   ```

6. Create/update the permission catalog and default roles:

   ```bash
   php artisan db:seed --class=AuthorizationSeeder --force
   ```

7. If the initial administrator has not received `super_admin`, configure
   `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`, then run:

   ```bash
   php artisan db:seed --class=AdminSeeder --force
   ```

8. Reset the permission cache and run tests:

   ```bash
   php artisan permission:cache-reset
   php artisan test
   ```

9. Run the formatter and inspect routes:

   ```bash
   vendor/bin/pint --test
   php artisan route:list --path=api
   ```

The 2026-09-28 archive's `composer.lock` includes `spatie/laravel-permission`
8.3.0, and all direct dependency names in `composer.json` appear in the lock.
Composer's content hash and runtime installation have not been validated in
the supplied workspace because PHP and Composer are unavailable. Do not deploy
before `composer validate --strict`, `composer install`, and the test suite pass
in a clean PHP environment. Do not run an unconstrained dependency update
merely to install the already locked version.

Sprint 0 adds documentation and regression tests without changing existing API
routes, responses, models, migrations, or application behavior. Its execution
status and remaining gates are in `docs/sprint0/STATUS.md`.

## Behavioral changes

- Registration returns a `user:verify-email` token, not `user:access`.
- Unverified login returns HTTP 403 with `code: EMAIL_NOT_VERIFIED` and a
  verification-only token.
- Invalid credentials return HTTP 401 with `code: INVALID_CREDENTIALS`.
- `/api/user` dynamically requires a verified email and `user:access`.
- Admin responses include roles and effective permissions when those relations
  are loaded.
- Admin management and role APIs are protected through policies and Gates.
- KYC review additionally requires `experts.reviewKyc`.

## Rollback

Application rollback should restore the previous versions of the modified
files and remove the files marked `Added` in `CHANGED_FILES.md`. Only after the
old application code is restored may the RBAC tables be removed by rolling back
the permission-table migration. Take a database backup first; rolling back that
migration deletes all role and permission assignments.
