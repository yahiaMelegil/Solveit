# API contract changelog

## 2026-09-30 — Expert scope renewal contract1.0.0 (Sprint1)

- Owner approved single-scope renewal with configurable30-day window, no pending validity extension,
  separate reviewer permissions, independent identity/other scopes, and reasoned rejection/new attempt.
- Added15 endpoints,2 tables, private immutable evidence revisions, version/unique/locked transitions,
  request-information/resubmission/cancellation and audited successor-scope approval.
- Reused existing ADR-002 evidence validation and KYC evidence tables. Existing endpoint shapes unchanged;
  approval appends evidence and supersedes only the selected scope. No Auth redesign/package/.env change.
- Contract: SPRINT1_EXPERT_RENEWAL_CONTRACT.md; release,20 fixtures and actual verification under
  docs/sprint1/expert-renewal. Local total227 tests/1564 assertions and108 API routes.
- Supersedes credential-renewal deferral in the earlier entry below. Final deletion/anonymization,
  Case Core, formal appeal, automatic reminders and React/Staging completion remain excluded/unverified.


## 2026-09-29 — Delivery revision 2: local queue integration evidence

- Adds a separate-process database queue/cache/private export lifecycle integration test, including actual queue worker and cleanup commands. No API contract change: version1.0.0 remains fixed.
- Full local regression now passes211 tests/1333 assertions; privacy suite55 tests/469 assertions. Staging, React and production database concurrency remain untested.

## 2026-09-29 — Core Profile, Context, Consent and Privacy 1.0.0

- Adds26 `/api/user/*` and permissioned read-only `/api/admin/*` endpoints. Canonical contract: `SPRINT1_PROFILE_CONTEXT_PRIVACY_CONTRACT.md`; fixtures/field mappings/React handoff: `docs/sprint1/core-profile-context-privacy/`.
- Adds self Profile and Preferences with expectedVersion, encrypted versioned Contexts, immutable policy-linked consent/withdrawal, password step-up and encrypted private JSON account exports.
- Adds data-request lifecycle, cancellation, due dates, duplicate protection, audit metadata and explicit deferred deletion without claiming final erasure.
- Adds `users.consentMetadata.view`, `dataRequests.viewAny`, `dataRequests.view`. No general Admin profile/context/export-content access.
- Existing Auth/KYC/RBAC/Verified Scope routes and response envelopes remain unchanged. CORS additively accepts Idempotency-Key and exposes response correlation/retry/download headers.
- New request/response envelope and strict fields apply only to new endpoints; no existing client migration or deprecation date is required. Frontend owners: User settings/privacy/context views and permissioned Admin metadata views.
- Production policy text, approved response SLA, queue/storage setup and later React/Staging acceptance remain gates. Credential renewal, Case Core and final deletion executor are deferred.

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
