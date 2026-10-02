# React integration handoff — 2.0.0
## Integration identity
Base URL and real test accounts: deployment team must supply them; none were provided or invented.
Use the existing three independent authentication realms. Case editing requires an email-verified User PAT
with `user:access`. Expert has no Case access, including tokens with `*` abilities. Admin is metadata only.
All request IDs, names, and examples in fixtures are synthetic. Fixtures are actual Laravel HTTP responses,
not a promise that the seeded example service is commercially launched. Never use fixture bearer placeholders.

## User flow
1. Read paginated `/api/catalog/domains`, `/specialties?parentId=...`, `/services?parentId=...`,
   `/countries`, `/jurisdictions?parentId=...`, `/delivery-modes`, and `/entries?domain=...`.
   Render `labels.ar` / `labels.en`; service work and delivery channel are separate selections.
2. POST `/api/v2/user/cases` to create a self-owned draft. Fields may be filled progressively.
   PATCH the same resource with `expectedVersion`. Read it again to resume; server is authoritative.
3. GET `/api/catalog/intake-schema/{catalogEntryId}`. Store the returned `catalogVersion`, not a guessed ID.
   Render the bounded `policy.intakeSchema` fields. `id` is the internal policy-version record ID;
   `catalogEntryId` + `catalogVersion` identify a user's version selection.
4. PUT `/{case}/scopes` with all unsubmitted scopes to retain. Previously submitted scopes remain pinned;
   omitted unsubmitted scopes are detached. Each scope has its own answers and jurisdiction set.
   GLOBAL uses `[]`; COUNTRY_SPECIFIC one exact configured jurisdiction; MULTI_COUNTRY the full configured set.
5. Attach explicitly selected existing context facts using shared v1 snapshot endpoints. Upload required
   documents using shared protected v1 document endpoints. Wait for clean scans; failed/pending/rejected
   revisions block readiness. These operations return the shared legacy Case representation: refetch v2 detail.
6. GET `/{case}/readiness` for current eligibility, reason codes, coverage counts, and suggestions.
   POST `/{case}/assessment` persists the assessed state and advances the version. Suggestions require explicit
   user confirmation; a suggested service is never selected or submitted automatically.
7. POST `/{case}/confirm` with `confirmed:true`, then POST `/{case}/submit`, each with latest `expectedVersion`.
   A suitable readiness assessment does not itself submit the case. Only submit pins policy/data references.
8. For a subset, include `selectedScopeIds` and `partialConsent:true`. Explain which parts remain unsupported
   or waiting. Do not present the parent as fully ready unless its persisted status is `ready_for_matching`.
9. Use shared timeline GET for events. Cancel via v2; cancellation is terminal and retains audit history.

Each write gets a new Idempotency-Key (16..128 safe ASCII characters). Retry an interrupted identical operation
using the SAME key/body; a changed operation uses a NEW key. Persist keys only for retry management, not a
catalog cache or ownership source. Never silently retry a VERSION_CONFLICT with updated version and old edits.
On 409 refresh and ask the user to resolve the affected input; on INTAKE_ASSESSMENT_STALE reassess/reconfirm.
Honor Retry-After on 429. Cross-owner resources produce 404, wrong account/permission 403, invalid token 401.
No userId/owner/status/risk/internal fields in User payloads. `caseCountry` is not residence or service jurisdiction.

## Status rendering
Use server statuses unchanged. Readiness GET is an evaluation, while detail `status` is persisted lifecycle.
Show `reasonCodes` with translated copy; do not branch on English message text. `confidence:null` is intentional:
a deterministic keyword indicator is not a calibrated AI probability. Follow `nextAction` and `triageUrl`;
when triage is unavailable, show a stop/support screen without promising staffed professional review.
Catalog pilot/intake_only/paused are not eligible for live submission. Draft/retired/blocked policies are not public.
An enabled taxonomy node does not mean every service under it is launched.

## Shared endpoints and downloads
Shared v1 context/document/timeline schema is in `SHARED_CASE_API.md` and `SHARED_API_SCHEMAS.json`.
Authenticated Blob downloads/preview only; no public storage URL or internal path. Do not render document
content in a public iframe. Preserve Content-Disposition filename handling. Pinned document revisions cannot
be replaced/deleted after submission. Draft replacements remain versioned and scan gated.

## Admin console
Service Catalog Management uses six permissions; see PERMISSION_MATRIX.md. Read entry detail to obtain entry
version, policy `revision` for review/publish, and node version for node updates. Review -> impact -> publish;
send the current impactToken. If impact changed, refresh it. Regulated create/review/publish needs three
independent admins even when one is super_admin. Grant mapping needs catalog.review AND experts.reviewKyc.
Admin Cases endpoint never returns full case narrative, intake answers, contexts, or document contents.

## Acceptance checklist
Exercise draft/resume, autosave conflict, duplicate retry, consent/context changes, document clean/rejected/failed,
GLOBAL residence independence, unsupported/pilot/regulated policy, no supply, per-jurisdiction coverage,
partial submit, ready and cancel. Verify no Expert or foreign User can access the record. Use the same Admin
PAT to test permission revocation. React E2E and real staging accounts remain pending deployment.

Protected context/document/timeline examples generated from the current shared endpoint tests are in `shared-fixtures/`. Their contractVersion remains1.0.0 because those URLs reuse the shared contract. No old Case-create domain lists are included in the shared schema.
