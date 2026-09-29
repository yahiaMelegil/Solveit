# API contract template for new Sprint 1 endpoints

This is a proposal for **new** endpoints. `ROUTE_INVENTORY.md` and the actual code describe existing endpoints. Do not retrofit this template onto an existing response without a frontend impact review.

## Endpoint

| Item | Value |
| --- | --- |
| Module / owner | TBD |
| Method / URL / route name | TBD |
| Account model / Sanctum ability | `User`, `Expert`, or `Admin`; exact ability |
| Admin permission / object policy | Exact Gate or `none` |
| Request FormRequest | Field names, types, required/nullable, bounds, normalization |
| Success HTTP status / Resource | Full JSON example, including null/empty cases |
| Errors | `401`, `403`, `404`, `409`, `422`, `429`; upload errors where relevant |
| Filters / sort / pagination | Allowlisted fields, defaults, maximum page size |
| Domain lifecycle | States and permitted transitions |
| Side effects | Database records, events, notifications, jobs, private files |
| Idempotency | Key scope, retry behavior, duplicate response |
| Test fixtures | Authorized, forbidden, missing, conflicting, invalid examples |
| Frontend consumers / change log | Exact owner and compatibility plan |

## Proposed new-endpoint envelopes

```json
{"status":true,"message":"Saved successfully.","data":{"item":{"id":1}}}
```

```json
{"status":false,"code":"VALIDATION_FAILED","message":"The provided data is invalid.","errors":{"field":["This field is required."]}}
```

For a new paginated list, propose `data.items` plus `data.pagination` containing `currentPage`, `perPage`, `lastPage`, and `total`. Existing Admin/User/Expert Laravel resource collections use `data/links/meta`, while Admin KYC has its own `data.applications/data.pagination`. Preserve both until a coordinated migration.

Proposed query names: `page`, `perPage`, `search`, `sortBy`, `sortDirection`; feature-specific filters must be explicitly named and allowlisted. Never accept an arbitrary database column or SQL order expression from the client.
