# Audit and security

## Implemented events

| Event | Subject / actor | When |
|---|---|---|
| user.profile_updated | user_profile / User | Atomic with version snapshot |
| user.preferences_updated | user_preferences / User | Actual preference change |
| user.context_created | context / User | Initial version |
| user.context_updated | context / User | Content/reuse/conflict clarification version |
| user.context_archived / active / deleted | context / User | Lifecycle version |
| user.consent_recorded | consent / User | New purpose-specific decision |
| user.privacy_identity_confirmed | user / User | Correct password confirmation |
| user.data_request_created | data_request / User | Persisted requested state and items |
| data_request.transitioned | data_request / User or system | Valid locked state transition |
| data_request.requeued | data_request / system | Authorized CLI operator retry |
| user.export_downloaded / denied | data_request / User | Verified download or unavailable artifact |
| export.artifact_expired | data_request / system | Successful expiration cleanup |
| admin.consent_metadata_viewed | user / Admin | Permissioned consent metadata read |
| admin.data_request_list_viewed | data_request / Admin | Permissioned list; subject_id=0 denotes collection |
| admin.data_request_metadata_viewed | data_request / Admin | Permissioned detail |

Metadata allowlist: changedFields/fromVersion/toVersion/policyVersionId/itemCount. Actor account type and numeric token ID distinguish overlapping IDs without storing token plaintext. API uses server X-Request-ID; background events generate their own UUID and correlate by request subject. This is the Sprint1 event catalogue only: older Auth/KYC events are not retroactively claimed as new audit rows. Existing KYC histories are unchanged.

Application model hooks prevent updating/deleting histories, policy versions, consent records and audit events. They do not provide cryptographic immutability or prevent direct SQL/bulk query bypass. No general audit-reading API is introduced. Financial/legal retention is not invented; FK restrictions and no purge keep evidence intact until approved policy.

## Controls and practical limits

Concrete account middleware runs before private resource access. Admin uses Gate::authorize plus explicit permissions; User queries scope through authenticated relationships and Policies. Sensitive fields are never mass assigned from raw requests. Form Requests reject top-level/nested unknown fields, bound sizes, validate domain schemas and optimistic versions. Reads are paginated, related histories/policies are eager loaded, and consent summary queries select latest records per purpose.

Multi-step writes + audit events commit atomically; tests inject an audit failure and verify no partial Profile write. Existing-owner locks protect first-create/dedup races. Worker fencing tokens protect artifact completion from duplicate/stale workers. SQLite tests do not prove row-lock behavior on the production database.

New Profile phone, snapshots, Context payload/clarification and idempotency responses are encrypted using existing Laravel cryptography. Export artifacts are encrypted before private storage, verified by checksum/read-back and retrieved only after ownership and fresh token/purpose-specific password proof. No public URL is created; Cache-Control private,no-store and attachment headers are sent. APP_KEY lifecycle is essential to recover historical data. Plaintext exists in process memory during authorized serialization/download.

New API 500 responses and reported exceptions redact payload/SQL/provider messages; operational logs retain exception class and request ID only. Existing baseline exception contracts are not changed. Queue processing catches provider errors and throws safe generic errors. Third-party request tracing/proxies must also exclude passwords, authorization headers, Profile/Context bodies and exports; application redaction cannot control an external logger.

Privacy cleanup expires response caches and export artifacts, recovers dead processing leases and deletes old orphan files only in the dedicated export disk. It never deletes user histories or legal evidence. Read-only Admin access is minimal but itself sensitive and audited. The application does not claim legal certification, Staging verification, browser integration or production security testing.
