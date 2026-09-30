# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

Icon Venue & Suites booking system — a server-rendered **Laravel 12 / PHP 8.2** app. Public visitors browse venues/suites, check availability and get cost estimates; **staff and admins** create bookings on behalf of clients, record/verify payments, and manage inventory from `/admin`. There is no client-facing checkout — clients never log in.

Currency is PHP (₱). Developed on Windows/XAMPP with MariaDB.

## Business context

Business process, booking rules, open initiatives and the decision log live in `docs/BUSINESS_RULES.md` (imported below). Use `/product-review <topic>` for a read-only product review that updates that document.

@docs/BUSINESS_RULES.md

## Commands

```bash
composer install
cp .env.example .env && php artisan key:generate   # then set DB_* (see below)
php artisan migrate --seed          # seeds roles, admin/staff users, contact settings
php artisan storage:link
php artisan serve                   # http://localhost:8000, admin at /login

php artisan test                    # PHPUnit (sqlite :memory:, see phpunit.xml)
php artisan test --filter=ImageUploadTest
./vendor/bin/pint                   # code style (Laravel Pint)
php artisan migrate:fresh --seed    # reset local DB

php artisan schedule:work           # run scheduler locally
php artisan bookings:complete-expired
php artisan bookings:send-reminders
php artisan bookings:generate-references
php artisan images:clean --dry-run
```

Seeded logins: `admin@iconvenue.com / admin123`, `staff@iconvenue.com / staff123`.

**Database:** `.env.example` defaults to `sqlite`, but the real target is MariaDB/MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=venue_booking`). `icon (3).sql` in the repo root is a phpMyAdmin dump of a real database — treat it as reference data, don't edit it, and don't commit new dumps.

## Architecture

```
routes/web.php          all routes (public, auth, /admin with admin-only subgroup)
routes/console.php      scheduler: send-reminders daily 09:00, complete-expired every minute
bootstrap/app.php       middleware aliases + AutoCompleteBookings appended to `web` group
app/Http/Controllers/
  PublicController      public pages + JSON endpoints (calendar-data, addons-data, check-availability)
  AuthController        login/logout
  Admin/*               BookingController (largest, ~740 lines), VenueController (venues, suites,
                        packages), PaymentController, VenueAddonController, Report/Staff/Settings/...
app/Models/             Booking, Venue, VenuePackage, VenueAddon, Payment, User, Role, ...
app/Console/Commands/   scheduled + maintenance commands
app/Mail/               Booking/Payment status + reminder mailables (views in resources/views/emails)
app/Helpers/ImageHelper + ImageServiceProvider   global image_url()/image_urls()/image_exists()
resources/views/        Blade: layouts/{public,admin,app}, public/*, admin/*
```

- **Controllers are fat**; there is no service layer. Validation is inline `$request->validate()`, no FormRequests. Follow the existing pattern unless asked to refactor.
- **Roles**: `users.role_id` → `roles.name` (`admin` | `staff`). Use `$user->isAdmin()` / `isStaff()`. Every `/admin` route requires `auth`; admin-only routes sit inside the `AdminMiddleware` group in `web.php`. The `public` middleware alias *redirects logged-in staff to the dashboard* and sets no-cache headers — logged-in users can't view the public site.
- **Frontend**: Blade + Tailwind **via CDN** (`cdn.tailwindcss.com`), Font Awesome 6, Alpine.js and jQuery from CDNs in the layouts. The Vite/Tailwind v4 setup in `vite.config.js`/`resources/css` is scaffold and is **not** loaded by any layout (only the unused default `welcome.blade.php` calls `@vite`) — don't add `@vite` or expect `npm run build` to affect pages. Page JS lives inline in Blade partials (e.g. `admin/bookings/partials/create-script.blade.php`, `public/partials/venue-details-script.blade.php`).

## Domain rules (read before touching bookings/pricing)

- **One table, two types**: the `Venue` model maps to table **`venues_and_suites`** (renamed from `venues`); `type` is `venue` or `suite`. Validation rules must use `exists:venues_and_suites,id`. FKs are still named `venue_id`.
- **Time slots** (venues only): `morning` 8AM–12PM, `afternoon` 1PM–5PM, `evening` 6PM–10PM. `bookings.time_slots` is a JSON array; **empty/null = full day**. Allowed combos: single slot, morning+afternoon, afternoon+evening, or full day — **morning+evening is rejected**. Same-day bookings can't include a slot that has already started.
- **Suites** are booked by day (`number_of_days`, `end_date = booking_date + days - 1`), check in 2:00 PM, auto-complete at 12:00 PM on `end_date`, allow same-day walk-ins, and any date overlap blocks them.
- **Availability** lives in `Venue::isAvailable($start, $end, $excludeId, $slots)`; cancelled bookings don't block. Reuse it rather than re-querying.
- **Pricing** (see `BookingController@store`; `update` recalculates the base venue/package amount only, without add-ons or discount): venue full day = `price_per_day × days`; slots = sum of `price_morning/afternoon/evening` (falling back to `price_per_day`); packages (`VenuePackage`) override venue pricing, and a time-priced package's full day = sum of its three slot prices. Add-ons add `price × qty` and store `price_at_booking` on the `booking_addons` pivot. Discounts (amount or %) set `original_amount`, `discount_amount`, `discount_percentage`; `total_amount` is the post-discount figure.
- **Pricing is duplicated** in PHP (`BookingController`, `PublicController::calculateEstimatedCost`) and in the Blade JS estimators. Any pricing change must be applied in all places and kept in sync.
- **Add-on stock**: when `track_stock` is true, creating a booking decrements `stock_quantity` inside the `DB::transaction` and cancelling returns it; keep stock changes transactional.
- **Status lifecycle**: bookings start `pending`; become `confirmed` when verified payments ≥ `total_amount` (`PaymentController::verifyPayment`), `completed` automatically after the booking ends, or `cancelled`. `payment_status` is `unpaid`/`partial`/`paid`. Auto-completion logic exists in **both** `AutoCompleteBookings` middleware (throttled via cache, runs on web requests) and the `bookings:complete-expired` command — change both together.
- **Booking reference** `IVS-YYYY-XXXX` is generated in `Booking::boot()` on create.
- Bookings and payments use **soft deletes**.
- Emails are sent synchronously inside `try/catch` that only logs failures — a mail error must never break a booking/payment flow.

## Images / uploads

- Stored on the `public` disk (`storage/app/public/...`); render with `image_url($path)` which falls back to `public/images/placeholder.jpg`.
- Code **copies** uploaded files into `public/storage/` as a Windows/XAMPP symlink workaround. Keep that behavior when adding upload paths.
- `HandlesImageUploads` references Intervention Image behind a `class_exists` guard, but the package is **not** in `composer.json` — don't assume it's installed.

## Conventions & gotchas

- Style: PSR-12 / Pint defaults, 4-space indent, LF (`.editorconfig`).
- New schema changes go in new timestamped migrations; never edit existing ones. `2026_02_11_005918_add_allow_same_day_booking_…` and `2026_06_10_093113_add_time_slot_times_…` have empty `up()`/`down()` bodies.
- Tests are thin (`ImageUploadTest` + examples). They assume `role_id = 1` is admin — create roles in test setup if you add tests that need them. Add a feature test when changing pricing, availability or payment logic.
- `README.md` references files that don't exist (`SETUP_INSTRUCTIONS.md`, `PROJECT_OVERVIEW.md`, `SYSTEM_FLOW.md`, `QUICK_REFERENCE.md`). `USER_GUIDE.md` is the end-user guide — update it when user-visible flows change.
- Never commit `.env`, credentials, or DB dumps.
