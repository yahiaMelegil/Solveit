# Sprint1 Changes-Only — revision3, 2026-09-30 Asia/Jerusalem

Cumulative against the uploaded Sprint0 baseline (local commit681ca02). Preserve paths and apply
at the Laravel root. Includes all previously delivered core-profile/context/consent/privacy work
plus the approved single-scope Expert renewal addition. This replaces revision2 of this bundle.
No deleted files; no vendor, credentials, local databases or unchanged Composer manifests included.

Latest:227 tests/1564 assertions passed; focused renewal16/231; Pint and Composer strict passed;
108 API routes,15 renewal routes;2 new renewal tables (13 total new Sprint1 tables).
Final deletion/anonymization remain excluded. Staging/React and production DB concurrency untested.
Full deployment/permissions/states/field mapping/handoff/20 renewal fixtures:
docs/sprint1/expert-renewal/README.md and docs/api/SPRINT1_EXPERT_RENEWAL_CONTRACT.md.
Earlier privacy fixtures and queue verification remain included. Do not confuse historical release
counts with the latest cumulative verification under docs/sprint1/expert-renewal/verification/.

## Upgrade commands

```bash
php artisan migrate --force
php artisan db:seed --class=PrivacyPermissionsSeeder --force
php artisan db:seed --class=ExpertRenewalPermissionsSeeder --force
php artisan route:cache
php artisan route:list --name=renewals
```

Review backups/private storage and test production-engine migrations/concurrency on Staging first.
No project .env modification is supplied. Re-running AuthorizationSeeder on existing installations
would sync role grants; use the additive seeders above instead. Read existing privacy deployment
gates for policy publication, rights SLA, worker and scheduler. No irreversible data executor added.

## Shared-file impact of renewal

- Expert and ExpertVerifiedScope: renewal relations only.
- ExpertKycWorkflow: expose its existing evidence validator for reuse; no validation rule rewrite.
- AdminPermission/AuthorizationSeeder: four independent renewal permissions; upgrade seeder additive.
- bootstrap/app.php: typed409 errors on explicitly enrolled SprintOneApi routes, including renewal.
- routes/api.php: load additive renewal routes.
- ADR002/docs: record accepted addition, retain previous decisions/evidence as historical.

## Added files (187)

