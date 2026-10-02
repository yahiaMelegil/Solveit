> Current Case submission uses [Case Core v2 contract 2.0.0](../sprint2-v2/SPRINT2_CASE_CORE_CONTRACT.md). This v1 contract remains a historical/shared endpoint reference; v1 submit cannot bypass the catalog gate.

# Sprint 2 Case Core & Intelligent Intake

Contract version: 1.0.0. Status: implementation contract, staging acceptance pending.
Baseline: backend-30-9-2026, Sprint 1 Renewal R3, frontend handoff 1.1.0.
User authorization: implement Sprint 2 now; defer the existing time-sensitive Privacy ContractFixturesTest fix.

## Boundaries
Separate User/Expert/Admin identities remain unchanged. No matching, invitations, booking, payments,
consultations, Expert case access, final account erasure or anonymization. Deterministic rules only.
No deployment is authorized by local test success: Sprint 1 Staging gate remains open.

## Transport and security
Prefix /api. Bearer Sanctum PAT. User routes require auth:sanctum, regular-user, user.verified,
abilities:user:access, Case policy. Admin routes require auth:sanctum, admin,
abilities:admin:access, Gate::authorize and independent cases.viewAny/cases.view.
Admin receives metadata only, not narrative, context values, document titles/bytes or intake answers.
Foreign resources return indistinguishable 404. Experts have no access, including identical numeric IDs.
All responses private/no-store, UTC ISO8601, X-Request-ID. Existing SprintOneApi envelope reused.

Success {status:true,message,data:{item:...}}; collections {status:true,message,data:{items:[],pagination:{currentPage,perPage,lastPage,total}}}.
Create/snapshot/document/version:201; reads/mutations:200. File reads: binary, protected, no internal paths.
Errors {status:false,code,message,errors?}:401 UNAUTHENTICATED;403 FORBIDDEN;404 RESOURCE_NOT_FOUND;
409 VERSION_CONFLICT/IDEMPOTENCY_CONFLICT/INVALID_CASE_TRANSITION/INTAKE_ASSESSMENT_STALE/CASE_NOT_READY/DOCUMENT_NOT_READY/POLICY_CONFIGURATION_REQUIRED;
422 VALIDATION_FAILED;429 RATE_LIMITED with Retry-After. Refresh GET after 409; retain unsaved UI input.

## Endpoints
Route prefix user.cases. unless specified. All writes require Idempotency-Key (16..128 ASCII letters/digits/._:-).
Every existing Case mutation requires expectedVersion integer >=1. Create does not accept expectedVersion.
| Method | Relative URL | Route name | Body |
|---|---|---|---|
|GET|/user/case-intake/bootstrap|user.case-intake.bootstrap|none|
|GET|/user/cases|user.cases.index|list query|
|POST|/user/cases|user.cases.store|partial intake|
|GET|/user/cases/{case}|user.cases.show|none|
|PATCH|/user/cases/{case}|user.cases.update|expectedVersion + partial intake|
|POST|/user/cases/{case}/cancel|user.cases.cancel|expectedVersion|
|POST|/user/cases/{case}/context-snapshots|user.cases.context-snapshots.store|expectedVersion,contextId,contextVersion,selectedFactKeys,authorizeUse:true|
|DELETE|/user/cases/{case}/context-snapshots/{snapshot}|user.cases.context-snapshots.destroy|expectedVersion|
|GET|/user/cases/{case}/documents|user.cases.documents.index|pagination|
|POST|/user/cases/{case}/documents|user.cases.documents.store|multipart expectedVersion,title,category,file|
|GET|/user/cases/{case}/documents/{document}/versions|user.cases.documents.versions.index|pagination|
|POST|/user/cases/{case}/documents/{document}/versions|user.cases.documents.versions.store|multipart expectedVersion,title,category,file|
|GET|/user/cases/{case}/documents/{document}/versions/{version}/download|user.cases.documents.versions.download|none|
|GET|/user/cases/{case}/documents/{document}/versions/{version}/preview|user.cases.documents.versions.preview|none|
|DELETE|/user/cases/{case}/documents/{document}|user.cases.documents.destroy|expectedVersion|
|POST|/user/cases/{case}/intake-assessments|user.cases.intake-assessments.store|expectedVersion|
|GET|/user/cases/{case}/clarifications|user.cases.clarifications.index|none|
|POST|/user/cases/{case}/intake-confirmation|user.cases.intake-confirmation.store|expectedVersion,assessmentId,confirmed:true|
|POST|/user/cases/{case}/submit|user.cases.submit|expectedVersion|
|GET|/user/cases/{case}/timeline|user.cases.timeline.index|pagination|
|GET|/admin/cases|admin.cases.index|list query; cases.viewAny|
|GET|/admin/cases/{case}|admin.cases.show|cases.view|

