# SolveIt Back-End Sprint 2 Repair

## Package purpose

This changes-only package repairs the incomplete Sprint 2 merge in
`backend-2-10-2026.zip` (SHA-256:
`5e6f1fdd0beef6f99bd50b23ebe74528bb000669c7ef033cec043f82ff5e4a9e`).

The repair was reconstructed from the verified Case Core delivery
`backend-30-9-2026 (2).zip` (SHA-256:
`5cbfeb68ec9f76287bb5b49261708fc33e0a485eb2f6d4f77b6e8661953dc128`)
and the existing Sprint 2 v2/catalog delta. It preserves both the legacy Case Core
contract and the v2 catalog-backed contract.

## Summary

- Added project files: **89**
- Modified project files: **3**
- Package metadata: `CHANGED_FILES.md` and `PACKAGE_MANIFEST.sha256`
- Not included: `.env`, secrets, `vendor/`, caches, logs, generated views,
  runtime storage, test output, or unrelated files.
- The obsolete `SPRINT2_MANIFEST.sha256` was intentionally excluded because its
  historical hashes no longer describe the merged v2 tree. Use the package-level
  `PACKAGE_MANIFEST.sha256` instead.

## Added files

- `.github/workflows/backend-ci.yml` — added/restored.
- `app/Console/Commands/CleanupCaseDocuments.php` — added/restored.
- `app/Console/Commands/RetryCaseDocumentScans.php` — added/restored.
- `app/Enums/CaseDocumentScanStatus.php` — added/restored.
- `app/Enums/CaseStatus.php` — added/restored.
- `app/Enums/CaseSuitability.php` — added/restored.
- `app/Http/Controllers/Api/Admin/CaseOversightController.php` — added/restored.
- `app/Http/Controllers/Api/User/Cases/CaseController.php` — added/restored.
- `app/Http/Controllers/Api/User/Cases/CaseDocumentController.php` — added/restored.
- `app/Http/Requests/Cases/CaseListRequest.php` — added/restored.
- `app/Http/Requests/Cases/CaseMutationRequest.php` — added/restored.
- `app/Http/Requests/Cases/CaseRequest.php` — added/restored.
- `app/Http/Requests/Cases/IntakeRequest.php` — added/restored.
- `app/Http/Resources/Cases/CaseAssessmentResource.php` — added/restored.
- `app/Http/Resources/Cases/CaseContextSnapshotResource.php` — added/restored.
- `app/Http/Resources/Cases/CaseDocumentResource.php` — added/restored.
- `app/Http/Resources/Cases/CaseDocumentVersionResource.php` — added/restored.
- `app/Http/Resources/Cases/CaseTimelineResource.php` — added/restored.
- `app/Jobs/Cases/ScanCaseDocument.php` — added/restored.
- `app/Models/CaseContextSnapshot.php` — added/restored.
- `app/Models/CaseDocumentVersion.php` — added/restored.
- `app/Models/CaseDomain.php` — added/restored.
- `app/Models/CaseIntakeAssessment.php` — added/restored.
- `app/Models/CaseIntakeVersion.php` — added/restored.
- `app/Policies/CaseRecordPolicy.php` — added/restored.
- `app/Services/Cases/CaseDocumentStorage.php` — added/restored.
- `app/Services/Cases/ClamAvDocumentScanner.php` — added/restored.
- `app/Services/Cases/DocumentScanner.php` — added/restored.
- `database/factories/CaseRecordFactory.php` — added/restored.
- `database/migrations/2026_09_30_100000_create_case_core_tables.php` — added/restored.
- `database/seeders/CasePermissionsSeeder.php` — added/restored.
- `docs/adr/ADR-003-case-core-intake.md` — added/restored.
- `docs/sprint2/API_SCHEMAS.json` — added/restored.
- `docs/sprint2/DATABASE_MAPPING.md` — added/restored.
- `docs/sprint2/DECISIONS_AND_LIMITATIONS.md` — added/restored.
- `docs/sprint2/ENDPOINTS.md` — added/restored.
- `docs/sprint2/PERMISSION_MATRIX.md` — added/restored.
- `docs/sprint2/STATE_TRANSITIONS.md` — added/restored.
- `docs/sprint2/fixtures/admin-detail.json` — added/restored.
- `docs/sprint2/fixtures/admin-list.json` — added/restored.
- `docs/sprint2/fixtures/autosave-complete.json` — added/restored.
- `docs/sprint2/fixtures/bootstrap.json` — added/restored.
- `docs/sprint2/fixtures/cancelled.json` — added/restored.
- `docs/sprint2/fixtures/case-list.json` — added/restored.
- `docs/sprint2/fixtures/clarifications.json` — added/restored.
- `docs/sprint2/fixtures/context-attached.json` — added/restored.
- `docs/sprint2/fixtures/context-detached.json` — added/restored.
- `docs/sprint2/fixtures/document-clean.json` — added/restored.
- `docs/sprint2/fixtures/document-deleted.json` — added/restored.
- `docs/sprint2/fixtures/document-failed.json` — added/restored.
- `docs/sprint2/fixtures/document-list.json` — added/restored.
- `docs/sprint2/fixtures/document-not-ready-409.json` — added/restored.
- `docs/sprint2/fixtures/document-pending.json` — added/restored.
- `docs/sprint2/fixtures/document-rejected.json` — added/restored.
- `docs/sprint2/fixtures/document-replacement-failed.json` — added/restored.
- `docs/sprint2/fixtures/document-replacement-rejected.json` — added/restored.
- `docs/sprint2/fixtures/document-versions.json` — added/restored.
- `docs/sprint2/fixtures/draft-created.json` — added/restored.
- `docs/sprint2/fixtures/draft-resumed.json` — added/restored.
- `docs/sprint2/fixtures/guest-401.json` — added/restored.
- `docs/sprint2/fixtures/human-triage-required.json` — added/restored.
- `docs/sprint2/fixtures/incomplete-submit-422.json` — added/restored.
- `docs/sprint2/fixtures/intake-confirmed.json` — added/restored.
- `docs/sprint2/fixtures/needs-information.json` — added/restored.
- `docs/sprint2/fixtures/not-found-404.json` — added/restored.
- `docs/sprint2/fixtures/rate-limit-429.json` — added/restored.
- `docs/sprint2/fixtures/ready-for-matching.json` — added/restored.
- `docs/sprint2/fixtures/suitable-assessment.json` — added/restored.
- `docs/sprint2/fixtures/timeline.json` — added/restored.
- `docs/sprint2/fixtures/unsupported.json` — added/restored.
- `docs/sprint2/fixtures/urgent.json` — added/restored.
- `docs/sprint2/fixtures/validation-422.json` — added/restored.
- `docs/sprint2/fixtures/version-conflict-409.json` — added/restored.
- `docs/sprint2/fixtures/wrong-account-403.json` — added/restored.
- `docs/sprint2/verification/baseline-preservation.json` — added/restored.
- `docs/sprint2/verification/composer.txt` — added/restored.
- `docs/sprint2/verification/final-review.json` — added/restored.
- `docs/sprint2/verification/focused-tests.json` — added/restored.
- `docs/sprint2/verification/full-suite.json` — added/restored.
- `docs/sprint2/verification/migrations.json` — added/restored.
- `docs/sprint2/verification/pint.json` — added/restored.
- `docs/sprint2/verification/queue-integration.json` — added/restored.
- `docs/sprint2/verification/routes.json` — added/restored.
- `docs/sprint2/verification/static-analysis-unavailable.txt` — added/restored.
- `docs/sprint2/verification/syntax.json` — added/restored.
- `routes/cases_v2.php` — added/restored.
- `scripts/verify_case_concurrency.py` — added/restored.
- `tests/Feature/Cases/CaseContextAndAdminTest.php` — added/restored.
- `tests/Feature/Cases/CaseDocumentsTest.php` — added/restored.

