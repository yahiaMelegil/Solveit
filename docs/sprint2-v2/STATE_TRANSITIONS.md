# State transitions — 2.0.0
## Case and scope
Client cannot directly write status. New Case starts `draft`; editing/selecting produces `intake_in_progress`.
Assessment stores a blocking state below; successful assessment stays intake_in_progress until explicit submit.
Confirm binds current input, context/document prerequisites, current consent and catalog revision by hash.
Submit checks version, confirmation, current policy and locked expert coverage again and pins accepted scopes.

| State | Principal reason | Next step |
|---|---|---|
|draft|No selection/input yet|Fill intake|
|intake_in_progress|Editable or assessed suitable but unsubmitted|Complete and confirm|
|needs_clarification|CASE_INCOMPLETE, missing documents/consent, HUMAN_TRIAGE_REQUIRED, policy/context changed|Resolve input or use approved support route|
|unsupported_domain|UNSUPPORTED_DOMAIN or SERVICE_NOT_LAUNCHED|Choose an enabled catalog service|
|unsupported_jurisdiction|UNSUPPORTED_JURISDICTION|Choose supported exact jurisdiction|
|regulated_service_not_enabled|REGULATED_SERVICE_NOT_ENABLED|Wait for approved launch|
|not_suitable_for_remote_service|REMOTE_DELIVERY_NOT_ALLOWED|Stop remote intake / referral|
|waiting_for_expert_supply|NO_ELIGIBLE_EXPERT_COVERAGE|Wait for explicit approved supply|
|safety_referral_required|SAFETY_OR_EMERGENCY|Stop; show safe referral action|
|ready_for_matching|All retained scopes submitted and ready|No matching performed here|
|requires_review|PROFESSIONAL_COVERAGE_REVIEW_REQUIRED or legacy missing catalog coverage|Operator review; no automatic clearing|
|cancelled|Owner cancellation|Terminal|

An editable blocking state may return to intake_in_progress after correction. A readiness GET is read-only and
may report suitable without advancing persisted lifecycle. Scope status ready is stored only on submit.
Partial submit freezes shared input and selected scope snapshots; other scopes can be replaced/resolved.
The parent remains unready until every retained scope is ready. Scope IDs cannot be reassigned across cases.
Cancellation is allowed from active states including ready and review, preserving snapshots/history.

`catalog:review-cases` flags existing submitted work after blocked service / lost professional coverage.
Scheduled every five minutes; revoke/block decisions invoke it immediately. This is not expert assignment,
not an automated remediation decision, and does not automatically clear requires_review.
Ordinary pause/new policy version does not silently rewrite or cancel already submitted cases.

## Catalog versions
New version: draft revision1; review increments revision but leaves status draft.
Reviewed draft -> intake_only / pilot / enabled by publish + fresh impactToken.
Reviewed pilot/intake_only/paused may be published to the same supported publication targets.
Published nonterminal -> paused / retired / blocked with reasonCode + fresh impactToken.
Blocked/retired are terminal for that version; new policy requires a NEW version and review.
Publishing sets the entry's current-version pointer. New selections use current policy; stale unsubmitted
selection cannot submit. Existing submitted references keep their immutable policy version.

Node creation is draft. Labels/status updates increment version and audit. Codes/parents/classification/rules
are immutable; use new records/policies for changes. Enabling needs catalog.publish in addition to manageDrafts;
disabling needs catalog.pause. Shared taxonomy disabling is frozen after publication: pause affected versioned
services instead. This intentionally prevents an unaudited cross-service launch change.

## Expert grant
Explicit evidence review -> grant for exact parent scope + catalog version + jurisdiction.
Grant -> revoked is irreversible for that tuple. Revoked records remain auditable; renewed successor scope
requires new explicit grant. KYC approval/renewal never automatically enables services or expands mapping.
