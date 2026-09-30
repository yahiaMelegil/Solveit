# Deferred scope and required decisions

Implemented: self Profile/Preferences, private versioned Contexts, versioned consent/policy registry, explicit withdrawal, audit events, actual encrypted JSON export/download/cleanup, request cancellation/failure recovery, deletion-request lifecycle with honest deferral, and permissioned read-only Admin metadata.

| Deferred / missing decision | Current behavior | Required next step |
|---|---|---|
| Approved policy text/version/effective date/locales | Registry empty unless deliberately provisioned; no fake consent | Publish approved append-only PolicyVersion records |
| Response SLA/jurisdiction policy | New rights requests return 409 until due_days is set | Owner sets approved operational SLA; tests use30 only as fixture |
| Final account erasure/anonymization, financial/legal holds | Deletion becomes deferred; all user/evidence rows retained | Define legal retention per class, hold/release rules, identity assistance, backups and approved executor |
| Unverified/inaccessible account rights channel | User API requires verified email | Provide approved assisted identity-verification/access process |
| Expert formal appeal, new-scope extension and automatic expiry/reminder operations | Single-scope renewal is now implemented under its new contract; ADR-002 remains | Separate future contracts for formal appeal, extensions and scheduler |
| Admin decisions/rejections/retention overrides | Read-only metadata; rejected reserved | Separate permissions, evidence and transition contract |
| Case Core and sharing contexts with Experts | No Cases, sharing or Expert context access | Case must reference exact context version and authorize purpose anew |
| AI execution, model training, recording | Preferences only, training false | Independent consent enforcement/session authorization before any processor exists |
| Notification translation and scheduled contact delivery | Language/timezone stored; existing essential notifications preserved | Add approved templates/scheduling when that module is scoped |
| Large-account export streaming | Current bounded-field records assembled in memory; historical record count unbounded | Load-test with representative account volumes; stream/chunk if required |
| Database-engine concurrency and infrastructure security | SQLite regression plus separate-process database queue/cache verified; production engine untested | Test actual production engine, shared cache/queue, IAM/storage and key retention |
| Production tamper-resistant audit / retention | Application append-only, no destructive retention job | Define DB grants, immutable archival, legal holds and eventual retention schedule |
| Staging and React end-to-end | Not run | Close Sprint0 UI integration then perform Sprint1 acceptance |

The non-regulated context domain/fact-key catalog is a versioned initial product configuration, not a legal/professional eligibility registry. Any catalog expansion or incompatible schema change needs a contract changelog. No package, identity merge or authentication redesign was introduced.

See EXPERT_REVIEW_BOUNDARY.md and ../expert-renewal/README.md for the now-approved renewal addition. Final deletion/anonymization remain deferred by explicit owner instruction.
