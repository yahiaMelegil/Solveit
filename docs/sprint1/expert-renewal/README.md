# Sprint 1 expert renewal — release 1.0.0

The owner approved all five renewal rules on 2026-09-30 (Asia/Jerusalem).
This addition supersedes the earlier design-only renewal boundary. Final deletion and
anonymization remain explicitly excluded; their existing deferred request lifecycle is unchanged.

## Architecture and behavior

15 additive endpoints:8 Expert,7 Admin. Whole application now has108 API routes.
Existing account realms/Sanctum remain separate. Form Requests authorize before validation;
Resources expose explicit fields; owner queries and a policy isolate Expert requests;
Admin controllers use Gate::authorize and independent permissions. ScopeRenewal owns
transactions, state/version checks, row locks, private uploads and approval. ExpertRenewalStatus
is the central state enum. AuditWriter and audit_events are reused.

Two new tables: expert_scope_renewals and expert_scope_renewal_submissions.
Expert has many renewals; a scope has many attempts but only one open request (nullable unique
open_scope_id). Each request has ordered immutable evidence revisions. Request history/feedback/
review and submitted evidence metadata are encrypted at rest. Document bytes use the existing
private KYC disk, not application-level encryption; existing KYC storage protection applies.

Approval appends reviewed evidence to the existing KYC credential/qualification/experience/document
tables and creates one successor verified scope. It does not mutate prior evidence, reset global
KYC status, recheck identity, or revoke other scopes. The old scope becomes revoked to represent
supersession; its original evidence and dates remain. The new request links replacementScopeId.
The shared existing evidence validator is reused without changing its rules or original endpoint.

Transactions lock Expert, scope, then request; unique open_scope_id enforces duplicate prevention
at the database level. Version checks reject repeated/competing mutations. Actual production-engine
concurrency remains a release gate; SQLite tests do not prove row-lock scheduling under load.

## Contract / states / mapping / permissions

The authoritative [API contract](../../api/SPRINT1_EXPERT_RENEWAL_CONTRACT.md) contains every
method/URL/route/account/middleware/permission, field validation, response/error, pagination/filter/
sort, state transition, idempotency rule and database mapping. No existing API shape is changed.

| State | Actor/action | Next state |
|---|---|---|
| draft | Expert uploads revisions | draft |
| draft | Expert submits evidence | submitted |
| submitted | Authorized Admin starts review | under_review |
| under_review | Admin approves verified evidence | approved |
| under_review | Admin gives rejection reason | rejected |
| under_review | Admin requests additional information | needs_information |
| needs_information | Expert uploads a NEW revision and submits | submitted |
| draft/submitted/needs_information | Expert cancels | cancelled |

Approved/rejected/cancelled are terminal. Rejected/cancelled frees the scope for a new attempt.
Never-submitted drafts and cancelled drafts are invisible to Admin. Under-review evidence is frozen.
After rejection, the original scope remains effective only until its original expiry. There is no
implicit extension while waiting. Suspended/revoked scope cannot renew. Legacy scopes lacking a
verified country or any expiry/review deadline require a separately authorized review.

| Permission | Capability | Default roles |
|---|---|---|
| expertRenewals.viewAny | Paginated submitted request metadata | kyc_reviewer, super_admin |
| expertRenewals.view | Detail and evidence metadata/history | kyc_reviewer, super_admin |
| expertRenewals.viewEvidence | Authenticated document bytes, audited | kyc_reviewer, super_admin |
| expertRenewals.review | Start / request information / reject / approve | kyc_reviewer, super_admin |

Permissions do not imply each other. Existing experts.reviewKyc alone grants no renewal access.
The existing super_admin Gate behavior is preserved, behind the Admin account-type middleware.

## Frontend handoff

Expert React: load scopes eligibility; show opensAt/dueAt, isEffective and pending request ID;
create once; upload multipart evidence; submit with current version; refresh detail/poll list.
A 409 requires refreshing state before retry. needs_information displays feedback, requires NEW
upload, then re-submit. Historical revisions stay readable. Never infer that submitted means renewed.
After approval refresh the existing expert profile and use replacementScopeId. No user_id/expert_id
is sent for ownership. Expired Experts can still use this workspace if account/KYC/email requirements hold.

