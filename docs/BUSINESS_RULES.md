# Business Rules — Icon Venue & Suites Booking System

Living document for the project. High-level business process and business rules only.
Last updated: 2026-09-29

Maintained through the `/product-review` command (see `.claude/commands/product-review.md`).

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
6. After the stay/event ends → booking becomes **Completed** (automatically, or manually by staff).
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

## Open initiative: Short-time stays (2–3 hours) with extensions

Client requirement: a new client offers short-time suite stays of around 2–3 hours, and guests sometimes extend.

Status: discovery. Business decisions needed before build — see "Open questions".

Areas in the current system that assume the 22-hour / per-date suite model (where to look):

- Suite availability check (by date, whole-day blocking) — `Venue` model, availability check.
- Suite end date and auto-completion time (12 PM on end date) — scheduled completion command and the auto-complete middleware (two copies of the same rule).
- Suite pricing (price per day × days) — admin booking create and update.
- Suite calendar display (one "suite" block per date) — admin and public calendar data.
- Fixed "2 PM / 12 PM / 22 hours" wording — suite admin pages, booking form, public suites page, reminder email, report export label.
- Day-before reminder email — only fires for bookings on the next calendar day.

Observations to review with the team (facts, no recommendation):

- Single-night suite bookings store the end date as the check-in date, while completion runs at 12 PM on the end date (before the 2 PM check-in). Look at: admin booking store/update end-date calculation vs. the completion command and middleware.
- The public calendar only lists bookings by their start date; later nights of a multi-night stay may appear free. Look at: public calendar data.
- Editing a booking recalculates the total from the base price only (add-ons and discount not included). Look at: admin booking update.
- The reminder email template still references a single time-slot field that was replaced by multiple slots. Look at: booking reminder email view.
- The database dump contains a "time slot times" field on bookings and a same-day-booking setting migration that has no content. Look at: the two empty migrations dated 2026-02-11 and 2026-06-10.

## Open questions (business)

- Short-stay product: fixed blocks (e.g., 3 hrs) or any hour count? Minimum/maximum?
- Pricing: flat block rate, per-hour rate, or tiered? How are extensions priced (per hour, per block, grace period)?
- Extensions: who approves, how late can they be requested, what if the next booking conflicts?
- Cleaning/turnover buffer between guests — how long?
- Operating hours for short stays (24/7 or limited)?
- Can short stays and overnight stays mix on the same suite and same day?
- Which suites offer short stays — all, or configurable per suite?
- Payment for extensions: settled on checkout, or paid upfront per extension?
- Late checkout / overstay without request — charged how?
- Is this client-specific (per property) or a product-wide option?
- Reporting: should short stays be reported separately (occupancy, revenue per hour)?

## Decision log

- 2026-09-29 — Short-time stay initiative opened; codebase reviewed for impact. No decisions yet.
