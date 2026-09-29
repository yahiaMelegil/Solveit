# ADR-001: Separate account models and stable API paths

Status: Accepted for Solveit on 2026-09-28 by the backend owner.

## Decision

- Retain independent `User`, `Expert`, and `Admin` models, providers, tables, and Sanctum token ownership. Do not migrate to a unified account model.
- The current `/api/*` paths and response shapes remain stable. Introduce explicit API versioning before the first incompatible change; use an additive or negotiated migration where the existing frontend consumes the old contract.
- Backend policies, Gates, account-type middleware, and current database state remain the authority for permissions. Frontend permission state is a presentation cache only.

## Requirements traceability

This decision supersedes SRS BR-002 **only for account identity storage**. The user explicitly chose separate models. Requirements for one Case involving several domains or experts, controlled cross-account invitations, and scoped access remain in force. Future User/Expert interactions must use explicit relations and permissions, not an implicit shared identity. The SRS should carry this exception in its next approved revision.

## Consequences

- The same email may exist in different account tables. Password reset, verification, token revocation, and audit actor references must retain an account type.
- A person acting as both User and Expert has two independent login sessions unless a later product decision explicitly authorizes account linking. Account linking is outside Sprint 0.
- Every new API contract states account type, token ability, and any Admin permission. An Admin `403` cannot be replaced by hiding a button in React.
- Existing responses and `/api/*` routes must not change without a frontend impact review, fixture updates, regression tests, and a changelog entry.

## Evidence

`routes/api.php`, `config/auth.php`, `app/Models/{User,Expert,Admin}.php`, and `tests/Feature/{Authentication,Expert/Auth,Admin}`. SRS section 6 BR-002 and the accepted 2026-09-28 backend decision.