Admin React: separate permission checks for queue, detail, evidence download and decisions;
start review before deciding; regulated approval records country, regulator, registration, authoritative
HTTPS source and active status. Both review date and validUntil required. UI never adds domain/country
or services through renewal. Approval errors must stay errors; do not optimistically mark a scope renewed.

Download using authenticated fetch and a short-lived browser object URL; revoke it after use.
All responses are private/no-store. Treat reason/note/evidence text as text, never render unsanitized HTML.
No React screens have been implemented or integrated in this backend delivery.

20 synthetic fixtures in fixtures/ were captured from real test HTTP responses, including every
lifecycle state, resubmission, both lists/details, upload/replacement and401/403/404/409/422.
Binary download success/ownership/integrity and429/Retry-After are separately tested; document bodies
are deliberately not embedded in JSON fixtures. Regenerate with:

```bash
SOLVEIT_RENEWAL_FIXTURE_DIR=docs/sprint1/expert-renewal/fixtures vendor/bin/phpunit tests/Feature/Expert/Renewal --display-warnings
```

## Deployment checklist

1. Apply Changes-Only at the Laravel root after reviewing CHANGED_FILES.md. It is cumulative against
   uploaded Sprint0 baseline681ca02 and includes prior Sprint1 privacy work; back up existing source/data.
2. Run composer validate --strict, Pint and full PHPUnit in CI. No dependency change is required.
3. Test both additive Sprint1 migrations on Staging with the actual production database engine;
   verify same-scope concurrent creation, evidence upload/submit and competing reviewer decisions.
4. Confirm existing KYC disk is private, not linked under public storage, not publicly served;
   configure shared private storage if running multiple hosts. Preserve APP_KEY for encrypted metadata.
5. Migrate and seed additively (do not resync custom roles through AuthorizationSeeder on upgrades):

```bash
php artisan migrate --force
php artisan db:seed --class=PrivacyPermissionsSeeder --force
php artisan db:seed --class=ExpertRenewalPermissionsSeeder --force
php artisan route:cache
php artisan route:list --name=renewals
```

On fresh environments the main AuthorizationSeeder also registers and assigns the four permissions.
No .env edits are part of this delivery. config/expert_renewal.php sets window_days=30 and max_submissions=20.
Existing privacy queue/scheduler/SLA/policy-publication deployment steps still apply.
Do not roll back populated renewal migrations in production: their down method drops renewal history.
Use reviewed forward fixes and backups. No retention executor or destructive cleanup was added.

6. Verify Expert and Admin real tokens, wrong-type403, foreign404, stale/duplicate409, invalid422,
   private download and429, plus existing KYC approval/public profile regression on Staging.
7. Connect Expert/Admin React and run accepted renewal/info/reject/expiry scenarios before declaring E2E complete.

## Explicit limitations

- Regulator verification is a manual reviewer attestation, not an automated registry lookup.
- Formal appeals, new-country/scope extensions, autonomous expiry/review scheduler and email reminders
  are not implemented. Current effectiveness checks still enforce existing date-based scope eligibility;
  clients fetch status. No whole-US20/US21 or Sprint9 completion claim.
- Active Case impact/rerouting is deferred because Case Core does not yet exist.
- No final erasure/anonymization or evidence retention purge. Metadata/history is retained; evidence
  retention/legal holds need approved policy. Crash-created orphan files need operational reconciliation;
  ordinary transaction failures remove the just-uploaded file (tested).
- Staging, React, production DB concurrent locks, multi-host storage and regulator integrations untested.
- Existing core-profile policy text publication and rights-request SLA remain owner configuration gates.

See VERIFICATION.md and verification/ for actual local results. These establish local backend completion
of the agreed renewal scope, not deployment or full Sprint1 frontend completion.
