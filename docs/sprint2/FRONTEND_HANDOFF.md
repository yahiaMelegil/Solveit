> Historical v1 delivery record. Superseded for deployment/integration by [Case Core v2](../sprint2-v2/README.md). The fixture clock is now fixed; country/service policy comes from the database catalog.

# Sprint 2 React handoff
Contract: **1.0.0**. Source: docs/api/SPRINT2_CASE_CORE_CONTRACT.md. Staging Base URL: NOT PROVIDED.
This is an implementation handoff, not a staging or React acceptance certificate.
Known Sprint 1 ContractFixturesTest time failure is explicitly deferred by owner; do not hide it in CI.

## User integration
1. Use the existing verified User PAT. GET /api/user/case-intake/bootstrap for catalogs, field limits,
   boolean risk questions, enabled countries, operational flags and steps.
2. POST /api/user/cases with a new Idempotency-Key, optionally {}. Save item.id and item.version.
3. PATCH /api/user/cases/{id} with expectedVersion and changed fields. Use a fresh key for each logical
   save; retry a lost response with the same key AND identical body. Never send owner/status/risk fields.
4. Debounce autosave, allow one in-flight save per Case, queue later edits. On409 retain local edits,
   GET latest, present conflict resolution, then explicitly resave using its version and a new key.
5. Every mutation may advance version. Scan completion also advances it. GET resumes lastCompletedStep,
   intake, selected domains, active context snapshots and current confirmation.
6. Context selection: display exact fields, require authorizeUse, send contextId,contextVersion,
   selectedFactKeys,expectedVersion. General allowCaseReuse is not consent for this particular case.
   Editing source Context does not change captured facts. Archiving/revoking its reuse blocks submission.
7. Documents: multipart/form-data, browser-generated boundary (do not set Content-Type manually),
   expectedVersion,title,category,file, and Idempotency-Key. Poll document list/current Case while pending.
   Clean is the only readable state. failed requires operator scanner repair/retry; rejected needs replacement
   or removal. Removal is logical and revokes all version downloads, not irreversible erasure.
8. POST intake-assessments, render reasonCodes/clarifications, show suggestions as **rules**, not AI output.
   Domain suggestions never change selected domains. To accept/edit, PATCH explicit domains then reassess.
9. For a suitable current assessment POST intake-confirmation with assessmentId,confirmed:true,expectedVersion.
10. POST submit with its returned version and a key. Only backend status ready_for_matching is success.
    There are no expert recommendations, booking, matching jobs or payment side effects in this sprint.
11. Ready case is immutable except cancel. Cancelled is terminal. A replay may return an older successful
    response if later edits occurred, so refresh after uncertain retries instead of overwriting current UI.

## Admin and Expert
Admin list requires cases.viewAny; detail requires cases.view independently. Refresh using the same PAT after
permission revocation:403 is expected. Detail intentionally excludes title/narrative/answers/snapshot values/
document bytes. No Admin write buttons. Expert receives403 at all User/Admin Case routes, even with '*' abilities.

## Error UX
401 clear the affected account session;403 show the actual permission/verification boundary, no automatic logout;
404 do not distinguish foreign from absent;409 refresh and retain draft edits;422 map errors to fields;
429 honor Retry-After. X-Request-ID is for support correlation, never log case narrative or tokens.
Urgent-stop: display a clear stop and jurisdiction-approved local emergency guidance, no monitoring promise.
Unsupported: show configured scope limitations. Ambiguous high risk: configured human triage link, or explicit
stop/contact-support message. No case is secretly assigned to a reviewer. Other-person cases cannot submit yet.
Marketing/AI consent is optional for this deterministic intake. Current Terms/Privacy remain prerequisites.

## Binary reads
Authenticated fetch -> Blob URL for clean JPEG/PNG preview; revoke URL on unmount. PDF uses attachment download.
No direct storage URLs or bearer tokens in query strings. Responses are private/no-store/nosniff.

## Fixtures and acceptance
fixtures/*.json were captured by CaseContractTest from actual application HTTP responses with synthetic users,
synthetic policy text and PS enabled only in test config. IDs/timestamps are examples, not shared test accounts.
Production policies/countries/scanner/triage must be separately provisioned. Contract contains HTTP schemas;
ENDPOINTS.md is generated from route:list; DATABASE_MAPPING.md maps storage fields.
React acceptance: draft -> refresh/resume -> stale autosave -> resolution -> context selection -> document
pending/clean -> clarify -> confirm -> ready; verify Admin metadata and all Expert403 paths on the published API.
