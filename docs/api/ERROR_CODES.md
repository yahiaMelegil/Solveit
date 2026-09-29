# API errors: existing behavior and proposed codes

The existing code is the compatibility baseline. The proposed codes below apply to new endpoints first; changing existing envelopes requires a recorded frontend migration.

| HTTP | Existing examples | Proposed stable code for new endpoints |
| --- | --- | --- |
| 401 | `Unauthenticated.`; wrong credentials | `UNAUTHENTICATED`, `INVALID_CREDENTIALS` |
| 403 | Wrong account, disabled account, missing ability/permission, unverified email | `FORBIDDEN`, `ACCOUNT_INACTIVE`, `EMAIL_NOT_VERIFIED` |
| 404 | Missing or ownership-hidden KYC document/profile | `RESOURCE_NOT_FOUND` |
| 409 | Invalid KYC transition or role conflict | `INVALID_STATE_TRANSITION`, `RESOURCE_CONFLICT` |
| 422 | FormRequest validation or upload validation | `VALIDATION_FAILED`, `UPLOAD_INVALID_TYPE`, `UPLOAD_TOO_LARGE` |
| 429 | Throttle exception | `RATE_LIMITED` |

`User` login already emits `INVALID_CREDENTIALS` and `EMAIL_NOT_VERIFIED`; other login controllers do not consistently emit a `code`. Global validation and throttling currently return `status` and `message`, with `errors` for validation, but no universal code. A failed signed verification URL currently yields `403`, not a new `404`. Do not claim that the proposed codes are emitted by existing controllers.

Recommended handling: retain `Retry-After` on `429`, avoid account enumeration in forgot-password responses, and use `404` for objects hidden by ownership. Upload constraints currently permit KYC PDF/JPG/JPEG/PNG up to 10 MiB and avatar JPG/JPEG/PNG/WebP up to 5 MiB; malware scanning is not implemented.
