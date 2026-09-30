# Endpoint documentation

All 26 new endpoints are additive. The canonical contract defines complete validation, errors, pagination and idempotency. Fixtures below contain exact request/response examples captured by the test suite. U means verified User token + user:access; A means Admin token + admin:access plus listed permission.

| Method | URL | Route name | Account / permission |
|---|---|---|---|
| GET | /api/admin/data-requests | admin.data-requests.index | A + dataRequests.viewAny |
| GET | /api/admin/data-requests/{dataRequest} | admin.data-requests.show | A + dataRequests.view |
| GET | /api/admin/users/{user}/consents | admin.users.consents.index | A + users.consentMetadata.view |
| GET | /api/user/consents | user.consents.index | U / own records |
| POST | /api/user/consents | user.consents.store | U / own records |
| GET | /api/user/consents/history | user.consents.history | U / own records |
| GET | /api/user/context-schemas | user.context-schemas.index | U / own records |
| GET | /api/user/contexts | user.contexts.index | U / own records |
| POST | /api/user/contexts | user.contexts.store | U / own records |
| GET | /api/user/contexts/{context} | user.contexts.show | U / own records |
| PATCH | /api/user/contexts/{context} | user.contexts.update | U / own records |
| DELETE | /api/user/contexts/{context} | user.contexts.destroy | U / own records |
| POST | /api/user/contexts/{context}/archive | user.contexts.archive | U / own records |
| POST | /api/user/contexts/{context}/restore | user.contexts.restore | U / own records |
| GET | /api/user/contexts/{context}/versions | user.contexts.versions.index | U / own records |
| GET | /api/user/data-requests | user.data-requests.index | U / own records |
| POST | /api/user/data-requests | user.data-requests.store | U / own records |
| GET | /api/user/data-requests/{dataRequest} | user.data-requests.show | U / own records |
| POST | /api/user/data-requests/{dataRequest}/cancel | user.data-requests.cancel | U / own records |
| GET | /api/user/data-requests/{dataRequest}/download | user.data-requests.download | U / own records |
| GET | /api/user/policies | user.policies.index | U / own records |
| GET | /api/user/preferences | user.preferences.show | U / own records |
| PATCH | /api/user/preferences | user.preferences.update | U / own records |
| GET | /api/user/profile | user.profile.show | U / own records |
| PATCH | /api/user/profile | user.profile.update | U / own records |
| POST | /api/user/security/confirm-password | user.security.confirm-password | U / own records |

## Request/response fixture inventory

