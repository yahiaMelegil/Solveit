# Sprint 1 Expert eligibility — implementation report (2026-09-28)

## Summary

The owner approved implementation of the previously documented Expert professional-verification rule. This source patch changes the existing Admin KYC approval, not authentication or account models. Every new approval must select at least one explicit, reviewed evidence-backed scope. For regulated domains, the selected evidence must be a current licence with its own reviewed private document. A permitted Admin records the scope's licensed country (which may differ from residence), regulator, registration number, official HTTPS source, active status, and next review date after a real manual register check. The scope cannot be valid after the earlier of licence expiry and its next review. The existing transaction validates before changing KYC status or revoking any old scope.

Nonregulated domains require reviewed credential/qualification evidence or an experience record with a reviewed CV/work sample. Domains missing from `config/expert_verification.php` fail closed; the initial lists are product policy, not assertions about the law in every country. The Expert can still log in and use the limited KYC workspace without approval. The old separate `User`, `Expert`, and `Admin` models, `/api/*` routes, Sanctum abilities and Admin permission checks are unchanged. The backend does not query or scrape regulators: Admin input records a human review.

## Files added

| Path | Reason |
| --- | --- |
| `config/expert_verification.php` | Initial regulated/nonregulated domain policy; unknown domains fail closed. |
| `database/migrations/2026_09_28_000000_add_evidence_review_to_expert_verified_scopes_table.php` | Add nullable private evidence/review fields and recheck index without deleting or fabricating historical records. |
| `docs/sprint1/FRONTEND_HANDOFF_AR.md` | Exact Admin and Expert UI payloads, errors, and safe rollout order. |
| `docs/sprint1/EXPERT_VERIFICATION_RULE_AR.md` | Arabic statement of the approved global eligibility rule and operational limits. |
| `docs/sprint1/RELEASE_REPORT.md` | This evidence, change manifest, Staging plan and rollback. |

## Files modified

| Path | Reason |
| --- | --- |
| `CHANGES.md` | Record the approved Sprint 1 change separately from Sprint 0 history. |
| `app/Http/Requests/Admin/Kyc/ApproveKycApplicationRequest.php` | Enforce the existing Admin KYC Gate before validation, then require `scopes` and typed evidence and validate optional private reviewer input and dates. |
| `app/Http/Controllers/Api/Admin/KycController.php` | Pass the validated explicit scopes without the empty fallback; existing Gate stays in place. |
| `app/Services/Kyc/ExpertKycWorkflow.php` | Validate same-application reviewed evidence, licence, country, authority review, bounded validity, and reject unknown domains before transactional approval; remove implicit active scope creation. |
| `app/Models/ExpertVerifiedScope.php` | Allow and cast private evidence/review metadata. |
| `app/Http/Resources/Admin/Kyc/KycApplicationDetailResource.php` | Add Admin-only `professionalReviews`; Expert/public resources still omit private metadata. |
| `app/Http/Resources/Expert/Profile/VerifiedScopeResource.php` | Add public-safe licensed `jurisdictionCountry` to scope output; null for historic scopes. |
| `tests/Feature/Admin/Kyc/AdminKycTest.php` | Update the valid legal fixture and add nine meaningful regressions for explicit scopes, missing/foreign/expired evidence, licensed-country/source/review rules, cross-country residence, unknown domain, nonregulated experience, atomic rejection, and forbidden-before-validation. |
| `docs/api/CHANGELOG.md` | Explain the changed Admin request and private additive response. |
| `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md` | Replace the Sprint 0 proposal with the actual source contract and limitations. |
| `docs/architecture/ADR-002-expert-eligibility-and-verification.md` | Record the implemented subset and the remaining lifecycle/legacy gaps. |

No `.env`, vendor, logs, cache, credentials, composer dependency, unrelated migration, route, or frontend file is included.

## API contract and frontend impact

**A frontend change is required in the Admin KYC approval screen before deploying this backend:** `POST /api/admin/kyc/applications/{application}/approve` no longer accepts an empty request and returns `422 errors.scopes`. The screen must submit an explicit scope with `evidence.type` and `evidence.id` from this application. For a regulated domain it must also collect the two-letter licensed `jurisdictionCountry` and matching `professionalReview.verifiedCountry`, `regulator`, `registrationNumber`, `verificationSource`, `statusChecked=active`, and `nextReviewAt`. The scope jurisdiction text must equal the reviewed application; the licensed country can differ from Expert residence. Error keys use existing Laravel dot notation. The success status/envelope remains unchanged. Scope responses add public-safe `jurisdictionCountry` (nullable for old scopes); the Admin detail/approval response adds `data.application.professionalReviews` with private review metadata. Do not display that private metadata on public or Expert pages.

The Expert KYC endpoint already accepts licence credentials and document upload with `credentialId`; the Expert UI should expose that existing capability clearly for regulated domains and support requests for missing information. Expert login, KYC workspace access and base response shape do not change. The request's `authorize()` now enforces the existing Gate before payload validation, so an Admin without `experts.reviewKyc` gets 403 even for a malformed approval body. The controller retains its Gate. The prior Sprint 0 `isPublished: false` correction remains a separate frontend compatibility note.

See `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md` and `docs/sprint1/FRONTEND_HANDOFF_AR.md` for JSON examples and full field mapping. The initial policy catalogue is deliberately finite; classify further domains after specifying service and jurisdiction rules. Do not claim automatic licence verification.

