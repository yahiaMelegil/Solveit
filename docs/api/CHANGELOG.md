# API contract changelog

## 2026-09-28 — Sprint 1 Expert eligibility (source implemented, runtime pending)

- `POST /api/admin/kyc/applications/{application}/approve` now requires at least one explicit evidence-backed scope. An empty body or an unverified regulated licence returns the existing Laravel validation response with HTTP 422. This **requires an Admin frontend update before backend deployment**. The URL, ability, permission, success envelope, and account separation do not change.
- For regulated domains the Admin must record an active licence check for the selected scope's `jurisdictionCountry` (which can differ from the Expert's residence), including regulator, registration number, HTTPS source, and next review date. This is an Admin attestation of a human check, not an automated authority lookup.
- Verified scope responses add public-safe `jurisdictionCountry` (nullable for legacy scopes); the review metadata remains Admin only.
- Admin KYC detail/approval responses add `data.application.professionalReviews` with private verification metadata. Expert and public scope resources do not expose these fields.
- Unknown domain policy rejects approval with 422 until a domain classification is configured. Historic scopes remain in place, with no fabricated review metadata. See `SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md` for payloads, errors, and rollout.

## 2026-09-28 — Sprint 0 contract baseline

- The current `/api/*` routes and responses were documented without changing their URL or account models. Versioning is reserved for a later coordinated breaking change.
- `ProfessionalProfileResource` corrects the newly created unpublished Expert profile's `isPublished` from `null` to boolean `false` in workspace responses. A React consumer that distinguishes null from false should update its handling.
- Regression tests for KYC, dynamic Admin permissions, CORS and Expert profile passed in the owner's direct PHPUnit run: 147 tests, 731 assertions. The Sprint 0 report records the exact runtime evidence.

For future changes, record the affected frontend owners, old and new requests/responses, compatibility window, fixtures, deprecation date and actual test evidence. Do not silently change existing response shapes.
