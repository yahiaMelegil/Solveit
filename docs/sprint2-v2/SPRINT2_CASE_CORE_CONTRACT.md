# SolveIt Case Core v2 and Service Catalog, contract 2.0.0
Status: implemented backend contract 2.0.0, locally verified; deployment acceptance pending.
Owner decisions approved 2026-10-01. Exact routes: ENDPOINTS.md; schemas: API_SCHEMAS.json.

## Boundaries
Separate User, Expert, Admin PAT realms. Expert receives no Case access. No ranking, assignment,
invitation, booking or payments. Supply check proves current eligibility, not a reservation.
Self cases only. Career means non-legal career guidance. No hard deletion/anonymization.
Service type is professional work; delivery mode is the existing consultation channel.

## Compatibility
New `/api/v2/user/cases` endpoints use v2 readiness statuses. v1 reads, drafts, documents,
context snapshots and cancellation remain available with legacy status projection.
v1 submit cannot bypass catalog: cases without explicit reviewed catalog scopes cannot submit through v1. Complete v1 intake receives
409 CATALOG_UPGRADE_REQUIRED; incomplete legacy intake may first return 422. All v2 Cases
receive 409 at the legacy submit route. No implicit choice of specialty, global jurisdiction, or expert grant.
Existing ready records retain history; an explicit audit/review command flags missing policy
coverage, never fabricates snapshots. New current catalog version applies to new selections;
pinned submitted scopes are immutable. Ordinary pause stops new submissions, not existing cases.

## Responses and writes
Existing `{status,message,data:{item}}` envelope; lists `data.items,pagination`.
CamelCase; ISO8601 UTC. Errors use existing normalized 401/403/404/409/422/429 envelope.
Owner inferred from token. Cross-owner 404; wrong realm 403; guest 401.
Every write requires Idempotency-Key (16..128); existing mutations also expectedVersion.
Replay lifetime 24h; payload conflict 409 IDEMPOTENCY_CONFLICT; stale revision 409 VERSION_CONFLICT.
Authorization precedes replay, nested ownership is checked. No transactions for ordinary reads.

## Data contract
Draft input: title,problemDescription,desiredOutcome,language,urgency,subjectType=self,
privacyChoice=private,answers{immediateDanger,requiresInPerson,ambiguousHighRisk},caseCountry
(optional residence-independent catalog country),lastCompletedStep.
Scopes: catalogEntryId,catalogVersion,deliveryMode,jurisdictionCodes[],answers{},confirmed.
GLOBAL requires no jurisdictionCodes; country-specific exactly one allowed code; multi-country
requires each configured jurisdiction. Partial submission requires explicit selectedScopeIds
and partialConsent=true. Parent is not ready while any retained scope is unready.

## Catalog
Database nodes: country,jurisdiction,domain,specialty,service,delivery_mode with stable codes,
Arabic/English labels and parent references, plus admin-only safety_rule nodes. GLOBAL is a jurisdiction mode, never an ISO country.
Version policy: domain,specialty,serviceType,deliveryModes,jurisdictionMode,jurisdictionCodes,
regulated,remoteDeliveryAllowed,requiresVerifiedScope,requiresProfessionalLicense,
requiredEvidenceType,minimumExperts,maximumExperts,intakeSchema,matchingRules,
commerciallyAvailable,operationallyAvailable,requiredConsents,requiredDocuments,
effectiveFrom,effectiveUntil. Unknown/missing policies fail closed.
Only enabled and effective versions admit new ready scopes. Pilot is inspectable/testable but
never ready on live Case endpoints. Draft seeds do not authorize launch.
Schema is a bounded declarative field subset, never executable Laravel rules, regex or code.

