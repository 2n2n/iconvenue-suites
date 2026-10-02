# Client Requirement Mapping

Traceability between the client's enhancement register and the internal backlog.

- Client register: [`TPH_Enhancement_Task_Register_2026-10-02.csv`](TPH_Enhancement_Task_Register_2026-10-02.csv) (commercial notes removed)
- Internal backlog IDs: `E0`–`E10` (exported as CSV from the planning sessions)
- Business rules: [`../BUSINESS_RULES.md`](../BUSINESS_RULES.md)
- Last updated: 2026-10-02

## Client tasks → internal tasks

| Client task | Requirement | Title | Internal tasks | Coverage |
|---|---|---|---|---|
| TASK-001 | FR-014 | Short-stay vs long-term model | Decisions in BUSINESS_RULES.md; E1-2 | Covered (decided 2026-09-30) |
| TASK-002 | FR-014 | Classification/configuration fields | E1-2, E1-5, E1-6 | Covered |
| TASK-003 | FR-002 | Configurable short-stay interval (every X hours) | E1-1 | Covered |
| TASK-004 | FR-015 | Short-stay → long-term upgrade | E3-6 (at booking), E6-1 (active booking) | Covered; E6-4 pricing question open |
| TASK-005 | FR-015 | Prevent long-term downgrade to short-stay rate | E6-2 | Covered |
| TASK-006 | FR-016 | Configurable cancellation options | E7-1, E7-2, E7-3 | Planned; E7-1 client input needed |
| TASK-007 | FR-010 | Operational dashboard | E5-3, E4-10 | Planned; E5-4 client input needed |
| TASK-008 | FR-011 | Revenue and payment reports | E5-1, E5-2 | Planned; E5-4 client input needed |
| TASK-009 | FR-012 | Venue/hall booking workflow | E8-3 | Planned; E8-1 client input needed |
| TASK-010 | FR-013 | Configurable venue pricing/payment variables | E8-2 | Planned; E8-1 client input needed |
| TASK-011 | SEC-001 | User access history | E9-4, E9-5 | Planned |
| TASK-012 | SEC-002 | Booking audit trail | E9-1, E9-2, E1-7 | Planned |
| TASK-013 | SEC-002 | Payment audit trail | E9-1, E9-3 | Planned |
| TASK-014 | NFR-001 | Cross-device browser validation | E0-1, E10-1 | Planned |
| TASK-015 | FR-004, FR-006–FR-009 | Regression of retained features | E0-6, E10-2 | Planned |
| TASK-016 | Implementation | Deployment to client environment | E10-3 | Planned |
| TASK-017 | OPS-001 | Handover to monthly support | E10-4 | Planned (operations) |

## Internal tasks not in the client register

| Tag | Internal tasks | Notes |
|---|---|---|
| **CR** (change request, quoted separately) | E2-3, E4-1 – E4-11 | Extensions (3-hour blocks), overstay charges, early check-out, removal of auto-completion, staff check-out, "Checked out – balance due". |
| Internal enablers | E0-1 – E0-5, E2-1, E2-2, E2-4 – E2-6, E3-1 – E3-5, E9-1 | Test infrastructure, time-based availability, pricing consistency, shared audit log. Needed to deliver TASK-001 – TASK-005 and TASK-012/013. |
| Later | E1-9 – E1-11 | Scheduled (future-dated) room stay mode changes. |

## Open inputs needed from the client

- E5-4 — Dashboard KPIs and report figures; cash received (verified payments) vs booked revenue.
- E6-4 — On upgrade, is the short-stay amount already charged credited toward the overnight rate?
- E7-1 — Cancellation options to offer.
- E8-1 — Venue values that must be configurable; examples of inconsistent booking states.
- Terminology — confirm "long-term" in the register means the overnight (22-hour, multi-night) stay.
