# Permission matrix
| Actor | List own | Read own | Mutate own editable | Read others | Admin list | Admin detail | File read |
|---|---|---|---|---|---|---|---|
|Guest|401|401|401|401|401|401|401|
|Unverified User|403|403|403|403|403|403|403|
|Verified User + user:access|yes|yes|Policy + version/key|404|403|403|own active clean revision only|
|Expert, any ability|403|403|403|403|403|403|403|
|Admin no Case permissions|403|403|403|403|403|403|403|
|Admin cases.viewAny|403|403|403|403|metadata only|403 unless cases.view|403|
|Admin cases.view|403|403|403|403|403 unless cases.viewAny|metadata only|403|

Admin endpoints use Gate::authorize, never role-name branching. Existing project super_admin Gate::before
behavior is preserved. Test permission revocation with an ordinary explicitly permitted Admin, not super_admin.
CasePermissionsSeeder is additive; it registers exactly cases.viewAny and cases.view, grants only an existing
super_admin role, and preserves custom grants. AuthorizationSeeder sees enum additions for fresh installations,
but must not be rerun during an existing deployment upgrade.
