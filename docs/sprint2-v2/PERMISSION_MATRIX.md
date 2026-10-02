# Permission matrix
All admin actions require auth:sanctum, actual Admin model, abilities:admin:access and Gate::authorize.
Permissions are independent; role names do not authorize controllers. Additive CatalogPermissionsSeeder
registers the six catalog permissions and adds them to the existing super_admin role without replacing custom grants.

| Action | Permissions |
|---|---|
|Public enabled taxonomy/current public policy|None; rate limited|
|Admin taxonomy/entries/grants reads|catalog.view|
|Create nodes, entry or policy version; edit node labels|catalog.manageDrafts|
|Enable a node|catalog.manageDrafts + catalog.publish|
|Disable a node|catalog.manageDrafts + catalog.pause|
|Review policy|catalog.review|
|Publish enabled/pilot/intake_only|catalog.publish|
|Pause/retire/block version|catalog.pause|
|Impact preview and waiting-scope metadata|catalog.viewImpact|
|Grant/revoke precise expert mapping|catalog.review + experts.reviewKyc|
|Admin Case list|cases.viewAny|
|Admin Case detail metadata|cases.view|
|Own Case CRUD/context/documents|Verified User token + CaseRecordPolicy ownership|
|Expert Case access|Denied in all cases|

Having publish/pause without viewImpact is intentional: policy requires a fresh impact acknowledgement;
assign viewImpact separately to an operator who must preview. Regulated service creator, reviewer and publisher
must be distinct Admin IDs even with all permissions. Sensitive decisions and metadata detail views are audited.
No new admin endpoint returns user narrative or expert private evidence bytes.
KYC/renewal private evidence still uses the existing separate protected endpoints/permissions.
