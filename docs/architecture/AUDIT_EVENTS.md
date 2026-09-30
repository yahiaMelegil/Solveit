# AuditEvent baseline (design only)

Sprint 0 defines the event contract. It does not create a table or replace `expert_kyc_status_histories`, which remains the existing KYC transition history.

## Minimum fields for future implementation

| Field | Purpose |
| --- | --- |
| `event_id`, `occurred_at`, `correlation_id` | Immutable identifier, UTC time, request/job correlation |
| `actor_type`, `actor_id`, `actor_token_id` | Separate User/Expert/Admin identities; nullable for system jobs |
| `action`, `subject_type`, `subject_id` | Stable event name and affected object |
| `previous_state`, `new_state`, `reason_code` | Minimal decision trail; avoid storing complete private records |
| `outcome`, `source`, `request_id` | Success/failure, API/job/system, request trace |
| `metadata` | Allowlisted, redacted attributes only |

Never store plaintext tokens, passwords, identity document contents, payment secrets, or full Case narratives in audit metadata. Audit access requires dedicated Admin permission and is itself auditable. Define retention, redaction, encryption, immutable storage, legal hold, and deletion rules before writing a general-purpose migration.

## Initial event catalogue

`user.registered`, `user.email_verified`, `user.password_reset`, `expert.registered`, `expert.email_verified`, `expert.kyc_submitted`, `expert.kyc_review_started`, `expert.kyc_decided`, `expert.scope_changed`, `admin.invitation_issued`, `admin.invitation_accepted`, `admin.status_changed`, `admin.role_assigned`, `admin.role_revoked`, `admin.role_permissions_changed`.

The same event should be emitted once after a successful committed transition. Future financial and webhook operations require idempotency keys and provider references. Failed authorization can be aggregated with redacted request context; do not persist user-supplied secrets. `expert_kyc_status_histories` currently captures only KYC state changes and does not meet the full SRS audit requirement.

## 2026-09-29 Sprint 1 implementation

The baseline above remains the historical design. Core Profile/Context/Privacy now adds a scoped audit_events table and transactional events documented in `docs/sprint1/core-profile-context-privacy/AUDIT_AND_SECURITY.md`. It does not backfill or claim implementation of every baseline event. KYC histories remain unchanged. Application-level append-only guards, minimal allowlisted metadata and FK retention restrictions are implemented; production immutable archival and final legal retention remain deployment-policy dependencies.
