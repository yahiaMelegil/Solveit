# Expert review boundary — updated 2026-09-30

The owner explicitly brought single-scope renewal into Sprint1 and approved the five business rules.
The earlier design-only/deferred renewal decision is superseded by the additive
[Expert renewal contract](../../api/SPRINT1_EXPERT_RENEWAL_CONTRACT.md) and
[implementation/handoff](../expert-renewal/README.md).

Renewal now includes a30-day window, immutable evidence revisions, submit/review/additional-information/
rejection/approval/cancellation, independent Admin permissions and audited decisions. Approval creates
one successor scope without replacing unrelated scopes or redoing identity verification. Existing
ADR-002 evidence rules are reused. Pending renewal never extends the existing scope expiry.

Formal appeal, new jurisdiction, scheduled regulator lookup/reminders and active Case handling remain
separate work. Renewal backend is locally tested; React/Staging are not claimed complete.