## Actual verification and limits

The latest **Sprint 0 baseline**, before this source patch, passed direct PHPUnit (**147 tests, 731 assertions**), Pint (**168 files**), Composer validation and 67 registered API routes. Those results **do not verify this Sprint 1 patch**.

Attempts here to run `php -l app/Services/Kyc/ExpertKycWorkflow.php`, `php artisan route:list --path=api`, `composer validate --strict`, `vendor/bin/phpunit --filter=AdminKycTest --display-warnings`, and `vendor/bin/pint --test` all exited **127**: `php: command not found`, `composer: command not found`, or the vendor executable was absent. No PHP tests, migrations, Pint, CI, or Staging smoke were run on the new code. The source migration is additive; **do not claim it has applied successfully** until the owner runs it against disposable/Staging databases. Archive integrity and static source comparison are checked when producing the ZIP.

The owner's first Windows run of the initial Sprint 1 ZIP then reported `composer validate --strict` **valid** and `php artisan route:list --path=api` **67 routes**. Both `vendor/bin/phpunit --filter=AdminKycTest --display-warnings` and the full PHPUnit command stopped before running tests because `tests/Feature/Admin/Kyc/AdminKycTest.php:510` lacked a closing `]` in the `legalApproval()` return array. Pint reported the same parse error (170 files, 1 error). The extra closing bracket is fixed in this revised ZIP. **No successful PHP lint, test, or Pint output has been supplied for this revised ZIP yet.** The migration has not been applied or checked on Staging.

The owner's second Windows run of the revised ZIP passed `php -l tests/Feature/Admin/Kyc/AdminKycTest.php`. Its focused PHPUnit run completed **19 tests, 229 assertions, 1 failure**: the cross-country test expected Expert residence `JO`, but the factory generated `VA`; the KYC approval and scope-country assertions preceding it succeeded. This package explicitly sets the Expert fixture residence to `JO`. Pint inspected **170 files** and reported **one `braces_position` style issue in that test file**. The multiline helper signature and nested return were reformatted, but the exact Pint diff was not provided, so a passing style result cannot yet be claimed. The full test suite, Staging migration and CI results remain pending for this package.

## Ordered local and Staging verification

1. Apply the diff to a clean branch based on the exact Sprint 0 backend. Review the initial domain catalogue with the responsible policy owner. Back up the **Staging** database and private KYC documents before migrating. Do not run on Production for this verification.
2. Deploy the updated Admin KYC frontend to Staging first; it must send explicit evidence/review payloads. Existing Expert credential upload can be improved independently. Stop if the frontend still sends an empty approval body.
3. With test-safe credentials and a disposable database, run:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
php -l app/Services/Kyc/ExpertKycWorkflow.php
php -l tests/Feature/Admin/Kyc/AdminKycTest.php
php artisan route:list --path=api
php artisan migrate:status
vendor/bin/phpunit --filter=AdminKycTest --display-warnings
vendor/bin/phpunit --display-warnings
vendor/bin/pint --test
```

4. Verify the actual Staging database connection, deploy this backend patch to Staging, then run `php artisan migrate --force` **on Staging only** before serving API traffic with the new code. Check the added nullable columns and that historic scopes/rows remain intact. Coordinate Laravel Cloud's deploy/migrate release order so API traffic does not hit the new code before the columns exist. If production uses MySQL, repeat the migration and relevant tests on a disposable MySQL schema before Production promotion; SQLite tests alone do not prove MySQL DDL.
5. Smoke-check: legal application with reviewed licence and actual authority check approves; same application without licence, with evidence belonging to another application, expired licence, mismatched licensed/reviewed country, or unknown domain stays `under_review` with 422; legal registration in a different country than residence succeeds if application jurisdiction matches and the Admin verifies that scope country; unregulated reviewed experience approves; old Admin token without permission gets 403; Expert can still access limited KYC; private review fields appear only to Admin. Confirm expiry removes a scope from effective/public availability at the date boundary.
6. Inspect CI and log the results. Do not promote to Production or report Sprint 1 complete while any required test, formatting, DB, or frontend integration check fails.

## Rollback

For a failed Staging rollout, first revert/deploy the previous backend **and** Admin frontend commit so the old request behavior is restored. Preserve the additive columns and their review data during an immediate code rollback; the old code ignores them. If a schema rollback is later necessary, back up those columns and verify no new reviewed records depend on them before running the migration down; its `down()` drops the newly recorded evidence, so it is data-destructive. Never delete historic scopes or private KYC files to roll back this change.

## Open decisions and later work

- Historic approved scopes are unchanged and have null review fields. They were not automatically declared verified by the new rule. Inventory and manually re-review them **before enabling regulated paid bookings**; no Case/booking gate exists yet.
- The initial catalogue needs a reviewed policy per domain, service and jurisdiction. Unknown domains are blocked for new approval until classified. Human authority checks need an operations procedure; a submitted source URL is not itself proof.
- Scope renewal, manual suspension/revocation, adding another country's scope through a verified amendment, and automatic regulator-status changes are not implemented. A newly reviewed scope may name a licensed country different from residence, but the current application has a single jurisdiction. Regulated scopes expire by the stored validity date and can cease to appear publicly; a later renewal workflow must be approved before relying on perpetual availability.
- No frontend code is in this package. Admin UI integration is a deployment dependency and needs frontend team coordination. User and Expert auth, current RBAC and token separation were not redesigned.
