# Permission matrix

All new User endpoints require Sanctum personal access authentication, concrete User account, verified email and user:access ability. Ownership comes from that token, never a submitted user_id. User Profile/Preferences/Context/Consent/DataRightsRequest Policies are explicit. Foreign/absent IDs have the same404. Admin and Expert tokens are rejected even if they carry user:access or a wildcard ability.

| Account / permission | Own profile/preferences | Own contexts/consents/requests | Admin consent metadata | Admin request list | Admin request detail |
|---|---|---|---|---|---|
| Guest |401|401|401|401|401|
| Verified User + user:access |Allowed|Allowed, own only|403|403|403|
| Expert, including verified |403|403|403|403|403|
| Admin without new grants |403|403|403|403|403|
| Admin + users.consentMetadata.view |403|403|Allowed|403|403|
| Admin + dataRequests.viewAny |403|403|403|Allowed|403|
| Admin + dataRequests.view |403|403|403|403|Allowed|
| Existing super_admin role |403|403|Allowed|Allowed|Allowed|

Admin routes require auth:sanctum, concrete Admin account and abilities:admin:access. The route checks can:permission before validation; every controller action also calls Gate::authorize(...). The existing super_admin Gate behavior is preserved; it does not bypass the account-type middleware. Existing user_manager/support/finance roles do not automatically gain these permissions. AuthorizationSeeder reads the expanded AdminPermission enum and gives all permissions only to its existing super-admin role. Explicit grants must follow the existing RBAC workflow.

No new Admin access to profile phone, context facts, export content, policy text or internal files. No Admin mutation/review/delete/renewal endpoint. viewAny does not imply view; permission revocation is effective on the next authenticated request. Metadata reads are audited. Export creation/download and deletion creation additionally require password confirmation for the same personal token and purpose, valid 5 minutes.

For an existing database, PrivacyPermissionsSeeder is the additive upgrade path: it preserves custom role and direct grants. The baseline AuthorizationSeeder retains its original syncPermissions behavior for fresh/bootstrap use.
