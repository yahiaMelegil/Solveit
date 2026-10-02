> Historical v1 delivery record. Superseded for deployment/integration by [Case Core v2](../sprint2-v2/README.md). The fixture clock is now fixed; country/service policy comes from the database catalog.

# SolveIt Sprint 2 delivery
Implemented Case Core & deterministic Intelligent Intake, from owned draft creation through ready_for_matching.
User/Expert/Admin identities and Sprint0/1 routes remain separate. No matching, booking, consultation or payments.

Read the contract first: docs/api/SPRINT2_CASE_CORE_CONTRACT.md (1.0.0).
Then FRONTEND_HANDOFF.md, ENDPOINTS.md, API_SCHEMAS.json, STATE_TRANSITIONS.md, PERMISSION_MATRIX.md,
DATABASE_MAPPING.md, DEPLOYMENT.md, DECISIONS_AND_LIMITATIONS.md, VERIFICATION.md and fixtures/.
RELEASE_GATE_SPRINT1.md records the still-open earlier gate.

-7 additive tables; private immutable encrypted intake/context/document revisions.
-22 endpoints; two independent Admin read-only permissions.
-29 new tests/457 assertions pass; full suite255/256 with only the explicitly deferred original Privacy fixture failure.
-36 actual synthetic HTTP fixtures, typed request/response schemas and a separate React handoff ZIP.
-No .env edits, new Composer packages, deployment or new irreversible deletion.

Service readiness requires approved enabled countries, genuine policies and a working scanner for attached files.
Default country list is deliberately empty. Third-party representation and ambiguous high-risk triage cannot
silently proceed. Review documented operational/SRS limitations before release.
