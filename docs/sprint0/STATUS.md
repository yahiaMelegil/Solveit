# Sprint 0 execution status — 2026-09-28

## Latest owner verification

The third Windows run shows **0 test failures, 146 warnings, 1 clean pass (731 assertions)**, **67 registered API routes**, and **one Pint style issue among 168 checked files** (`config/permission.php`, `fully_qualified_strict_types`). The CORS assertion, dynamic Admin authorization, and Expert profile behavior now pass their assertions. The DateInterval style issue has been corrected by using the unqualified global class name, restoring its original syntax without the ineffective import. This final single-line correction has not been rerun through Pint; the 146 warnings still require the full warning detail. Sprint 0 remains unaccepted until the warning cause, formatting rerun and Staging gate are verified.

Approved decisions: three separate account models; preserve current `/api/*` contract; version before a breaking API change. The first checkpoint changed documentation, CI and tests only. After the backend owner's Windows test run, a narrow follow-up changed `ProfessionalProfileResource` to emit `false` rather than `null` for a new unpublished profile, plus test isolation and style-only edits. No route definition, migration, seed, `.env`, package or frontend file changed.

The backend owner subsequently accepted expert eligibility per `docs/architecture/ADR-002-expert-eligibility-and-verification.md`: identity plus at least one evidence-backed effective scope, with an active jurisdiction-specific licence for regulated services. This is a product decision and a documented **implementation gap**. It is not represented as an enforcement change in this Sprint 0 package.

## Delivered in this archive

- API route inventory, compatibility changelog, error catalogue, and next-sprint contract template.
- ADR for account separation and API versioning; AuditEvent design baseline.
- Laravel Cloud deployment choices and global pilot scope proposal.
- Feature regression tests for Expert KYC isolation/transitions/files and live Admin permission changes.
- A CI workflow for a future GitHub repository; it does not deploy or access production.

## Verification evidence and remaining gate

The source archive passed ZIP integrity validation. `composer.json`, `composer.lock`, and `package.json` parse as JSON; all direct package names appear in the lock file. The CI YAML parses. A static comparison finds 67 Route declarations (68 method/path combinations), represented by 66 inventory rows because the PUT/PATCH route occupies one row. This is not a Laravel route registration check. PHP, Composer, and `vendor/` are absent in this execution environment. Actual command attempts and exit codes are in `docs/sprint0/RELEASE_REPORT.md`; `composer validate/install`, `artisan about/route:list/migrate:status/test`, and Pint did **not** run. The new tests are **unverified**, and Sprint 0 is **not yet done**. No claim of successful PHP execution is made.

The owner's first Windows run showed 67 registered API routes; 6 failures, 140 missing-`.env` warnings and 1 pass in the test suite; and 5 Pint style issues. The second run after corrections showed **1 failed, 145 warnings, 1 passed (730 assertions)** and **Pint PASS on 168 files**. The Admin and Expert profile failures were resolved; the sole remaining test failure concerned an overly strict CORS-header absence assertion. A final test correction checks that a disallowed origin is never echoed or wildcard-allowed, and an ineffective `DateInterval` import causing a PHP warning was removed. Neither final edit has a PHP rerun yet. The per-test warnings still reference a missing local `.env`; inspect complete warning details in a disposable test checkout. See `docs/sprint0/RELEASE_REPORT.md` for evidence and exact retry commands. The preceding paragraph describes the container's first inspection environment, not these later Windows runs.

Run the CI workflow or these commands in a clean PHP 8.3+ environment against a disposable database:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
php artisan about
php artisan route:list --path=api
php artisan test
vendor/bin/pint --test
```

If tests fail, capture the exact failing assertion and fix only the proven defect with a regression test. If MySQL is the production engine, also run the migration and suite on a disposable MySQL database; SQLite-only green tests cannot validate MySQL behavior. Do not run `migrate` or `db:seed` on production during Sprint 0 verification.

## Blockers before operational release

- Confirm current production DB engine and attached resource; preserve existing data.
- Choose private persistent storage and approve any required S3 adapter dependency; migrate existing KYC files safely. Avatar URLs also require a deliberate durable-storage migration.
- Attach and verify a queue and real mail delivery; monitor failed jobs.
- Provider and regulatory gates for payments, realtime, video, AI, malware scanning, and global paid services.
- The SRS must record the accepted BR-002 exception for separate account models.

Definition of Done remains pending actual green test and deployment-compatibility evidence. This archive is a reviewable Sprint 0 implementation checkpoint, not a production-ready release.
