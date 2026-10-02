# Business Rules — Icon Venue & Suites Booking System

Living document for the project. High-level business process and business rules only.
Last updated: 2026-10-02

Maintained through the `/product-review` command (see `.claude/commands/product-review.md`).

Client requirements: [`client/TPH_Enhancement_Task_Register_2026-10-02.csv`](client/TPH_Enhancement_Task_Register_2026-10-02.csv), mapped to the internal backlog in [`client/REQUIREMENT_MAPPING.md`](client/REQUIREMENT_MAPPING.md).

## Product overview

A booking system for a property that rents two kinds of spaces:

- **Venues** — function/event spaces booked by time slot or full day.
- **Suites** — rooms for overnight stays, each with a room number.

Users:

- **Client (public)** — browses venues and suites, checks availability and estimated price, then contacts the business. Clients do not create bookings themselves.
- **Staff** — creates and manages bookings, records payments, manages clients, toggles availability.
- **Admin** — everything staff can do, plus venues/suites, packages, add-ons, staff accounts, settings, reports, homepage carousel.

## Core business process

1. Client browses the website and checks availability for a date.
2. Client contacts the business (phone, email, social, or Google Form).
3. Staff creates the booking → status **Pending**, payment **Unpaid**.
4. Staff records payment(s): partial or full. Payments are verified by staff.
5. When verified payments cover the total → booking becomes **Confirmed**, payment **Paid**. Partial coverage → payment **Partial**.
6. After the stay/event ends → booking becomes **Completed** (currently automatic, or manually by staff). *Approved change: staff-only completion — see "Approved rules" below.*
7. Booking may be **Cancelled** at any time; tracked add-on stock is returned.

Clients receive email on booking creation, status changes, payment status changes, and a reminder the day before.

## Booking rules — Suites (current)

- Standard stay: **22 hours — check-in 2:00 PM, check-out 12:00 PM the next day.**
- Price: one flat rate per night (price per day × number of days). No time-of-day pricing.
- Multi-night stays: staff sets the number of days.
- Same-day walk-in bookings are allowed if the suite is free.
- Availability is by calendar date: one booking occupies the suite for the whole date(s); no two bookings can share a date.
- Reports label every suite booking as "Suite (22 hours)".
- There is no concept of extending a stay; changes are made by editing the booking.

## Booking rules — Venues (current)

- Three fixed time slots: Morning 8 AM–12 PM, Afternoon 1 PM–5 PM, Evening 6 PM–10 PM; or Full Day.
- Allowed combinations: single slot, Morning+Afternoon, Afternoon+Evening, or Full Day. Morning+Evening is not allowed.
- Each slot can have its own price; Full Day has its own price.
- Same-day bookings are allowed only for slots that have not started yet.
- Packages can be attached to venues, optionally with slot-based pricing.

## Pricing & payments

- Total = venue/suite amount + add-ons − discount (fixed amount or percentage, with a reason).
- Add-ons can track stock; stock is reduced at booking and returned on cancellation.
- Payments cannot exceed the remaining balance; "Full Payment" must equal the balance exactly.

## Unique constraints

- Suites are single-occupancy per date — a suite cannot hold more than one booking on the same date.
- Venue slots are fixed times, not custom hours.
- Booking reference format: IVS-YYYY-XXXX.
- Bookings are staff-created only; the public site is informational/availability only.

## Initiative: Short-time stays (3-hour blocks) with extensions

Client requirement: a new client offers short-time suite stays, and guests sometimes extend.

Status: requirements agreed (2026-09-30); build planned. Backlog epics: E0 E2E foundation, E1 stay settings & stay modes, E2 time-based availability, E3 short-stay booking, E4 extensions, check-out & overstay, E5 reporting & dashboard, E6 booking upgrades/downgrade guard, E7 cancellation options, E8 venue configuration, E9 security & audit, E10 release & handover.

### Approved rules (not yet built)

Stay settings (property-wide, admin):

- Default behavior is unchanged: every suite is a 22-hour overnight stay (2:00 PM – 12:00 PM next day) until short stays are switched on.
- When short stays are on, admin sets: block length (default 3 hours), grace period, turnover/cleaning buffer between guests, short-stay operating hours, and default rates (short-stay block, extension block, overnight).

Room stay mode (per suite, admin only):

- Each suite has a stay mode: **Overnight** (default), **Short-stay**, or **Both** (short stays and overnight on the same suite and day, with the buffer between them). Example: 5 rooms — 2 Short-stay, 3 Overnight.
- Only admin changes a room's stay mode. The change applies immediately and is blocked while the room is occupied or when future bookings conflict with the new mode (conflicting booking references are listed). Every change is logged (who, when, from → to, reason).
- Scheduling a stay-mode change from a future date is deferred (later).

Booking a stay (staff):

