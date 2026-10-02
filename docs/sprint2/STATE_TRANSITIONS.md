# Case state transitions
| From | Operation | To | Conditions |
|---|---|---|---|
|none|create|draft|verified User, validated partial payload, idempotency|
|draft / needs_information|autosave/context/document edits|same|expectedVersion; append input/revision; invalidate confirmation|
|draft|assessment blocked|needs_information|stored reasons and questions|
|needs_information|assessment blocked or suitable|needs_information|suitability is separate from Case state|
|draft|assessment suitable|draft|requires explicit confirmation then submit|
|draft / needs_information|submit|ready_for_matching|all requirements, current suitable assessment, explicit confirmation|
|draft / needs_information / ready_for_matching|cancel|cancelled|expectedVersion; no deletion|
|ready_for_matching / cancelled|edit / assess / confirm / submit|409|no reopen workflow|

Every write is serialized under owner then Case row locks and increments Case version once.
A replay returns the stored successful response and makes no second transition/audit/file.
Suitability: not_assessed,suitable,needs_clarification,unsupported,urgent_stop.
Any material edit invalidates confirmation. Scan completion advances Case version. Submit re-evaluates
policy, source-context eligibility, catalog and active document scan status, not just the old assessment.
Risk reason codes are user-safe product routing information, never a professional diagnosis.

Document scan: pending -> clean/rejected/failed. Only operator retry can move failed or stale pending back
into pending on editable active documents. Clean/rejected revisions cannot be overwritten; upload a new revision.
Logical document removal revokes access to every revision. No retention purge or account erasure is included.
