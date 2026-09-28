# Administrator Management

This module adds secure sub-administrator lifecycle management on top of the
existing Sanctum and Spatie Permission implementation.

## Architecture

- Administrators continue to use the existing `Admin` model, `admins` table,
  `admin` permission guard, and `admin:access` Sanctum ability.
- A sub-administrator is created as an inactive `pending_invitation` account.
- No temporary or plain-text password is generated or emailed.
- The invitation contains a one-time random token. Only its SHA-256 hash is
  stored in `admin_invitations`.
- Accepting the invitation sets the administrator's password, records
  `invitation_accepted_at`, and activates the account.
- Deactivating an accepted administrator immediately revokes all of that
  administrator's Sanctum tokens.
- Updating the email address of an accepted administrator revokes their tokens.
- Updating the email address of a pending administrator rotates and resends the
  invitation.

The `super_admin` role cannot be assigned during sub-administrator creation.
Super administrators are created through the controlled initial-administrator
seeder or an explicitly governed role-management process.

## Environment variables

```env
ADMIN_FRONTEND_INVITATION_URL=https://frontend.example.com/admin/accept-invitation
ADMIN_INVITATION_EXPIRE_HOURS=72
```

The invitation URL is generated as:

```text
{ADMIN_FRONTEND_INVITATION_URL}?email={email}&token={one_time_token}
```

Configure a real mail transport in the deployment environment. The invitation
notification is currently sent synchronously, so no queue worker is required
for this feature.

## Permissions

| Operation | Policy / permission |
| --- | --- |
| List administrators | `admins.viewAny` |
| View an administrator | `admins.view` |
| Create a sub-administrator | `admins.create` |
| Edit, activate, deactivate, or resend invitation | `admins.update` |
| Assign or revoke roles | `admins.assignRoles` |

The existing `Gate::before` rule grants the `super_admin` role all
authorization checks. A non-super administrator cannot modify a super
administrator even when a custom role grants `admins.update`.
Only an existing super administrator can grant or revoke the `super_admin`
role; possession of `admins.assignRoles` alone cannot cross that boundary.

## API endpoints

All protected endpoints require:

```http
Authorization: Bearer {admin_access_token}
Accept: application/json
```

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/admin/admins` | Admin + `admins.create` | Create and invite a sub-admin |
| `GET` | `/api/admin/admins` | Admin + `admins.viewAny` | Paginated administrator list |
| `GET` | `/api/admin/admins/{admin}` | Admin + `admins.view` | Administrator details |
| `PUT/PATCH` | `/api/admin/admins/{admin}` | Admin + `admins.update` | Update name/email |
| `PATCH` | `/api/admin/admins/{admin}/status` | Admin + `admins.update` | Activate/deactivate an accepted admin |
| `POST` | `/api/admin/admins/{admin}/invitation` | Admin + `admins.update` | Rotate and resend a pending invitation |
| `POST` | `/api/admin/auth/invitations/accept` | Public, rate limited | Accept invitation and choose password |

### Create a sub-administrator

```json
{
  "name": "Support Manager",
  "email": "support@example.com",
  "role_id": 3
}
```

Successful response: `201 Created`.

```json
{
  "status": true,
  "message": "Sub-administrator invitation created successfully.",
  "data": {
    "admin": {
      "id": 12,
      "name": "Support Manager",
      "email": "support@example.com",
      "is_active": false,
      "status": "pending_invitation",
      "invitation_accepted_at": null,
      "last_login_at": null,
      "roles": ["support_admin"]
    }
  }
}
```

### Accept an invitation

```json
{
  "email": "support@example.com",
  "token": "64-character-token-from-the-invitation-link",
  "password": "NewSecurePassword1!",
  "password_confirmation": "NewSecurePassword1!"
}
```

Passwords must be at least 12 characters and contain upper- and lower-case
letters, a number, and a symbol. The token is single-use and expires after the
configured number of hours. Successful acceptance does not issue a Sanctum
token; the administrator signs in through `/api/admin/auth/login`.

### Update an administrator

```json
{
  "name": "Updated Support Manager",
  "email": "new-support@example.com"
}
```

At least one field is required. Role changes deliberately remain on the
existing role assignment endpoints.

### Change status

```json
{
  "is_active": false
}
```

Pending invitation accounts cannot be activated or deactivated. They must
accept the invitation first. The authenticated administrator cannot deactivate
their own account, and the last active super administrator remains protected.

## Frontend integration

The administration UI should:

1. Load roles from `GET /api/admin/roles` and submit the selected numeric
   `role_id` when creating a sub-admin.
2. Display the API `status` value directly:
   `pending_invitation`, `active`, or `inactive`.
3. Show **Resend invitation** only for `pending_invitation` accounts.
4. Show activate/deactivate controls only for accepted accounts.
5. Build a public `/admin/accept-invitation` page that reads `email` and
   `token` from the query string and posts them with the chosen password.
6. Treat a successful email change on an active admin as a forced logout for
   that target because all existing tokens are revoked.

## Database changes

The migration adds:

- `admins.invitation_accepted_at` (nullable timestamp)
- `admins.last_login_at` (nullable timestamp)
- `admin_invitations` with one invitation row per administrator, a unique token
  hash, expiry, and acceptance timestamp

Existing administrators are marked as having already accepted their invitation
during migration, preserving current logins.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Set the two environment variables and the existing mail variables before
caching configuration.

## Tests

Run the focused and full suites:

```bash
php artisan test tests/Feature/Admin/AdminManagementTest.php
php artisan test tests/Feature/Admin/AuthenticationTest.php
php artisan test tests/Feature/Admin/AuthorizationTest.php
php artisan test
```

The management tests cover secure invitation creation/acceptance, invalid and
expired invitations, authorization boundaries, edits, invitation rotation,
activation/deactivation, token revocation, and account isolation.

## Rollback

```bash
php artisan migrate:rollback --step=1
php artisan optimize:clear
```

Rollback removes the invitation table and the two newly added administrator
activity columns. Take a database backup before production rollback.
