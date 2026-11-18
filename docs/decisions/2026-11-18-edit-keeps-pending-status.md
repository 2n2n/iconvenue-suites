# 2026-11-18 — Editing a pending booking keeps it Pending

**Status:** Implemented

## Context
QA found this while testing the optional client email; the bug is older than that work.
- The edit-booking status dropdown offered only Confirmed, Cancelled and Completed.
- `BookingController@update` accepted only those three values.
- For a **pending** booking no option was pre-selected, so the browser submitted the first one, "confirmed".

As a result, editing a pending booking, even without changing anything, silently **confirmed** it. That bypassed the
rule that a booking becomes Confirmed only when verified payments cover the total
(`PaymentController::verifyPayment`).

## What we discussed
- **Read-only status on edit.** Status changes would happen only through Cancel/Complete (which return add-on stock
  and email the client) and through payment verification. This would also close the cancel-via-edit bypass, but it
  removes manual confirming. **Not chosen.**
- **Add Pending and keep the dropdown.** Smallest change. **Chosen by the user.**
- **Route dropdown changes through the Cancel/Complete logic.** Keeps the dropdown, with stock return and email on
  Cancelled/Completed. **Not chosen.**

**Knowingly left as it is:** choosing Cancelled in Edit still skips the add-on stock return and the client email that
the Cancel button does.

## Agreed decision
- The edit dropdown gets **Pending** as the first option. The selected option is
  `old('status', $booking->status)`, so the current status is always pre-selected.
- `BookingController@update` validates `status` as `in:pending,confirmed,cancelled,completed`.
- Nothing else changes.

## BDD scenarios
- Staff edits a pending booking to keep it pending until payment is verified
- Staff edits a confirmed booking to keep it confirmed

## Scope / Out of scope
**In scope:** the edit dropdown and the update validation.

**Out of scope:**
- the cancel-via-edit bypass;
- the staff client-update route;
- the login throttle.

## Files affected
- `resources/views/admin/bookings/edit.blade.php`
- `app/Http/Controllers/Admin/BookingController.php`
- `tests/Feature/Booking/EditBookingStatusTest.php`, and Dusk tests under `tests/Browser/Bookings/`

## Open questions
None.

## Deviations from plan
None. Dusk attributes were added for the tests: `booking-status-badge` and `booking-notes` on booking details, and
`booking-notes-input` on edit. The wrong-credentials sign-in test now waits for the page reload, which fixed a timing
flake in the test, not in the app.

Noted for the product: staff can still choose Confirmed on an unpaid pending booking in Edit. That's allowed by design
(the user chose to keep the dropdown).
