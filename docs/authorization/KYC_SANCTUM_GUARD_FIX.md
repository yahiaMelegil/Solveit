# Admin KYC Sanctum guard fix

## Symptom

An administrator could log in and call `/api/admin/auth/me`, but requests to
`/api/admin/kyc/applications` returned:

```json
{
  "message": "User is not logged in."
}
```

The request already contained a valid `Authorization: Bearer ...` header.

## Root cause

The KYC route used:

```php
'permission:experts.reviewKyc,admin'
```

The second middleware argument forced Spatie Permission to resolve the
session-based `admin` guard. The API, however, authenticates administrators
through Sanctum personal access tokens. Sanctum had authenticated the request,
but the separate `admin` session guard had no logged-in user, so Spatie emitted
`User is not logged in.` before the controller executed.

## Resolution

The route now uses Laravel's authorization middleware:

```php
'can:experts.reviewKyc'
```

The effective KYC pipeline is:

1. `auth:sanctum` validates the Bearer Token.
2. `admin` verifies that the token owner is an active `Admin` model.
3. `abilities:admin:access` verifies the Sanctum token scope.
4. `can:experts.reviewKyc` authorizes the already authenticated administrator.
5. The controller repeats the critical boundary with
   `Gate::authorize(AdminPermission::ExpertsReviewKyc->value)`.

This preserves defense in depth without switching to a session guard.

## Frontend verification

The inspected frontend already uses:

```js
apiClient.get('/api/admin/kyc/applications', { authType: 'admin' })
```

and stores admin, expert, and user tokens under separate storage keys. No
frontend source change is required for this error.

After deployment, sign out of the admin dashboard, clear the old admin token,
sign in again, and verify these requests in order:

```text
GET /api/admin/auth/me
GET /api/admin/kyc/applications
```

Both requests must send the same newly issued administrator Bearer Token.