## Modified files

- `app/Services/Privacy/AccountExport.php` — modified.
- `config/filesystems.php` — modified.
- `routes/cases.php` — modified.

## Why the three existing files were modified

- `routes/cases.php`: restores the original 22-route Case Core API. The v2 routes
  remain isolated in the added `routes/cases_v2.php` file.
- `config/filesystems.php`: restores the private `case-documents` disk required
  for encrypted Case attachments and protected downloads/previews.
- `app/Services/Privacy/AccountExport.php`: records Case data as outside the v1
  privacy export contract instead of incorrectly claiming that Case data is not
  implemented.

## Apply order

1. Back up the database, the private storage volume, and the current source tree.
2. Extract this ZIP directly over the Laravel project root, preserving paths.
3. Install the locked dependencies:

   ```bash
   composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   ```

4. Clear stale bootstrap/config/route caches:

   ```bash
   php artisan optimize:clear
   ```

5. Apply the additive migrations and permissions/catalog data:

   ```bash
   php artisan migrate --force
   php artisan db:seed --class=CasePermissionsSeeder --force
   php artisan db:seed --class=CatalogPermissionsSeeder --force
   php artisan db:seed --class=ServiceCatalogSeeder --force
   ```

6. Provision a real malware scanner and queue worker. In production,
   `CASE_DOCUMENT_SCANNER_BINARY` must point to the absolute executable path
   (for example `/usr/bin/clamscan`). Do not use the test scanner.
7. Rebuild production caches and restart workers:

   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan queue:restart
   php artisan schedule:list
   ```

8. Confirm that both route generations exist:

   ```bash
   php artisan route:list --path=api/user/cases
   php artisan route:list --path=api/v2/user/cases
   ```

## Verification commands

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
vendor/bin/phpstan analyse -c phpstan-legacy.neon --no-progress --memory-limit=1G
php artisan migrate:fresh --env=testing
php artisan test
```

Use an isolated test database for `migrate:fresh`; never run it against
production. The actual reconstructed tree passed 282/282 tests with 2548
assertions, Pint, both PHPStan configurations, route caching, and a clean
migration/seed cycle.

## Deployment notes

- The `case-documents` disk is private and local by default. A multi-instance
  deployment must mount shared durable private storage or configure an equivalent
  private object-storage disk before accepting uploads.
- Run a supervised queue worker. Scans remain pending without a worker.
- Keep the same `APP_KEY` on all web and worker instances; changing it makes
  encrypted Case documents unreadable.
- Run the scheduler so orphan cleanup and catalog review jobs execute.
- Validate the v1 and v2 API contracts against the real staging database engine.

## Rollback

- Prefer restoring the source backup while retaining the new tables and private
  files once real Case data exists.
- Do not run `migrate:fresh` or destructively roll back populated Case tables.
- If no Case writes have ever occurred, an operator may roll back the new
  migrations only inside a verified maintenance window after stopping workers.

## Package metadata

- **Added:** `CHANGED_FILES.md` — this installation and file manifest.
- **Added:** `PACKAGE_MANIFEST.sha256` — SHA-256 checksums for every other file
  in the archive.
