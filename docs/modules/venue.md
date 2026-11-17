# Venue module (venues, suites, packages, suite rates)

Living doc. Created 2026-11-17.

## Data
- `venues_and_suites` (`App\Models\Venue`): one table for both kinds, split by `type` (`venue` | `suite`).
  - Venues are priced with `price_per_day` (full day) and `price_morning`, `price_afternoon`, `price_evening`
    (slots).
  - For suites, `price_per_day` is the **regular nightly rate**.
- `venue_packages` (`VenuePackage`): event packages for venues.
- `booking_platforms` (`BookingPlatform`): the admin-managed list of channels (Agoda, Booking.com, …).
  - The defaults are inserted **by the migration**, because production never runs `db:seed`.
  - "Regular rate" is not a row.
- `suite_platform_rates` (`SuitePlatformRate`): one nightly rate per (suite, platform). Unique on
  (`venue_id`, `booking_platform_id`).
- `bookings` stores what was used:
  - `booking_platform_id` (null = regular);
  - `rate_per_night` (a snapshot, so later rate changes never alter existing bookings);
  - `platform_reference`.

## Services
- `App\Services\Venue\SuiteRateService`
  - `ratesFor(Venue)`: platform id → nightly rate.
  - `syncRates(Venue, array)`: transactional; an empty value removes the rate.
  - `quote(Venue, ?int $platformId): SuiteRateQuote`: platform rate, else regular rate (`usedFallback = true`).
  - `grid(): RateGrid`: active suites, all platforms and every rate, for the Room Rates screen.
  - `applyChanges(array $cells): int`: saves edited grid cells in one transaction and skips unchanged ones. A cell
    with no platform is the regular rate (`price_per_day`, required); an empty platform cell removes that rate.
- `App\Services\Settings\BookingPlatformService`
  - Create, update, toggle and delete platforms.
  - Delete throws `PlatformInUseException` when bookings reference the platform.

## Pricing rule (suites)
`base = quote(suite, platform).ratePerNight × nights`, then add-ons and discount exactly as before.

Places that apply this rule:
- PHP: `Admin\BookingController@store` and `@update`.
- JS: `window.getSuiteNightlyRate()` (also refreshes each Rate option's price label) in `admin/bookings/partials/rate-source-fields.blade.php`, used by
  `create-script` and the edit page.
- Not applied: `PublicController` and the public views, which keep the regular rate.

Keep the PHP and JS copies in sync.

On edit, the stored `rate_per_night` is kept while the suite and rate source are unchanged. Changing either re-quotes
at the current rate.

## Routes (admin only)
- `admin.booking-platforms.{index,store,update,toggle,destroy}`: `Admin\BookingPlatformController`
- `admin.rates.{index,update}`: `Admin\RoomRateController`, the Room Rates grid (`/admin/rates`)
- `admin.suites.rates.edit`: redirects old per-suite links to `/admin/rates#suite-{id}`

## Decisions
- [2026-11-17 — Per-platform room rates](../decisions/2026-11-17-suite-platform-rates.md)
- [2026-11-17 — Room Rates workspace](../decisions/2026-11-17-room-rates-workspace.md)