## Intake fields and validation
schemaVersion:1 (optional on create, fixed); title:string1..160; problemDescription:string1..10000;
desiredOutcome:string1..4000; primaryDomain:catalog key; domains:distinct catalog keys,1..5;
serviceNeeds:distinct existing ExpertServiceType values,1..5; jurisdiction:ISO country catalog;
language:ar/en; urgency:normal/soon/urgent; privacyChoice:private only;
subjectType:self/other (other cannot submit until representation workflow approved);
answers:strict boolean keys immediateDanger,requiresInPerson,ambiguousHighRisk;
lastCompletedStep:description/context/details/documents/review.
Draft accepts incomplete fields; PATCH merges scalar fields and supplied answer keys, replaces supplied arrays.
Unknown fields and all owner/status/risk/assessment/internal fields are rejected.
Submit requires title,description,outcome,primaryDomain,domains,serviceNeeds,jurisdiction,language,urgency,
all three risk answers, private visibility, self subject, primaryDomain in domains, supported service matrix,
latest assessment and confirmation, current Terms and Privacy grants, usable selected context snapshots,
all active document current versions scanned clean. Marketing and AI consent are never prerequisites.

## Catalog and operational gates
Bootstrap exposes schemas, catalogs, required fields, enabled jurisdictions/domains, rulesVersion and limits.
Known domains reuse existing context catalog. No country is enabled for service by default:
CASE_INTAKE_COUNTRIES must contain the product-approved country codes. Tests explicitly enable synthetic PS.
Only configured nonregulated domains/services are supported; no new professional eligibility is inferred.
Urgent-stop, unsupported, or ambiguous-high-risk results block submit and return safe reason codes/nextAction.
Human triage URL is optional approved HTTPS configuration. Without it the action is stop_contact_support,
not a claim that a human reviewer has been assigned. Third-party representation remains blocked.
No retention purge is enabled. Context detach and document deletion are logical removals, not erasure.

## Versions, idempotency and states
Case states: draft,needs_information,ready_for_matching,cancelled. No client-written status.
Create=>draft; assessment=>needs_information when blocked, otherwise keeps editable state;
submit=>ready_for_matching; any noncancelled state=>cancelled. Ready/cancelled cannot be edited.
Cancellation does not delete data. Assessment records are immutable and linked to input version/fingerprint.
Every mutation increments Case version and invalidates confirmation as appropriate. File scan completion
also increments version. UI must refresh after scanning. Relevant context/policy/config changes are rechecked.
User confirms domains by saving them then confirming the returned assessment. Rules suggestions do not edit inputs.
All writes use existing encrypted 24h idempotency ledger, owner then Case lock ordering, transaction-bound audit.
Replay checks authorization first, returns original successful result before stale version rejection, and sets
Idempotency-Replayed:true. Same key/different canonical payload conflicts; failed transactions do not consume key.
Upload fingerprints include server SHA256 of bytes. File staging occurs only inside an unreplayed callback;
rollback removes staged file; crash leftovers handled by case-documents:cleanup with a grace period.
Reads have no DB transactions. No notifications or AI calls are implied by submit.

## Documents
PDF/JPEG/PNG, matching MIME and extension, configurable maximum10240KiB,20 active documents,
20 revisions/document. Server-generated names, encrypted contents and encrypted title, SHA256 integrity.
Authenticated owner reads only after clean scan; unavailable scanner produces failed, never clean.
ClamAV adapter uses configured executable with argument array and timeout, no shell and no package dependency.
Preview supports safe raster images only; PDF uses attachment download (preview returns409).
Replacement preserves revisions. Logical deletion revokes all download access. No public URL.
Scanning uses private temporary plaintext deleted in finally; provision encrypted scratch/storage.

## Lists and timeline
page>=1,perPage1..100(default20),sortBy:updatedAt/createdAt/submittedAt(defaultupdatedAt),sortDirection:asc/desc(defaultdesc).
Case filters:status,domain,jurisdiction,language,urgency; Admin additionally userId.
Timeline/documents/version lists use stable ID ordering and pagination. Timeline excludes actor/token IDs and internal metadata.
Potential related Cases are own-only exact normalized title matches, capped5; no semantic-search claim or auto merge.

## Compatibility
Sprint0/1 routes and contracts unchanged. Export v1 retains its existing sections; its exclusion reason is corrected
from case_data_not_implemented to case_data_outside_export_v1. Case export integration is deferred explicitly,
not represented as implemented. Consumers must treat exclusion identifiers as extensible informational codes.
Fixtures in docs/sprint2/fixtures are generated from actual HTTP contract tests using synthetic data.
