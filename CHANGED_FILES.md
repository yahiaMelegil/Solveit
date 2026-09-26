# Changed files manifest

This manifest is relative to the Laravel project root.

## Added

| File | Purpose |
| --- | --- |
| `CHANGES.md` | Deployment order, behavior changes, test commands, and rollback notes. |
| `CHANGED_FILES.md` | Exact archive manifest. |
| `app/Enums/AdminPermission.php` | Central administrator permission names. |
| `app/Enums/AdminRole.php` | Central default administrator role names. |
| `app/Http/Controllers/Api/Admin/AdminExpertController.php` | Authorized expert browsing endpoints for administrators. |
| `app/Http/Controllers/Api/Admin/AdminManagementController.php` | Authorized administrator browsing endpoints. |
| `app/Http/Controllers/Api/Admin/AdminUserController.php` | Authorized regular-user browsing endpoints for administrators. |
| `app/Http/Controllers/Api/Admin/AuthorizationController.php` | Role, permission, assignment, and revocation API operations. |
| `app/Http/Middleware/EnsureUserEmailIsVerified.php` | Dynamic database-backed verification check for regular users. |
| `app/Http/Requests/Admin/Authorization/StoreRoleRequest.php` | Authorization and validation for creating roles. |
| `app/Http/Requests/Admin/Authorization/UpdateRoleRequest.php` | Authorization and validation for updating roles. |
| `app/Http/Resources/Admin/RoleResource.php` | Safe role/permission API serialization. |
| `app/Policies/AdminPolicy.php` | Administrator resource permissions. |
| `app/Policies/ExpertPolicy.php` | Expert resource and KYC-review permissions. |
| `app/Policies/RolePolicy.php` | Role management permissions. |
| `app/Policies/UserPolicy.php` | Regular-user resource permissions. |
| `config/permission.php` | Spatie Permission model, table, guard, and cache configuration. |
| `database/migrations/2026_09_20_000000_create_permission_tables.php` | Roles, permissions, and polymorphic assignment tables. |
| `database/seeders/AuthorizationSeeder.php` | Idempotent permission catalog and default role mapping. |
| `docs/authentication/user-email-verification.md` | Verification-only token flow and frontend response contract. |
| `docs/authorization/RBAC_GUIDE.md` | RBAC architecture, endpoints, deployment, and extension guide. |
| `tests/Feature/Admin/AuthorizationTest.php` | RBAC allow/deny and role lifecycle feature coverage. |

## Modified

| File | Purpose |
| --- | --- |
| `app/Http/Controllers/Api/Admin/AuthController.php` | Returns roles and effective permissions with admin login and profile responses. |
| `app/Http/Controllers/Api/Admin/KycController.php` | Enforces `experts.reviewKyc` with `Gate::authorize(...)` for every KYC operation. |
| `app/Http/Controllers/Api/AuthController.php` | Issues verification-only tokens and blocks normal login before email verification. |
| `app/Http/Resources/Admin/AdminResource.php` | Safely serializes loaded roles and effective permissions. |
| `app/Models/Admin.php` | Adds the Spatie `HasRoles` integration with the `admin` guard. |
| `app/Models/User.php` | Defines the `user:verify-email` Sanctum ability. |
| `app/Providers/AppServiceProvider.php` | Registers policies, the super-admin Gate, and preserves all existing rate limiters. |
| `bootstrap/app.php` | Registers verification and RBAC middleware aliases while preserving existing exception handling. |
| `composer.json` | Requires `spatie/laravel-permission:^8.0`. |
| `config/auth.php` | Adds the dedicated administrator guard/provider required by RBAC. |
| `database/seeders/AdminSeeder.php` | Assigns `super_admin` to the configured initial administrator. |
| `database/seeders/DatabaseSeeder.php` | Seeds the permission and default-role catalog. |
| `routes/api.php` | Protects user routes and adds isolated admin management/RBAC endpoints. |
| `tests/Feature/Admin/AuthenticationTest.php` | Verifies the seeded initial administrator receives `super_admin`. |
| `tests/Feature/Admin/Kyc/AdminKycTest.php` | Covers denial without KYC permission and allows the reviewer role. |
| `tests/Feature/Authentication/AuthenticationTest.php` | Covers verification-only registration/login and legacy-token bypass prevention. |
| `tests/Feature/Authentication/EmailVerificationTest.php` | Uses verification-only tokens for the resend flow. |

## Excluded intentionally

- `.env`, credentials, secrets, logs, caches, runtime output, and `vendor`.
- `composer.lock` is not included in the changes-only archive because it could
  not be regenerated without PHP and Composer. Run the Composer update command
  in `CHANGES.md` before deployment and commit the generated lock file.
