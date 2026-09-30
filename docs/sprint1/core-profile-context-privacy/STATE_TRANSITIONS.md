# State transitions and recovery

## Profiles and contexts

Profile and Preferences start at virtual version 0. An actual PATCH increments version; a normalized no-op keeps it. expectedVersion is mandatory; stale values return 409 and never overwrite data. Profile history stores the initial snapshot before the first modification.

| Context state | Command | Result |
|---|---|---|
| None | Create | active/version 1 |
| active | Edit / opt in or out | active/new version if changed |
| active | Archive | archived/new version |
| archived | Restore | active/new version |
| active or archived | Delete | deleted/new version, no payload response |
| archived | Edit |409 INVALID_STATE_TRANSITION |
| deleted | Show/edit/history/restore |404; exact successful DELETE replay is still allowed |

Conflicting values for the same fact key and effectiveDate retain both snapshots and mark unresolved. A later explicit clarification appends a clarified version. Version history never overwrites an earlier fact. canUseInFutureCase is an eligibility hint only; there is no Case entity/access grant.

## Consent

No record -> not_recorded (derived, never fabricated acceptance). Granted or declined decisions append to the purpose chain, with exact PolicyVersion FK/hash and server time. The same current decision returns 200 without another event; exact Idempotency-Key replay returns the original status/body. Withdrawal is permitted only for marketing/ai_assistance and must reference the latest granted record and its policy version. Old-version grants can still be withdrawn after policy updates. Terms/privacy optional withdrawal returns 422. Regrant after withdrawal requires the current effective policy.

Any intervening material revision sets requiresReconsent for a grant even when the newest revision is editorial. Policy language is explicit. Marketing withdrawal does not affect essential email. Preferences alone never authorize AI or recording.

## Data rights requests

| From | Allowed next states | Producer / condition |
|---|---|---|
| requested | processing, cancelled | Worker claim or owner cancellation |
| processing | completed, failed, deferred, rejected | Owned worker lease; completed requires verified export bytes |
| failed | processing, cancelled | Bounded queue retry / explicit operator requeue or owner cancellation |
| deferred | processing, cancelled | Reserved future approved executor; only cancellation currently exposed |
| completed/rejected/cancelled | none | Terminal |

Implemented export flow: requested -> processing -> completed, or processing -> failed -> processing. Download expiry changes availability, not historical completion. User can request a fresh export after terminal completion. One open request per owner/type includes requested/processing/deferred/failed.

Implemented deletion flow: requested -> processing -> deferred with RETENTION_POLICY_PENDING. Each section is deferred with reviewAt=dueAt. Nothing is deleted, anonymized or disabled. The item model reserves retainUntil/reviewAt for a future approved retention decision. rejected is supported by the enum and fixtures for rendering but no rejection decision workflow ships. There is no implemented path to completed deletion.

Items: pending -> exported for a successful export; pending/failed -> deferred for deletion; processing failure -> failed; cancellation changes pending/deferred/failed items to cancelled. Items are not an external per-record legal-hold engine.

Each state transition increments request.version, records an audit event in the same transaction, and checks the locked aggregate. HTTP accepts no status/outcome/dueAt fields. Request creation202 means accepted for processing, not completed. Replayed202 can be historical: GET the request for current state.

## Worker and operational recovery

Jobs carry request ID only, dispatch after commit, and have 3 tries,60s timeout with backoff30/120/300s. Each worker claims a random fencing token with a10-minute lease, releases locks during file generation, and commits only if its token still owns the row. A duplicate delivery cannot produce a second completion. Storage failure uses PROCESSING_FAILED with generic queue error text, never plaintext export content.

An expired lease can be reclaimed by a subsequent job. The hourly privacy:cleanup command also moves expired processing leases to failed, clears the token, and records PROCESSING_LEASE_EXPIRED, so a killed/exhausted worker does not remain processing forever. Operators can run privacy:retry REQUEST_ID for requested/failed rows (e.g. broker failure after commit). It audits OPERATOR_RETRY and refuses deferred/terminal rows. CLI access is an existing infrastructure privilege, not an Admin API permission. Retries are operator controlled after automatic attempts; no infinite scheduler retry loop.

Storage writes are outside the DB transaction. Artifacts are encrypted, checksum verified after read-back, and completed only after the owned lease commits metadata. Stale worker files are discarded; old orphan files on the dedicated disk are cleaned after the configured TTL plus 1 hour. Cleanup failure is nonzero and retryable. Application-level immutable models do not prevent privileged direct SQL; constrain production DB grants accordingly.
