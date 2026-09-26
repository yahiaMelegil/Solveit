# Administrator RBAC authorization

## Request flow

Every protected administrator operation passes through these checks:

1. `auth:sanctum` authenticates the bearer token.
2. `admin` confirms that the token owner is an active `Admin` model.
3. `abilities:admin:access` confirms that the token belongs to the admin area.
4. A permission middleware, Policy, or Gate authorizes the operation.

Authentication, Sanctum abilities, and RBAC permissions are deliberately
separate. A regular-user or expert token cannot become an administrator merely
by presenting a similarly named token ability.

Controllers use the facade form required by this project:

```php
use Illuminate\Support\Facades\Gate;

Gate::authorize('view', $resource);
Gate::authorize(AdminPermission::ExpertsReviewKyc->value);
```

The PHP facade is `Gate`; it is not `$Gate`, and no controller uses
`$this->authorize(...)`.

## Default roles

| Role | Purpose |
| --- | --- |
| `super_admin` | Receives every permission and has a global `Gate::before` bypass. |
| `user_manager` | Reads administrators, users, and experts according to its seeded permissions. |
| `support_admin` | Reads user/expert data and owns support permissions. |
| `kyc_reviewer` | Reads experts and performs KYC review decisions. |

The authoritative mapping is
`database/seeders/AuthorizationSeeder.php`. Controllers never check role names;
they authorize permissions.

## KYC protection

Every `/api/admin/kyc/*` route additionally requires
`experts.reviewKyc` through middleware. Every KYC controller method also calls:

```php
Gate::authorize(AdminPermission::ExpertsReviewKyc->value);
```

This rejects unauthorized administrators before validation or business logic,
while retaining an explicit authorization boundary in the controller.

## Management endpoints

| Method | Endpoint | Authorization |
| --- | --- | --- |
| GET | `/api/admin/admins` | `AdminPolicy::viewAny` |
| GET | `/api/admin/admins/{admin}` | `AdminPolicy::view` |
| GET | `/api/admin/roles` | `roles.viewAny` |
| POST | `/api/admin/roles` | `RolePolicy::create` |
| GET | `/api/admin/roles/{role}` | `RolePolicy::view` |
| PUT | `/api/admin/roles/{role}` | `RolePolicy::update` |
| DELETE | `/api/admin/roles/{role}` | `RolePolicy::delete` |
| GET | `/api/admin/permissions` | `permissions.viewAny` |
| POST | `/api/admin/admins/{admin}/roles/{role}` | `AdminPolicy::assignRoles` |
| DELETE | `/api/admin/admins/{admin}/roles/{role}` | `AdminPolicy::assignRoles` |
| GET | `/api/admin/users` | `UserPolicy::viewAny` |
| GET | `/api/admin/users/{user}` | `UserPolicy::view` |
| GET | `/api/admin/experts` | `ExpertPolicy::viewAny` |
| GET | `/api/admin/experts/{expert}` | `ExpertPolicy::view` |

Role create/update payload:

```json
{
  "name": "content_manager",
  "permissions": ["users.viewAny", "users.view"]
}
```

Role names use lower snake case. Permission names must already exist for the
`admin` guard. The `super_admin` role cannot be renamed or deleted; an assigned
role cannot be deleted; and the last active super administrator cannot lose the
role.

## Installation and deployment

This merge adds `spatie/laravel-permission:^8.0` to `composer.json`. Generate an
updated lock file in an environment with PHP 8.3 and Composer before deployment:

```bash
composer update spatie/laravel-permission --with-all-dependencies
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=AuthorizationSeeder --force
php artisan db:seed --class=AdminSeeder --force
php artisan permission:cache-reset
```

Set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` before running
`AdminSeeder`. The configured initial administrator receives `super_admin`.

## Tests

```bash
php artisan test tests/Feature/Admin/AuthorizationTest.php
php artisan test tests/Feature/Admin/Kyc/AdminKycTest.php
php artisan test
```