## States
Catalog draft -> intake_only/pilot/enabled after review + fresh impact acknowledgement;
enabled/pilot/intake_only -> paused/retired/blocked. New version for policy changes.
Regulated creator, reviewer and publisher must be distinct, even super admin.
Case v2: draft,intake_in_progress,needs_clarification,unsupported_domain,
unsupported_jurisdiction,regulated_service_not_enabled,not_suitable_for_remote_service,
waiting_for_expert_supply,safety_referral_required,ready_for_matching,requires_review,cancelled.
Reasons: CASE_INCOMPLETE,UNSUPPORTED_DOMAIN,UNSUPPORTED_JURISDICTION,SERVICE_NOT_LAUNCHED,
REMOTE_DELIVERY_NOT_ALLOWED,REGULATED_SERVICE_NOT_ENABLED,NO_ELIGIBLE_EXPERT_COVERAGE,
MISSING_REQUIRED_DOCUMENTS,MISSING_REQUIRED_CONSENT,SAFETY_OR_EMERGENCY,
HUMAN_TRIAGE_REQUIRED,CATALOG_VERSION_CHANGED,CATALOG_UPGRADE_REQUIRED.
Status/risk/owner cannot be assigned by clients.

## Expert eligibility
Explicit admin grant binds existing verified scope + catalog version + exact jurisdiction,
with evidence reference and audit. Profile specialties confer no eligibility.
KYC approved, active verified account/scope, nonexpired validity/review, evidence/license,
confirmed specialty/service/delivery, language and accepting-new-work are rechecked at submit.
Renewal replacement does not silently expand/migrate grants; new explicit review is required.
Coverage for each jurisdiction >= minimumExperts; distinct expert IDs, no double counting.
MaximumExperts is a future team size limit, not a supply count cap.

## Initial endpoint matrix
GET /api/catalog/{countries|jurisdictions|domains|specialties|services|delivery-modes}
GET /api/catalog/entries
GET /api/catalog/intake-schema/{entry} (current public version)
Admin /api/admin/catalog: nodes list/create/update; entries list/create/detail;
entries/{entry}/versions create; versions/{version}/review,impact,publish,pause;
grants list/create/revoke; waiting-cases metadata.
Permissions catalog.view,manageDrafts,review,publish,pause,viewImpact;
expert scope mapping separately catalog.review + experts.reviewKyc.
User /api/v2/user/cases: list/create/show/update, scopes replace, assessment,confirm,submit,cancel.
Shared protected v1 document/context/timeline endpoints remain canonical.
Admin v2 list/detail remains cases.viewAny/cases.view, metadata only.

## Query conventions
Page/perPage default20 max100; code/status/domain/parent filters by endpoint;
sortBy id/updatedAt, sortDirection asc/desc. No unrestricted sort columns.
Public catalog hides draft policies and reviewer identities, returns labels/status/version and review/publication dates.
No private expert IDs, evidence paths or case narrative in catalog/impact output.

## Exact request schemas
`API_SCHEMAS.json` is JSON Schema 2020-12 for client forms. Use the named definition:

| Route action | Request definition | expectedVersion refers to |
|---|---|---|
|v2 Case store/update|CaseCreate / CaseUpdate|Case version on update|
|v2 scopes|CaseScopes|Case version|
|v2 assessment/cancel|CaseAction|Case version|
|v2 confirm/submit|CaseConfirm / CaseSubmit|Case version|
|Admin node/updateNode|NodeCreate / NodeUpdate|Node version|
|Admin entry store/version|EntryCreate / EntryVersion|Entry version on successor creation|
|Admin review/publish/pause|PolicyReview / PolicyPublish / PolicyPause|Policy revision, not catalogVersion|
|Admin grant/revoke|ExpertGrant / ExpertRevoke|Unique grant / irreversible revoke|

Server additionally validates FK ownership, catalog hierarchy/classification, accepted booleans, ordered dates,
maximumExperts >= minimumExperts, exact jurisdiction mode cardinality, active country/jurisdiction taxonomy,
regulated license requirements, evidence ownership, effective policy, and live supply. Clients cannot bypass these
with a schema-valid request. Unknown input fields fail validation. Country existence is not service authorization.

