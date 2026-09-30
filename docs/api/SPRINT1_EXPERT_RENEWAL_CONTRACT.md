# Sprint 1 — Expert scope renewal API

Version 1.0.0. Additive contract, approved business rules 2026-09-30 (Asia/Jerusalem).
Implement this contract before Expert/Admin React integration. Existing authentication,
KYC approval and profile contracts remain compatible. Final deletion/anonymization and
Case Core are excluded.

## Rules and state machine

Renew one scope without changing domain, country, jurisdiction, role, languages or services.
Open window: earliest non-null validUntil/nextReviewAt minus configurable 30 calendar days
(server application timezone); expiry/review date is inclusive. Active or expired scope
statuses are eligible; suspended/revoked scopes are not. A scope without either date or
without a verified country needs a separately authorized legacy review, not invented data.
Expert must be active, email verified, with approved KYC. Expired scope does not block this workspace.

`draft -> submitted -> under_review -> approved | rejected | needs_information`.
`needs_information -> submitted` requires a NEW evidence submission.
`draft | submitted | needs_information -> cancelled` by owner. No cancellation under review.
One open request per scope, including needs_information. Rejected/cancelled permits a new request.
No appeal workflow or scope extension is implied. Rejection preserves any remaining original validity.
Approval atomically creates one successor active scope and marks only the original revoked
(superseded by renewal). Previous evidence, original dates and other scopes remain unchanged.
Pending requests never extend eligibility. New validity ends no later than selected evidence expiry
and next review. Regulated review follows ADR-002, manual authoritative HTTPS attestation,
active license, matching country, next review within one year. Nonregulated evidence accepts
credential, qualification or experience with a document; no mandatory license.

## Common transport/security

Base `/api`. JSON except evidence upload (`multipart/form-data`) and document download.
Expert middleware: `SprintOneApi, auth:sanctum, expert, abilities:expert:access, expert.verified`.
Admin: `SprintOneApi, auth:sanctum, admin, abilities:admin:access` plus explicit Gate permissions.
GET rate 120/minute, writes 30/minute, evidence 15/minute per account/IP.
Headers: `Authorization: Bearer ...`, `Accept: application/json`; private/no-store and X-Request-ID.
Mutations of existing requests require integer `version >= 1`; stale version =>409.
No Idempotency-Key required: one-open unique constraint prevents duplicate creation (409);
version/state checks prevent duplicate upload/submit/decisions (409). Clients refresh after ambiguous
network results before retry. Successful mutation increments version; GET is side-effect free except
an audit event for sensitive document downloads. Create returns201, upload201, other successes200.

Errors: `{status:false,code,message,errors?}`. 401 absent/invalid token;403 wrong account,
ability, inactive/unverified account or missing permission;404 foreign/missing request/scope/submission;
409 state/version/duplicate/window/evidence unavailable;422 fields, forbidden extra fields or evidence
review failure;429 rate limit with Retry-After. No paths, hashes, tokens or exception internals returned.

## Endpoints

All route names below start `expert.renewals.` or `admin.renewals.` as shown.

| Method | URL | Route name | Account / permission | Input / success data |
|---|---|---|---|---|
| GET | /expert/scope-renewals/scopes | expert.renewals.scopes | Expert owner | page/perPage; scopes + pagination, eligibility |
| POST | /expert/scopes/{scope}/renewals | expert.renewals.store | Expert owner | empty body; request |
| GET | /expert/scope-renewals | expert.renewals.index | Expert owner | list query; requests + pagination |
| GET | /expert/scope-renewals/{renewal} | expert.renewals.show | Expert owner | request + submissions |
| POST | /expert/scope-renewals/{renewal}/evidence | expert.renewals.evidence | Expert owner | version, evidence, file; request + submissions |
| POST | /expert/scope-renewals/{renewal}/submit | expert.renewals.submit | Expert owner | version; request |
| POST | /expert/scope-renewals/{renewal}/cancel | expert.renewals.cancel | Expert owner | version; request |
| GET | /expert/scope-renewals/{renewal}/submissions/{submission}/document | expert.renewals.document | Expert owner | private attachment |
| GET | /admin/scope-renewals | admin.renewals.index | Admin / expertRenewals.viewAny | list query; metadata + pagination |
| GET | /admin/scope-renewals/{renewal} | admin.renewals.show | Admin / expertRenewals.view | request + submissions |
| GET | /admin/scope-renewals/{renewal}/submissions/{submission}/document | admin.renewals.document | Admin / expertRenewals.viewEvidence | audited private attachment |
| POST | /admin/scope-renewals/{renewal}/start-review | admin.renewals.start-review | Admin / expertRenewals.review | version; request |
| POST | /admin/scope-renewals/{renewal}/request-information | admin.renewals.request-information | Admin / expertRenewals.review | version, reason; request |
| POST | /admin/scope-renewals/{renewal}/reject | admin.renewals.reject | Admin / expertRenewals.review | version, reason; request |
| POST | /admin/scope-renewals/{renewal}/approve | admin.renewals.approve | Admin / expertRenewals.review | version, evidenceReviewed=true, validUntil, nextReviewAt, professionalReview?; request |