| Fixture | Method / URL | HTTP |
|---|---|---|
| [admin-consent-metadata.json](fixtures/admin-consent-metadata.json) | GET /api/admin/users/1/consents | 200 |
| [admin-request-detail.json](fixtures/admin-request-detail.json) | GET /api/admin/data-requests/1 | 200 |
| [admin-request-list.json](fixtures/admin-request-list.json) | GET /api/admin/data-requests?userId=1 | 200 |
| [consent-declined.json](fixtures/consent-declined.json) | POST /api/user/consents | 201 |
| [consent-granted.json](fixtures/consent-granted.json) | POST /api/user/consents | 201 |
| [consent-history.json](fixtures/consent-history.json) | GET /api/user/consents/history?purpose=marketing&sortBy=decidedAt | 200 |
| [consent-regranted.json](fixtures/consent-regranted.json) | POST /api/user/consents | 201 |
| [consent-withdrawn.json](fixtures/consent-withdrawn.json) | POST /api/user/consents | 201 |
| [consents-effective.json](fixtures/consents-effective.json) | GET /api/user/consents | 200 |
| [consents-initial.json](fixtures/consents-initial.json) | GET /api/user/consents | 200 |
| [consents-reconsent-required.json](fixtures/consents-reconsent-required.json) | GET /api/user/consents | 200 |
| [context-archived.json](fixtures/context-archived.json) | POST /api/user/contexts/1/archive | 200 |
| [context-clarified.json](fixtures/context-clarified.json) | PATCH /api/user/contexts/1 | 200 |
| [context-conflict.json](fixtures/context-conflict.json) | PATCH /api/user/contexts/1 | 200 |
| [context-created.json](fixtures/context-created.json) | POST /api/user/contexts | 201 |
| [context-deleted.json](fixtures/context-deleted.json) | DELETE /api/user/contexts/1 | 200 |
| [context-detail.json](fixtures/context-detail.json) | GET /api/user/contexts/1 | 200 |
| [context-list.json](fixtures/context-list.json) | GET /api/user/contexts?domain=technology&perPage=10&sortBy=updatedAt&sortDirection=desc | 200 |
| [context-restored.json](fixtures/context-restored.json) | POST /api/user/contexts/1/restore | 200 |
| [context-schemas.json](fixtures/context-schemas.json) | GET /api/user/context-schemas | 200 |
| [context-versions.json](fixtures/context-versions.json) | GET /api/user/contexts/1/versions?perPage=10&sortDirection=asc | 200 |
| [data-request-list.json](fixtures/data-request-list.json) | GET /api/user/data-requests?perPage=10&sortBy=dueAt | 200 |
| [deletion-cancelled.json](fixtures/deletion-cancelled.json) | POST /api/user/data-requests/2/cancel | 200 |
| [deletion-deferred.json](fixtures/deletion-deferred.json) | GET /api/user/data-requests/2 | 200 |
| [deletion-requested.json](fixtures/deletion-requested.json) | POST /api/user/data-requests | 202 |
| [error-401.json](fixtures/error-401.json) | GET /api/user/profile | 401 |
| [error-403-reauthentication.json](fixtures/error-403-reauthentication.json) | GET /api/user/data-requests/1/download | 403 |
| [error-403.json](fixtures/error-403.json) | GET /api/user/profile | 403 |
| [error-404.json](fixtures/error-404.json) | GET /api/user/contexts/999999 | 404 |
| [error-409-export-expired.json](fixtures/error-409-export-expired.json) | GET /api/user/data-requests/1/download | 409 |
| [error-409.json](fixtures/error-409.json) | PATCH /api/user/profile | 409 |
| [error-422.json](fixtures/error-422.json) | PATCH /api/user/profile | 422 |
| [error-429.json](fixtures/error-429.json) | POST /api/user/security/confirm-password | 429 |
| [export-completed.json](fixtures/export-completed.json) | GET /api/user/data-requests/1 | 200 |
| [export-download.json](fixtures/export-download.json) | GET /api/user/data-requests/1/download | 200 |
| [export-expired.json](fixtures/export-expired.json) | GET /api/user/data-requests/1 | 200 |
| [export-failed.json](fixtures/export-failed.json) | GET /api/user/data-requests/1 | 200 |
| [export-processing.json](fixtures/export-processing.json) | GET /api/user/data-requests/1 | 200 |
| [export-requested.json](fixtures/export-requested.json) | POST /api/user/data-requests | 202 |
| [password-confirmed.json](fixtures/password-confirmed.json) | POST /api/user/security/confirm-password | 200 |
| [policies.json](fixtures/policies.json) | GET /api/user/policies | 200 |
| [preferences-initial.json](fixtures/preferences-initial.json) | GET /api/user/preferences | 200 |
| [preferences-updated.json](fixtures/preferences-updated.json) | PATCH /api/user/preferences | 200 |
| [profile-initial.json](fixtures/profile-initial.json) | GET /api/user/profile | 200 |
| [profile-updated.json](fixtures/profile-updated.json) | PATCH /api/user/profile | 200 |
| [request-rejected-reserved.json](fixtures/request-rejected-reserved.json) | GET /api/user/data-requests/3 | 200 |

All protected endpoints may return 401/403/429. Resource endpoints may return 404. Mutations may return 422 or409 as specified by the canonical contract; password confirmation invalid password is422, while absent/expired proof is403. New500 responses are generic INTERNAL_ERROR. Lists alone support the documented filter/sort/pagination query fields. GET profile/preferences/policies/catalog/summary/detail endpoints have no pagination.

POST/DELETE idempotent actions are context create/archive/restore/delete, consent decisions, data-request create/cancel. Password confirmation is never idempotency-cached; PATCH uses expectedVersion. Headers: Authorization, Accept:application/json, Content-Type:application/json for JSON bodies, Idempotency-Key where required.