## Response items
Case detail: id,contractVersion,status,version,intake,scopes[],readinessCheckedAt,createdAt,updatedAt.
Each scope: id,catalogEntryId,catalogVersion,domain,specialty,serviceType,deliveryMode,jurisdictionMode,
jurisdictionCodes[],answers object,confirmed,status,reasonCodes[],policySnapshot nullable,
readinessCheckedAt,submittedAt. Empty answer maps are JSON objects. Detached scopes are absent from the list.
Case list items are compact: id,status,version,title,caseCountry,updatedAt.
Readiness: status,reasonCodes[],scopes[{scopeId,status,reasonCodes,coverage,alreadySubmitted}],suggestions[],nextAction,triageUrl.
Coverage reveals counts per jurisdiction, not Expert IDs or evidence. A readiness GET never performs submission.
Admin Case metadata: id,userId,status,version,scopeCount,caseCountry,language,readinessCheckedAt,createdAt,updatedAt.
Catalog node: id,kind,code,parentId,labels,regulated,status,version.
Catalog version: id,catalogEntryId,catalogVersion,revision,status,policy,policyHash,reviewedAt,publishedAt.
Entry detail: id,code,version,status,currentVersionId,versions[].
Impact: catalogEntryId,catalogVersion,revision,activeScopes,waitingScopes,coverage[],impactToken.
Grant metadata: id,scopeId,catalogVersionId,jurisdictionCode,reviewedBy,revokedAt.
Waiting metadata: caseId,scopeId,catalogVersionId,status,reasonCodes[].
`scopeCount` is the historical relation count; use user detail scopes[] for currently retained scopes.

## Error behavior
| HTTP | Codes / meaning |
|---|---|
|401|UNAUTHENTICATED|
|403|FORBIDDEN / realm, permission; EMAIL_NOT_VERIFIED for unverified Users; SEPARATION_OF_DUTIES_REQUIRED for regulated decisions|
|404|RESOURCE_NOT_FOUND; unknown or foreign resource concealed|
|409|VERSION_CONFLICT, IDEMPOTENCY_CONFLICT, INTAKE_ASSESSMENT_STALE, CATALOG_UPGRADE_REQUIRED, PARTIAL_CONSENT_REQUIRED, CASE_NOT_READY, INVALID_CASE_TRANSITION, DUPLICATE_CASE_SCOPE, SUBMITTED_INPUT_IMMUTABLE|
|409 catalog|IMPACT_CHANGED, INVALID_CATALOG_TRANSITION, POLICY_CONFIGURATION_REQUIRED, CATALOG_CODE_EXISTS, SCOPE_REVIEW_REQUIRED, GRANT_ALREADY_EXISTS, GRANT_ALREADY_REVOKED, CATALOG_NODE_TERMINAL, ENTRY_POLICY_CHANGE_REQUIRED|
|409 readiness submit|Stable first blocking reason, e.g. NO_ELIGIBLE_EXPERT_COVERAGE; GET readiness returns the full list|
|422|VALIDATION_FAILED with errors{field:[messages]}; malformed/unknown input or key|
|429|RATE_LIMITED; honor Retry-After|

See actual error fixtures for envelope spelling. Additional readiness reasons include SAFETY_POLICY_REQUIRED,
SELF_CASES_ONLY, CONTEXT_AUTHORIZATION_CHANGED and PROFESSIONAL_COVERAGE_REVIEW_REQUIRED.
Idempotency saves successful responses only, scoped by actor + named operation/resource + key. Transaction failure
is not cached. Same body/key replays without advancing version; changed body/key conflicts. Authorization occurs
before replay. User v2 replay preserves exact JSON body bytes; historical v1 replay storage stays compatible.

## Compatibility and change log
2.0.0 adds DB Service Catalog, exact expert grants, per-scope jurisdictions and policy snapshots, multiple scopes,
partial consent, broader lifecycle, dynamic intake, and locked readiness checks. v1 reads/drafts/context/documents/
timeline remain with their legacy representation. v1 cannot submit, create another person's case, or mutate pinned
shared input. No silent old-case mapping. Existing ready cases lacking catalog snapshots are flagged by an explicit
review command, not rewritten in the migration. Optional KYC `catalogPolicyVersionId` enforces stricter reviewed
service licensing; standalone domain classification still comes from DB. Privacy/KYC/RBAC contracts are otherwise
retained. Higher-level readiness consumers must use v2 rather than legacy primaryDomain or four-state projection.