- Each booking records its own stay type (short-stay or overnight). Changing a room's stay mode never changes existing bookings, their price or their reports.
- Staff can switch the stay type during booking (e.g. book a Short-stay room as overnight) when the room is free for that time plus buffer. This affects that booking only; the room's configured mode stays the same.
- Price follows the booking's stay type: the room's rate for that stay type, or the property-wide default rate if the room has none. Example — Room 1 (Short-stay): ₱300 per 3-hour block, ₱300 per extension, ₱2,500 overnight; switching a booking to overnight changes its price from ₱300 to ₱2,500.
- New overnight bookings check out at 12:00 PM the day after the last night. Existing bookings are not changed.
- Availability for suites is by time range (check-in to check-out plus buffer), not by whole date.
- An existing short-stay booking can be upgraded to overnight (long-term); the price is recalculated and the original values and payments are kept in history.
- An existing overnight (long-term) booking can never be converted to short-stay or a short-stay rate. New bookings may still choose either stay type.

Extensions, check-out and overstay (staff and admin, no approval step):

- One extension = a full 3-hour block at the extension rate. An extension is blocked if the room is needed by the next guest (including buffer).
- Extensions are added to the balance and settled at check-out; the booking stays Confirmed and payment becomes Partial until paid.
- Early check-out: the base stay and all extensions are non-refundable. The room is available again from the actual check-out time plus buffer.
- The app never completes bookings automatically (applies to all bookings, venues included). Staff check the guest out:
  - balance ₱0 → **Completed**;
  - balance due → **Checked out – balance due**, then **Completed** when the payment is recorded.
- Stays past their check-out time that staff have not checked out are shown on the dashboard as due for check-out.
- Overstay without an extension (past check-out + grace): flagged; staff enter the charge or waive it, with a reason (who and when are recorded).

### Areas in the current system that assume the 22-hour / per-date suite model (where to look)

- Suite availability check (by date, whole-day blocking) — `Venue` model, availability check.
- Suite end date and auto-completion time (12 PM on end date) — scheduled completion command and the auto-complete middleware (two copies of the same rule).
- Suite pricing (price per day × days) — admin booking create and update.
- Suite calendar display (one "suite" block per date) — admin and public calendar data.
- Fixed "2 PM / 12 PM / 22 hours" wording — suite admin pages, booking form, public suites page, reminder email, report export label.
- Day-before reminder email — only fires for bookings on the next calendar day.

### Observations to review with the team (facts, no recommendation)

- Single-night suite bookings store the end date as the check-in date, while completion runs at 12 PM on the end date (before the 2 PM check-in). Look at: admin booking store/update end-date calculation vs. the completion command and middleware. *Decision: fixed for new bookings only.*
- The public calendar only lists bookings by their start date; later nights of a multi-night stay may appear free. Look at: public calendar data.
- Editing a booking recalculates the total from the base price only (add-ons and discount not included). Look at: admin booking update.
- The reminder email template still references a single time-slot field that was replaced by multiple slots. Look at: booking reminder email view.
- The database dump contains a "time slot times" field on bookings and a same-day-booking setting migration that has no content. Look at: the two empty migrations dated 2026-02-11 and 2026-06-10.

### Also requested by the client (2026-10-02)

- Admin-configurable cancellation options.
- Configurable dashboard KPIs; revenue and payment reports that separate payment status and admin-verified received payments.
- Configurable venue variables (pricing/payment values) and venue workflow fixes.
- Audit trails for booking and payment changes, and user access (login) history.
- Changed workflows validated on phone, iPad, laptop and desktop browsers.

### Scope

- Extensions, overstay charges, early check-out, removal of auto-completion and the staff check-out flow are **change requests** (not in the client register) and are quoted separately.

## Open questions (business)

- Default values for the turnover buffer, grace period and short-stay operating hours.
- Minimum/maximum number of extensions, and how late an extension can be requested.
- Reporting: which KPIs and report figures; cash received (verified payments) vs booked revenue? Should short stays be reported separately?
- Upgrade pricing: is the short-stay amount already charged credited toward the overnight rate?
- Cancellation options to offer (client input).
- Venue values that must be configurable (client input).
- Confirm "long-term" in the client register means the overnight (22-hour, multi-night) stay.

## Decision log

- 2026-09-29 — Short-time stay initiative opened; codebase reviewed for impact. No decisions yet.
- 2026-09-30 — Stay settings: default 22-hour overnight; short stays opt-in, 3-hour blocks; property-wide default rates.
- 2026-09-30 — Room stay mode per suite (Overnight / Short-stay / Both), admin only, immediate change with conflict check and change log. Scheduled (future-dated) changes deferred.
- 2026-09-30 — Staff may switch a booking's stay type during booking; price follows the stay type; missing room rate uses the property default.
- 2026-09-30 — Extensions: full 3-hour block at the extension rate, settled at check-out; base stay and extensions non-refundable on early check-out.
- 2026-09-30 — Overstay: flagged; staff enter or waive the charge with a reason. Staff and admin can extend/charge without approval.
- 2026-09-30 — No automatic completion for any booking; staff check-out; "Checked out – balance due" until paid.
- 2026-09-30 — Overnight check-out fixed to 12 PM the day after the last night for new bookings only; existing records unchanged.
- 2026-10-02 — Client register received and mapped. Existing long-term bookings can never be downgraded to short-stay; new bookings may use either stay type.
- 2026-10-02 — Extensions, overstay, early check-out and staff check-out are change requests, quoted separately. Reporting and dashboard raised to high priority.