- SPRINT1_MANIFEST.sha256
- app/Console/Commands/CleanupPrivacyArtifacts.php
- app/Console/Commands/RetryPrivacyRequest.php
- app/Enums/ConsentDecision.php
- app/Enums/ContextStatus.php
- app/Enums/DataRequestStatus.php
- app/Enums/DataRequestType.php
- app/Enums/ExpertRenewalStatus.php
- app/Exceptions/PrivacyException.php
- app/Http/Controllers/Api/Admin/ExpertRenewalController.php
- app/Http/Controllers/Api/Admin/PrivacyOversightController.php
- app/Http/Controllers/Api/Expert/Renewal/RenewalController.php
- app/Http/Controllers/Api/User/Privacy/ConsentController.php
- app/Http/Controllers/Api/User/Privacy/ContextController.php
- app/Http/Controllers/Api/User/Privacy/DataRightsRequestController.php
- app/Http/Controllers/Api/User/Privacy/PasswordConfirmationController.php
- app/Http/Controllers/Api/User/Privacy/PreferenceController.php
- app/Http/Controllers/Api/User/Privacy/PrivacyResponses.php
- app/Http/Controllers/Api/User/Privacy/ProfileController.php
- app/Http/Middleware/SprintOneApi.php
- app/Http/Requests/Expert/Renewal/RenewalRequest.php
- app/Http/Requests/User/Privacy/ConfirmPasswordRequest.php
- app/Http/Requests/User/Privacy/ConsentDecisionRequest.php
- app/Http/Requests/User/Privacy/ContextRequest.php
- app/Http/Requests/User/Privacy/ListPrivacyRequest.php
- app/Http/Requests/User/Privacy/PrivacyRequest.php
- app/Http/Requests/User/Privacy/StoreDataRequest.php
- app/Http/Requests/User/Privacy/TransitionRequest.php
- app/Http/Requests/User/Privacy/UpdatePreferencesRequest.php
- app/Http/Requests/User/Privacy/UpdateProfileRequest.php
- app/Http/Resources/Admin/ConsentMetadataResource.php
- app/Http/Resources/Admin/DataRequestMetadataResource.php
- app/Http/Resources/Expert/Renewal/RenewalResource.php
- app/Http/Resources/User/Privacy/ConsentResource.php
- app/Http/Resources/User/Privacy/ContextResource.php
- app/Http/Resources/User/Privacy/ContextVersionResource.php
- app/Http/Resources/User/Privacy/DataRequestResource.php
- app/Http/Resources/User/Privacy/PolicyResource.php
- app/Http/Resources/User/Privacy/PreferenceResource.php
- app/Http/Resources/User/Privacy/ProfileResource.php
- app/Jobs/Privacy/ProcessDataRightsRequest.php
- app/Models/AuditEvent.php
- app/Models/Concerns/AppendOnly.php
- app/Models/DataRightsRequest.php
- app/Models/DataRightsRequestItem.php
- app/Models/ExpertScopeRenewal.php
- app/Models/ExpertScopeRenewalSubmission.php
- app/Models/IdempotencyRecord.php
- app/Models/PolicyVersion.php
- app/Models/SpecializedContext.php
- app/Models/SpecializedContextVersion.php
- app/Models/UserConsentRecord.php
- app/Models/UserPreference.php
- app/Models/UserProfile.php
- app/Models/UserProfileVersion.php
- app/Policies/DataRightsRequestPolicy.php
- app/Policies/ExpertScopeRenewalPolicy.php
- app/Policies/SpecializedContextPolicy.php
- app/Policies/UserConsentRecordPolicy.php
- app/Policies/UserPreferencePolicy.php
- app/Policies/UserProfilePolicy.php
- app/Services/Expert/Renewal/ScopeRenewal.php
- app/Services/Privacy/AccountExport.php
- app/Services/Privacy/AuditWriter.php
- app/Services/Privacy/ConsentManager.php
- app/Services/Privacy/ContextManager.php
- app/Services/Privacy/DataRightsManager.php
- app/Services/Privacy/ExportDownload.php
- app/Services/Privacy/Idempotency.php
- app/Services/Privacy/PasswordConfirmation.php
- app/Services/Privacy/ProfileManager.php
- config/context_schemas.php
- config/countries.php
- config/data_rights.php
- config/expert_renewal.php
- config/privacy.php
- database/migrations/2026_09_29_000000_create_user_privacy_foundation.php
- database/migrations/2026_09_30_000000_create_expert_scope_renewals.php
- database/seeders/ExpertRenewalPermissionsSeeder.php
- database/seeders/PrivacyDevelopmentSeeder.php
- database/seeders/PrivacyPermissionsSeeder.php
- docs/api/SPRINT1_EXPERT_RENEWAL_CONTRACT.md
- docs/api/SPRINT1_PROFILE_CONTEXT_PRIVACY_CONTRACT.md
- docs/sprint1/core-profile-context-privacy/AUDIT_AND_SECURITY.md
- docs/sprint1/core-profile-context-privacy/DATABASE_MAPPING.md
- docs/sprint1/core-profile-context-privacy/DEFERRED.md
- docs/sprint1/core-profile-context-privacy/DEPLOYMENT.md
- docs/sprint1/core-profile-context-privacy/ENDPOINTS.md
- docs/sprint1/core-profile-context-privacy/EXPERT_REVIEW_BOUNDARY.md
- docs/sprint1/core-profile-context-privacy/FRONTEND_HANDOFF.md
- docs/sprint1/core-profile-context-privacy/PERMISSION_MATRIX.md
- docs/sprint1/core-profile-context-privacy/QUEUE_INTEGRATION.md
- docs/sprint1/core-profile-context-privacy/README.md
- docs/sprint1/core-profile-context-privacy/RELEASE_REPORT_AR.md
- docs/sprint1/core-profile-context-privacy/STATE_TRANSITIONS.md
- docs/sprint1/core-profile-context-privacy/VERIFICATION.md
- docs/sprint1/core-profile-context-privacy/fixtures/admin-consent-metadata.json
- docs/sprint1/core-profile-context-privacy/fixtures/admin-request-detail.json
- docs/sprint1/core-profile-context-privacy/fixtures/admin-request-list.json
- docs/sprint1/core-profile-context-privacy/fixtures/consent-declined.json
- docs/sprint1/core-profile-context-privacy/fixtures/consent-granted.json
- docs/sprint1/core-profile-context-privacy/fixtures/consent-history.json
- docs/sprint1/core-profile-context-privacy/fixtures/consent-regranted.json
- docs/sprint1/core-profile-context-privacy/fixtures/consent-withdrawn.json
- docs/sprint1/core-profile-context-privacy/fixtures/consents-effective.json
- docs/sprint1/core-profile-context-privacy/fixtures/consents-initial.json
- docs/sprint1/core-profile-context-privacy/fixtures/consents-reconsent-required.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-archived.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-clarified.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-conflict.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-created.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-deleted.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-detail.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-list.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-restored.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-schemas.json
- docs/sprint1/core-profile-context-privacy/fixtures/context-versions.json
- docs/sprint1/core-profile-context-privacy/fixtures/data-request-list.json
- docs/sprint1/core-profile-context-privacy/fixtures/deletion-cancelled.json
- docs/sprint1/core-profile-context-privacy/fixtures/deletion-deferred.json
- docs/sprint1/core-profile-context-privacy/fixtures/deletion-requested.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-401.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-403-reauthentication.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-403.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-404.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-409-export-expired.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-409.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-422.json
- docs/sprint1/core-profile-context-privacy/fixtures/error-429.json
- docs/sprint1/core-profile-context-privacy/fixtures/export-completed.json
- docs/sprint1/core-profile-context-privacy/fixtures/export-download.json
- docs/sprint1/core-profile-context-privacy/fixtures/export-expired.json
- docs/sprint1/core-profile-context-privacy/fixtures/export-failed.json
- docs/sprint1/core-profile-context-privacy/fixtures/export-processing.json
- docs/sprint1/core-profile-context-privacy/fixtures/export-requested.json
- docs/sprint1/core-profile-context-privacy/fixtures/password-confirmed.json
- docs/sprint1/core-profile-context-privacy/fixtures/policies.json
- docs/sprint1/core-profile-context-privacy/fixtures/preferences-initial.json
- docs/sprint1/core-profile-context-privacy/fixtures/preferences-updated.json
- docs/sprint1/core-profile-context-privacy/fixtures/profile-initial.json
- docs/sprint1/core-profile-context-privacy/fixtures/profile-updated.json
- docs/sprint1/core-profile-context-privacy/fixtures/request-rejected-reserved.json
- docs/sprint1/core-profile-context-privacy/verification/api-routes.json
- docs/sprint1/core-profile-context-privacy/verification/migrations.json
- docs/sprint1/core-profile-context-privacy/verification/queue-integration.json
- docs/sprint1/core-profile-context-privacy/verification/results.json
- docs/sprint1/expert-renewal/README.md
- docs/sprint1/expert-renewal/VERIFICATION.md
- docs/sprint1/expert-renewal/fixtures/admin-detail.json
- docs/sprint1/expert-renewal/fixtures/admin-queue.json
- docs/sprint1/expert-renewal/fixtures/approved.json
- docs/sprint1/expert-renewal/fixtures/cancelled.json
- docs/sprint1/expert-renewal/fixtures/draft.json
- docs/sprint1/expert-renewal/fixtures/duplicate-409.json
- docs/sprint1/expert-renewal/fixtures/error-401.json
- docs/sprint1/expert-renewal/fixtures/error-403.json
- docs/sprint1/expert-renewal/fixtures/error-404.json
- docs/sprint1/expert-renewal/fixtures/evidence-replaced.json
- docs/sprint1/expert-renewal/fixtures/evidence-uploaded.json
- docs/sprint1/expert-renewal/fixtures/expert-detail.json
- docs/sprint1/expert-renewal/fixtures/expert-list.json
- docs/sprint1/expert-renewal/fixtures/needs-information.json
- docs/sprint1/expert-renewal/fixtures/rejected.json
- docs/sprint1/expert-renewal/fixtures/resubmitted.json
- docs/sprint1/expert-renewal/fixtures/scopes-eligible.json
- docs/sprint1/expert-renewal/fixtures/submitted.json
- docs/sprint1/expert-renewal/fixtures/under-review.json
- docs/sprint1/expert-renewal/fixtures/validation-422.json
- docs/sprint1/expert-renewal/verification/focused-tests.json
- docs/sprint1/expert-renewal/verification/full-suite.json
- docs/sprint1/expert-renewal/verification/migrations.json
- docs/sprint1/expert-renewal/verification/pint.json
- docs/sprint1/expert-renewal/verification/results.json
- docs/sprint1/expert-renewal/verification/routes.json
- routes/expert_renewals.php
- routes/privacy.php
- tests/Feature/Expert/Renewal/ScopeRenewalTest.php
- tests/Feature/Privacy/AuthorizationPrivacyTest.php
- tests/Feature/Privacy/BoundaryPrivacyTest.php
- tests/Feature/Privacy/ConsentPrivacyTest.php
- tests/Feature/Privacy/ContextPrivacyTest.php
- tests/Feature/Privacy/ContractFixturesTest.php
- tests/Feature/Privacy/DataRightsPrivacyTest.php
- tests/Feature/Privacy/PrivacyTestCase.php
- tests/Feature/Privacy/ProfilePrivacyTest.php
- tests/Feature/Privacy/QueuedPrivacyIntegrationTest.php
- tests/Support/privacy_queue_process.php

## Modified files (16)

- CHANGED_FILES.md
- app/Enums/AdminPermission.php
- app/Models/Expert.php
- app/Models/ExpertVerifiedScope.php
- app/Models/User.php
- app/Providers/AppServiceProvider.php
- app/Services/Kyc/ExpertKycWorkflow.php
- bootstrap/app.php
- config/cors.php
- config/filesystems.php
- database/seeders/AuthorizationSeeder.php
- docs/api/CHANGELOG.md
- docs/architecture/ADR-002-expert-eligibility-and-verification.md
- docs/architecture/AUDIT_EVENTS.md
- routes/api.php
- routes/console.php