List: page>=1, perPage1..100 default20, status enum optional, scopeId positive integer optional,
sort `newest` (default) or `oldest`, deterministic id order. No arbitrary sorting or free-text search.
Scopes list only page/perPage. Pagination `{currentPage,perPage,total,lastPage}`.
Admin list excludes never-submitted requests (including cancelled drafts), evidence, free-text feedback and identity/contact data. Never-submitted detail returns404.
Permissions are independent; review does not implicitly grant evidence download.
All four assigned to kyc_reviewer and super_admin; additive upgrade seeder preserves custom grants.

## Validation / evidence

Only listed fields accepted, including nested keys. IDs/ownership/status cannot be mass assigned.
`evidence` is multipart nested fields, not a JSON-encoded string. One PDF/JPG/JPEG/PNG,
max10240 KiB. File name generated by server, download attachment only. Uploaded evidence is immutable;
a replacement appends a submission. Max20 submissions/request. Draft or needs_information only.

Evidence discriminator `type`: credential | qualification | experience.
- credential: credentialType license|certificate, name<=255, issuer<=255 required;
  issueDate optional YYYY-MM-DD <=today; expiryDate optional YYYY-MM-DD >today and >=issueDate.
  Regulated domains require credentialType=license.
- qualification: degree,institution required<=255; field optional<=255; graduationYear optional1900..currentYear.
- experience: jobTitle,organization required<=255; description required<=2000; document is work sample.
These are alternative schemas: extra fields from another type are rejected.
Optional `note` <=2000 within evidence, private owner/reviewer only.
Reason required nonblank10..2000 for reject/request-information, encrypted and preserved in decision history.
Approval: evidenceReviewed must be accepted boolean true; validUntil and nextReviewAt YYYY-MM-DD >today;
validUntil<=nextReviewAt, must advance original earliest deadline. Regulated nextReviewAt<=today+1year.
professionalReview required only regulated: verifiedCountry exactly original scope country,
regulator<=255, registrationNumber<=100, verificationSource HTTPS URL<=2048, statusChecked=active.
Server records checkedAt/reviewer. No regulator HTTP fetch is performed.

## Responses and mapping

Envelope `{status:true,data:{request:...}}`; lists `data.requests` / `data.scopes`.
Request: id, scopeId, status, version, currentSubmissionId, replacementScopeId, createdAt, updatedAt,
submittedAt, decidedAt. Detail also public-safe `scope`, `nextReviewAt`, `feedback`, `history` and `submissions`.
Submission: id, evidence, file:{mimeType,size}, createdAt. No storage coordinates.
History: from,to,reason,at; admin identity is not included in Expert output.
Scopes: existing public-safe verified scope fields plus nextReviewAt and
renewal:{eligible,reason,opensAt,dueAt,openRequestId}. Eligibility reason codes are stable.

| API | Database |
|---|---|
| scopeId | expert_scope_renewals.scope_id -> expert_verified_scopes.id |
| owner (token only) | expert_scope_renewals.expert_id -> experts.id |
| status/version | expert_scope_renewals.status/version |
| currentSubmissionId | expert_scope_renewals.current_submission_id (service-controlled same request) |
| replacementScopeId | expert_scope_renewals.replacement_scope_id -> expert_verified_scopes.id |
| feedback/history | expert_scope_renewals encrypted text feedback/history |
| submittedAt/decidedAt | submitted_at/decided_at timestamps |
| evidence | expert_scope_renewal_submissions.evidence encrypted array |
| file | submission disk/path/checksum hidden; mime_type/size public to authorized reader |
| approval attestation | renewal review encrypted array; copied to successor scope existing review columns |
| approved evidence | appended existing KYC credentials/qualifications/experiences/documents; old rows untouched |
| audit | existing audit_events, account typed actor, state and stable reason codes, no free text |

New tables: requests and immutable submissions; no duplicate profile/identity/evidence catalogue.
Unique nullable open_scope_id enforces one open request/scope; closed requests set null.
Indexes requests(expert_id,id),(status,id),(scope_id,id); submissions unique(request_id,sequence).
Foreign keys restrict removal. Current submission pointer is checked under lock (no cyclic FK).
Locks: Expert -> scope -> request; KYC approval also updates (locks) the Expert before replacing scopes.
Renewal never acquires a conflicting application write lock; original verified application stays verified.
Evidence bytes on existing private KYC disk; rollback removes new unreferenced upload. No automatic
purge of renewal evidence: retain pending owner-approved compliance retention policy.

## Frontend fixtures and handoff

See `docs/sprint1/expert-renewal/fixtures/` for executable-test-captured examples, plus README for flow.
Expert: scopes eligibility -> create -> upload -> submit -> poll/detail; needs_information uploads
new evidence before re-submit; reject reason -> new request. Preserve version on every mutation.
Admin: queue filter -> detail/document with separate permission -> start -> approve/info/reject.
Download with authenticated fetch, create/revoke a temporary browser object URL; never public storage URLs.
React integration, real production DB locking, Staging and notifications/scheduled reminders are separate gates.

## Changelog

1.0.0: new single-scope renewal lifecycle, private evidence revisions, review permissions and successor scope.
No changes to existing endpoint request/response shapes. Superseded scope remains historical with revoked
status; clients refresh existing expert profile after approval to obtain the successor scope ID.
