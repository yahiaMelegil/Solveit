# Endpoint matrix — observed route registry

All URLs include /api. GET also registers HEAD. Writes require Idempotency-Key.

| Method | URL | Route name | Account | Permission | Middleware |
|---|---|---|---|---|---|
|GET / HEAD|/api/admin/catalog/entries|admin.catalog.entries|Admin|catalog.view|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/entries|admin.catalog.store|Admin|catalog.manageDrafts|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|GET / HEAD|/api/admin/catalog/entries/{entry}|admin.catalog.detail|Admin|catalog.view|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/entries/{entry}/versions|admin.catalog.version|Admin|catalog.manageDrafts|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|GET / HEAD|/api/admin/catalog/grants|admin.catalog.grants|Admin|catalog.view|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/grants|admin.catalog.grant|Admin|catalog.review + experts.reviewKyc|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/grants/{grant}/revoke|admin.catalog.revoke|Admin|catalog.review + experts.reviewKyc|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|GET / HEAD|/api/admin/catalog/nodes|admin.catalog.nodes|Admin|catalog.view|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/nodes|admin.catalog.node|Admin|catalog.manageDrafts|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|PATCH|/api/admin/catalog/nodes/{node}|admin.catalog.updateNode|Admin|catalog.manageDrafts|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|GET / HEAD|/api/admin/catalog/versions/{version}/impact|admin.catalog.impact|Admin|catalog.viewImpact|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/versions/{version}/pause|admin.catalog.pause|Admin|catalog.pause|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/versions/{version}/publish|admin.catalog.publish|Admin|catalog.publish|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|POST|/api/admin/catalog/versions/{version}/review|admin.catalog.review|Admin|catalog.review|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|GET / HEAD|/api/admin/catalog/waiting-cases|admin.catalog.waiting|Admin|catalog.viewImpact|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:catalog-admin|
|GET / HEAD|/api/catalog/countries|catalog.countries|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/delivery-modes|catalog.delivery-modes|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/domains|catalog.domains|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/entries|catalog.entries|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/intake-schema/{entry}|catalog.schema|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/jurisdictions|catalog.jurisdictions|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/services|catalog.services|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/catalog/specialties|catalog.specialties|Public|None|api, SprintOneApi, ThrottleRequests:catalog-read|
|GET / HEAD|/api/v2/admin/cases|v2.admin.cases.index|Admin|cases.viewAny|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:cases-read|
|GET / HEAD|/api/v2/admin/cases/{case}|v2.admin.cases.show|Admin|cases.view|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsAdmin, CheckAbilities:admin:access, ThrottleRequests:cases-read|
|GET / HEAD|/api/v2/user/cases|v2.user.cases.index|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-read|
|POST|/api/v2/user/cases|v2.user.cases.store|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-create|
|GET / HEAD|/api/v2/user/cases/{case}|v2.user.cases.show|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-read|
|PATCH|/api/v2/user/cases/{case}|v2.user.cases.update|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-autosave|
|POST|/api/v2/user/cases/{case}/assessment|v2.user.cases.assessment|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-write|
|POST|/api/v2/user/cases/{case}/cancel|v2.user.cases.cancel|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-write|
|POST|/api/v2/user/cases/{case}/confirm|v2.user.cases.confirm|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-write|
|GET / HEAD|/api/v2/user/cases/{case}/readiness|v2.user.cases.readiness|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-read|
|PUT|/api/v2/user/cases/{case}/scopes|v2.user.cases.scopes|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-write|
|POST|/api/v2/user/cases/{case}/submit|v2.user.cases.submit|User|CaseRecordPolicy + ownership|api, SprintOneApi, Authenticate:sanctum, EnsureUserIsRegularUser, EnsureUserEmailIsVerified, CheckAbilities:user:access, ThrottleRequests:cases-submit|

## Pagination / filtering
Lists use page>=1, perPage1..100 (default20); envelope data.items + data.pagination{currentPage,perPage,lastPage,total}.
Case lists: status, sortBy updatedAt/createdAt, sortDirection asc/desc; Admin additionally userId.
Nodes: kind(admin), parentId, code, status, sortBy id/updatedAt, sortDirection. Public endpoints enforce enabled.
Entries: status/domain, sortBy id/updatedAt; public reads return only current public version.
Grants and waiting scopes: page/perPage, fixed ordering by ID. Waiting includes unsubmitted retained scopes.
The shared query request validates a superset: only the filters described per endpoint affect the query.

## Mutation schemas
See API_SCHEMAS.json; all unlisted body fields rejected. Policy is validated independently by CatalogPolicy.
Create201; ordinary writes200; document upload202; all reads200 unless an error.
Case update/selection/assessment/confirmation/submit/cancel requires case expectedVersion.
Node update uses node version; creating policy successor uses entry version; review/publish/pause uses policy revision.
Grant/revoke has no editable fields or expectedVersion: unique identity/revoked-state + idempotency protect decisions.

## Shared routes
Existing /api/user/cases/{case}/context-snapshots, documents, timeline remain canonical.
See SHARED_CASE_API.md. Use v2 assessment/confirmation/submit, not legacy intake-assessment submit flow.
