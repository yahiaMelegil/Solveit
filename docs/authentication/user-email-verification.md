# Regular-user email verification

## Security model

Registration creates the account and sends the verification notification, but
it does not grant normal application access. The token returned by registration
has only the `user:verify-email` ability.

Normal access requires all of the following:

1. a valid Sanctum bearer token;
2. a token whose owner is an `App\Models\User`;
3. a non-null `email_verified_at` value;
4. the `user:access` token ability.

The `user.verified` middleware checks the database on every protected request.
Consequently, an old `user:access` token cannot bypass verification if the
account is still unverified.

## Client flow

1. Call `POST /api/register`.
2. Store the returned verification-only token temporarily.
3. Display the verification screen and use that token only for:
   - `POST /api/email/verification-notification`;
   - `POST /api/logout`;
   - `POST /api/logout-all`.
4. The user opens the signed verification link received by email.
5. After verification succeeds, discard the verification-only token and call
   `POST /api/login` again.
6. The successful login response contains a full `user:access` token and
   `token_scope: full_access`.

## Responses the frontend must distinguish

Invalid credentials return HTTP `401`:

```json
{
  "status": false,
  "code": "INVALID_CREDENTIALS",
  "message": "The provided credentials are incorrect."
}
```

Correct credentials for an unverified account return HTTP `403` and a
verification-only token:

```json
{
  "status": false,
  "code": "EMAIL_NOT_VERIFIED",
  "message": "Your email address has not been verified.",
  "email_verified": false,
  "data": {
    "user": {},
    "token": "verification-only-token",
    "token_type": "Bearer",
    "token_scope": "email_verification"
  }
}
```

A verified login returns HTTP `200` with:

```json
{
  "status": true,
  "message": "Authentication completed successfully.",
  "data": {
    "user": {},
    "token": "full-access-token",
    "token_type": "Bearer",
    "token_scope": "full_access",
    "email_verified": true
  }
}
```

The frontend must branch on `code`, not on translated human-readable text.

## Route protection

`GET /api/user` uses these middleware in order:

```text
auth:sanctum
regular-user
user.verified
abilities:user:access
```

Verification resend and logout remain available to a verification-only token.
Future regular-user business routes must use the same verified/full-access
middleware chain as `GET /api/user`.

## Tests

```bash
php artisan test tests/Feature/Authentication/AuthenticationTest.php
php artisan test tests/Feature/Authentication/EmailVerificationTest.php
```
