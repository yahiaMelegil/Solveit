# Sprint 1 — Core Profile, Context, Consent and Privacy

Contract: [`docs/api/SPRINT1_PROFILE_CONTEXT_PRIVACY_CONTRACT.md`](../../api/SPRINT1_PROFILE_CONTEXT_PRIVACY_CONTRACT.md), version 1.0.0.

This delivery follows the owner's approved analysis. The uploaded project already included Expert eligibility work historically named Sprint 1. Those files and ADR-001/ADR-002 retain their meaning; this directory covers the new Core Profile/Privacy scope only.

| Document | Contents |
|---|---|
| [ENDPOINTS.md](ENDPOINTS.md) | All 26 methods, paths, route names and response fixtures |
| [DATABASE_MAPPING.md](DATABASE_MAPPING.md) | Tables, API fields, relations, encryption and indexes |
| [PERMISSION_MATRIX.md](PERMISSION_MATRIX.md) | User ownership and explicit Admin grants |
| [STATE_TRANSITIONS.md](STATE_TRANSITIONS.md) | Versions, consent, export/deletion states and recovery |
| [FRONTEND_HANDOFF.md](FRONTEND_HANDOFF.md) | React integration, errors, idempotency and acceptance gates |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Migration, policies, queue, scheduler, retention and rollback |
| [AUDIT_AND_SECURITY.md](AUDIT_AND_SECURITY.md) | Event catalogue, isolation and residual limits |
| [QUEUE_INTEGRATION.md](QUEUE_INTEGRATION.md) | Separate-process database queue/cache/artifact lifecycle test |
| [VERIFICATION.md](VERIFICATION.md) | Actual local checks and untested environments |
| [EXPERT_REVIEW_BOUNDARY.md](EXPERT_REVIEW_BOUNDARY.md) | Approved renewal addition and remaining boundaries |
| [DEFERRED.md](DEFERRED.md) | Explicit scope boundaries and outstanding owner decisions |
| [fixtures/](fixtures/) | Synthetic JSON requests and actual tested application responses |

Architecture: thin controllers -> Form Requests / explicit Gates -> narrowly scoped domain services -> Resources. Multi-step changes and audit events are transactional. Enums define lifecycle transitions. Existing User/Expert/Admin authentication and existing response Resources are preserved. The contract was committed before implementation.

Single-scope Expert renewal was added on2026-09-30: see [renewal release](../expert-renewal/README.md). Historical verification counts below describe the core-privacy delivery; latest cumulative results are in the renewal release.
