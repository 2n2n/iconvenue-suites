# 2026-11-17 — Client email optional on bookings

**Status:** Implemented

## Context
Staff take bookings from walk-ins and phone callers who have no email or don't want to give one. Admin booking create
and update required `client_email`, and `bookings.client_email` was `NOT NULL`, so staff typed placeholder addresses.
Outcome wanted: staff can leave the email empty, and nothing breaks.

## What we discussed
- **How a client without an email appears on the Clients page.** That page groups clients by email and opens
  `/admin/clients/{email}`, so an empty email would break the page.
  - Options: **group by phone**; list them without a detail page; hide them from Clients.
  - **Chosen: group by phone.** Phone stays required. The client page key is the email, or `tel-<phone>` when there
    is none. Rejected: no detail page (staff lose the client view), and hiding (the clients would be invisible).
- **Telling staff that no emails go out.**
  - Options: **a hint plus a badge**, or skipping silently.
  - **Chosen: hint plus badge.**
    - The field reads "Email (optional)", with a note that it's needed for the confirmation, payment and reminder
      emails.
    - Booking details show "No email — the client won't receive email notifications".
- **What happens to the notifications.** Booking, status, payment and reminder emails are **skipped** when there's
  no email. The reminder command leaves those bookings out entirely.
- **Shipping.** Same PR as platform rates and Room Rates (user's choice).

## Agreed decision
- New migration: `bookings.client_email` becomes nullable.
  - Existing rows are untouched.
  - `down()` turns NULLs into `''` before restoring NOT NULL, so a rollback is safe even after no-email bookings
    exist.
- Validation:
  - `BookingController@store` and `@update`: `nullable|email`.
  - `ClientController@update`: `nullable|email`.
- Model: `Booking::hasClientEmail()` and the `client_key` accessor (the email, or `tel-<phone>`).
- Every client `Mail::to()` call is guarded (BookingController ×4, PaymentController ×2).
  `SendBookingReminders` queries only bookings that have an email.
- Clients:
  - The `{email}` route parameter now carries the client key.
  - Route names and existing email URLs keep working.
- Views: booking create, edit and show, client show, and the calendar modal ("—" when there's no email).

## BDD scenarios
- Staff creates a booking without an email to serve a walk-in guest
- Staff views a booking without an email to know the client won't be notified
- Staff updates a booking to remove the email when the client has none
- Staff opens a client without an email to see their bookings by phone

## Scope / Out of scope
In scope: optional email on admin booking create and update, safe notifications, Clients by phone, and the no-email
indicators.

Out of scope: SMS or other channels for clients without email, and merging duplicate clients.

## Files affected
- `database/migrations/2026_11_17_000004_make_client_email_nullable_on_bookings.php`
- `app/Models/Booking.php`
- `app/Http/Controllers/Admin/{BookingController,PaymentController,ClientController}.php`
- `app/Console/Commands/SendBookingReminders.php`
- `resources/views/admin/bookings/{create,edit,show,calendar}.blade.php`
- `resources/views/admin/clients/{index,show}.blade.php`
- `tests/Feature/Booking/OptionalClientEmailTest.php`, and Dusk tests under `tests/Browser/`

## Open questions
None.

## Deviations from plan
- **Client links encoded once.** Client page links and redirects no longer wrap the key in `urlencode()`, because
  `route()` already encodes it. Double encoding broke links for phones with spaces or `+` (e.g. `+63 918 777 8888`) and
  for emails containing `+`. PHPUnit now covers this case.
- **Notes for follow-up, both older than this change and not fixed here:**
  - editing a pending booking submits status "confirmed" (fixed in [2026-11-18](2026-11-18-edit-keeps-pending-status.md));
  - staff can call the client update route directly, although only admins see the button (fixed in [2026-11-18](2026-11-18-admin-only-client-update.md)).
