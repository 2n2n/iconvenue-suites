# 2026-11-17 — Room Rates workspace (edit all suite and platform rates on one screen)

**Status:** Implemented

## Context
[Per-platform suite rates](2026-11-17-suite-platform-rates.md) gave every suite a regular rate plus a rate per booking
platform. Managing those prices took too many steps:
- the regular rate sat on the full suite edit form;
- platform rates were on a separate Rates page per suite;
- platforms had their own sidebar page.

Updating the Agoda price for 5 rooms meant 5 page visits and 5 saves.

Management changes prices often, for example to run a "promo". Here a **promo just means changing the suite rates and
per-platform rates by hand**. Outcome wanted: one screen to see and change any rate quickly.

## What we discussed
- **Where rates are edited.**
  - Options: keep per-suite pages, or **one grid** of rooms × (Regular + each platform).
  - **Chosen: one grid** (`/admin/rates`), edited inline and saved once. The per-suite Rates page is removed, and its
    URLs redirect to the grid.
- **Special promotions.** Date-ranged rates, time-based promos, buy-1-take-1 and promo codes were considered.
  - **Rejected by the user:** there are no special promotions for now. A promo is a manual rate change.
- **Change history with revert** (undo a whole promo in one click). It was proposed, then **dropped** for the same
  reason: keep it to plain manual editing.
- **Speeding up repeated edits.** An optional "apply to selected rooms" helper: set to ₱, or raise/lower by ₱ or %. It
  fills the cells only. Nothing is saved until Save.
- **Booking form.** Each Rate option shows its price for the selected room, so staff can see which channel price they
  are using.
- **Shipping.** Same PR as the platform-rates feature (user's choice). The feature was still uncommitted.

## Agreed decision
- Sidebar **Room Rates** (admin only) replaces the "Booking Platforms" item. "Manage platforms" opens the existing
  platforms page.
- **Grid**
  - Rows are active suites. Columns are Regular plus every platform; inactive platforms are labelled.
  - Every cell is a number input.
  - An empty platform cell shows the regular price in grey. Clearing a platform cell removes that rate, so the room
    uses the regular rate.
  - The Regular cell can't be empty.
- **Saving.** A sticky save bar shows the unsaved count, Discard and Save. One Save updates every changed cell in one
  transaction. The page warns before you leave with unsaved changes.
- **No schema change.** The grid writes `venues_and_suites.price_per_day` and `suite_platform_rates`. Bookings keep
  their `rate_per_night` snapshot, so rate changes apply to **new bookings only**.
- **Code.**
  - `SuiteRateService::grid()` and `applyChanges()`.
  - `Admin/RoomRateController` (index, update) and `Requests/Venue/UpdateRoomRatesRequest`.
  - `SuiteRateController`, `UpdateSuiteRatesRequest` and `admin/suites/rates.blade.php` are removed.

## BDD scenarios
New:
- Admin edits rates for several rooms at once to update prices in one save
- Admin sets one price for selected rooms to change a platform's rate quickly
- Admin discards unsaved rate edits to keep the current prices
- Admin spots rooms without a channel price to fill in missing rates
- Staff sees each rate's price when booking to choose the right channel

Changed:
- "Admin sets platform rates for a suite to match each channel's price" now runs through the grid.
- "Staff is denied access to platform rates to keep pricing admin-controlled" now covers `/admin/rates`.

## Scope / Out of scope
In scope:
- the Room Rates grid and its "apply to selected" helper;
- navigation and entry points;
- Rate option prices on the booking form;
- removal of the per-suite Rates page;
- the Dusk stability fix (waiting for page fade-in animations).

Out of scope:
- any special promotion feature;
- rate change history or undo;
- rate calendars;
- channel-manager sync;
- the existing login-throttle behaviour (successful sign-ins count toward the limit), which is reported separately.

## Files affected
- `app/Services/Venue/SuiteRateService.php`, `app/Data/Venue/RateGrid.php`
- `app/Http/Controllers/Admin/RoomRateController.php`, `app/Http/Requests/Venue/UpdateRoomRatesRequest.php`
- `routes/web.php`
- Views:
  - `resources/views/admin/rates/{index,partials/grid-script}.blade.php`
  - `layouts/admin`, `admin/suites/{index,edit}`, `admin/booking-platforms/index`
  - `admin/bookings/{edit,partials/rate-source-fields,partials/create-script}`
- Removed: `SuiteRateController`, `UpdateSuiteRatesRequest`, `admin/suites/rates.blade.php`
- Tests:
  - `tests/Feature/Venue/RoomRatesGridTest.php`, `tests/Feature/Booking/SuitePlatformRatePricingTest.php`
  - Dusk tests under `tests/Browser/`

## Open questions
None.

## Deviations from plan
- **Price labels.** The Rate option labels are refreshed inside `getSuiteNightlyRate()`. It already runs on every room
  and rate change, so no new hooks were added to `create-script` or the edit page.
- **Last updated.** The "Last updated" column uses the newest of the suite's `updated_at` and its platform rates'
  `updated_at`. Saving a platform rate touches the suite.
