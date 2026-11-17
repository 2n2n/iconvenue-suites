# 2026-11-17 — Per-platform room rates (Regular / Agoda / Booking.com …)

**Status:** Implemented

## Context
Each suite has a single price, `venues_and_suites.price_per_day`. In practice the property sells the same rooms on
third-party booking platforms (Agoda, Booking.com, …) and every platform has its own price. Staff had to work out
those prices by hand when they entered a platform booking in the back office.

Outcome wanted:
- Each suite has a **regular rate** and a manually maintained **rate per booking platform**.
- When staff create a booking, they pick which rate to use. The base price follows that rate, and discounts still
  apply on top.
- Only the "super admin" manages platforms and rates.

## What we discussed
- **Who manages platforms and rates.** The app has only two roles: `admin` and `staff`.
  - Options: treat the existing **admin** as the super admin, or add a new `super_admin` role above admin.
  - **Chosen: existing admin.** The admin-only route group (`AdminMiddleware`) already gives exactly that access. A
    new role would need a role migration, seeder changes and middleware for no practical gain today.
- **How platforms are defined.**
  - Options: a **managed list** (admin adds, renames and deactivates platforms), or a fixed list in code.
  - **Chosen: managed list.** New channels (e.g. Expedia) can be added without a developer. "Regular rate" is built
    in and is not a platform row.
- **Room with no rate for the selected platform.**
  - Options: hide that platform for the room, or **fall back to the regular rate**.
  - **Chosen: fall back to regular.** Every active platform is always selectable, and the form shows a note when the
    fallback applies.
- **Scope.**
  - Options: **suites only**, or suites and event venues.
  - **Chosen: suites only.** Online travel platforms sell rooms. Venue slot, full-day and package pricing stay
    unchanged. Adding venues would have meant per-platform prices for every slot.
- **Platform confirmation number.**
  - **Chosen:** store an optional **platform reference** on the booking, for reconciling with the platform's
    extranet.
- **Reports.**
  - Options: a column only, a column plus a revenue-by-platform summary, or no change.
  - **Chosen: column + summary.** The export gets Rate/Platform columns, and the Reports page gets bookings and
    revenue by platform.

## Agreed decision
- **Platforms.** New `booking_platforms` table (name, active, sort order), seeded with Agoda and Booking.com.
  - A platform that bookings reference can be deactivated but not deleted.
  - Inactive platforms can't be chosen for new bookings.
- **Room rates.** New `suite_platform_rates` table: one nightly rate per (suite, platform).
  - `price_per_day` stays and **is the regular rate**. No data migration is needed.
  - Public pages keep using it.
- **Bookings.** Three new nullable columns:
  - `booking_platform_id` (null = regular rate, so every existing booking stays "Regular");
  - `rate_per_night`, a snapshot of the rate used, so later rate changes never alter existing bookings;
  - `platform_reference`.
- **Price** for a suite booking: (the selected platform's nightly rate, or the regular rate when that suite has no
  rate for the platform) × nights, plus add-ons, minus discount. The discount rules are unchanged.
- **Editing a booking** keeps the stored nightly rate while the suite and platform are unchanged. Changing either
  re-quotes at the current rate.
- **Admin pages.** "Booking platforms" (admin only) and "Rates" per suite (admin only). Staff only *select* a rate
  when booking.
- **Code.** New code follows the target architecture:
  - `App\Services\Settings\BookingPlatformService`, `App\Services\Venue\SuiteRateService`;
  - FormRequests and thin controllers (`BookingPlatformController`, `SuiteRateController`).
  - The legacy `BookingController` and `ReportController` get targeted edits only.

## BDD scenarios
- Admin adds a booking platform to price rooms per channel
- Admin deactivates a booking platform to stop staff from selecting it
- Admin sets platform rates for a suite to match each channel's price
- Staff is denied access to platform rates to keep pricing admin-controlled
- Staff books a suite at a platform rate to charge the channel price
- Staff books a suite at the regular rate to charge walk-in pricing
- Staff books a suite on a platform without a room rate to fall back to the regular rate
- Staff applies a discount to a platform-rate booking to reduce the total
- Staff records the platform reference to reconcile with the channel
- Admin views revenue by platform to compare channels

## Scope / Out of scope
In scope:
- platform management;
- per-suite platform rates;
- rate selection, platform reference and pricing in admin booking create, edit and show;
- the Reports summary and CSV columns.

Out of scope:
- venue platform pricing;
- commission or payout tracking;
- syncing rates from the platforms (channel manager);
- date-based or seasonal rates;
- the public site (it keeps showing the regular rate);
- the early suite auto-completion bug (separate fix).

## Files affected
- Migrations: `create_booking_platforms_table`, `create_suite_platform_rates_table`,
  `add_platform_fields_to_bookings_table`. Seeder `BookingPlatformSeeder`.
- Models: `BookingPlatform`, `SuitePlatformRate`, `Venue`, `Booking`.
- Services:
  - `app/Services/Settings/BookingPlatformService.php`
  - `app/Services/Venue/SuiteRateService.php`
  - `app/Data/Venue/SuiteRateQuote.php`
  - `app/Exceptions/Settings/PlatformInUseException.php`
- Controllers: `Admin/BookingPlatformController`, `Admin/SuiteRateController`, `Admin/BookingController` (store,
  update, create, edit), `Admin/ReportController`.
- FormRequests: `Settings/StoreBookingPlatformRequest`, `Settings/UpdateBookingPlatformRequest`,
  `Venue/UpdateSuiteRatesRequest`.
- Views:
  - `admin/booking-platforms/index`, `admin/suites/rates`
  - `admin/bookings/{create,edit,show}`, `admin/bookings/partials/create-script`
  - `admin/suites/{index,create,edit}`, `admin/reports/index`, `layouts/admin`
- Routes: `routes/web.php` (admin-only group).

## Open questions
- Should platform commission be tracked later (net revenue per platform)?

## Deviations from plan
- **Per-suite Rates page replaced** the same day by the Room Rates grid
  ([decision](2026-11-17-room-rates-workspace.md)). The old URL redirects to the grid.
- **Platforms page moved out of the sidebar.** It's now reached through Room Rates → Manage platforms.
- **Inactive platforms on edit.** The edit form also lists a platform that has since been deactivated, so an existing
  booking keeps its rate source.
- **Dusk stability fix.** Dusk tests wait for the admin pages' fade-in animations (`waitForPageToSettle()` in
  `tests/DuskTestCase.php`).
- **Production safety check.** The migrations were verified up → down → up on a copy of `icon (3).sql`, and the
  bookings were byte-identical afterwards.
