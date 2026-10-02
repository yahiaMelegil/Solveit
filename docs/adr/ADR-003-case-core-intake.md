# ADR-003: private versioned Case intake before matching
Status: implemented on isolated Sprint 2 branch, deployment acceptance pending.

Preserve ADR-001 separate identities and ADR-002 expert-scope verification. Only verified regular Users own Cases;
Experts receive no Case visibility in Sprint 2. Admin read-only metadata permissions remain independent.

Store immutable encrypted intake snapshots, immutable rule assessments with fingerprints and explicit confirmation,
and explicit per-case selected Context snapshots. Keep routing metadata separately indexable. Record lifecycle
transitions through the existing sanitized audit ledger. Use owner then Case locks for related writes and optimistic
expectedVersion for clients. Reuse idempotency ledger for successful write replay; no state payload assignment.

Classify deterministically, expose rules provenance and safe reason codes, and fail closed for unknown eligibility,
urgent risk, unstaffed ambiguous triage, third-party representation, consent gaps and unclean active documents.
Do not dispatch matching or consultation workflows on ready_for_matching.

Store documents as encrypted private immutable revisions, scan outside DB transactions, audit result under locks,
and revalidate readiness at submit. Compensate failed uploads and clean crash orphans after a grace period.
Deletion is logical pending an approved retention policy; no account erasure/anonymization is introduced.
