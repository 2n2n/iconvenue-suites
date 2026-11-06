# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

Icon Venue & Suites booking system: a server-rendered **Laravel 12 / PHP 8.2+** app. Public visitors browse venues and suites, check availability and get cost estimates. **Staff and admins** create bookings for clients, record and verify payments, and manage inventory from `/admin`. There is no client-facing checkout, and clients never log in.

Currency is PHP (₱). Local development runs in **Docker** (`/run-dev`). The app was originally built on Windows/XAMPP with MariaDB, so keep the XAMPP-compatible behaviour (see Images / uploads).

Rules in `.claude/rules/` load automatically: senior Laravel standards, strict MVC, modular services, the docs workflow, the Definition of Done and test naming.

## Non-negotiables

- **Plan → approve → document.** Every plan lists its BDD scenarios. Once a plan is approved, the decision entry goes in `docs/decisions/` *before* any code (`.claude/rules/docs-workflow.md`). Business-rule decisions also go in the `docs/BUSINESS_RULES.md` decision log.
- **A feature is done when its BDD test passes.** That means a Gherkin `.feature` file plus a Laravel Dusk test, written by the `bdd-qa-tester` agent (`.claude/rules/definition-of-done.md`).
- **New code follows strict MVC and service modules:** FormRequests, `app/Services/{Module}/`, thin controllers. **Legacy fat controllers stay as they are** unless a refactor is approved (`docs/architecture.md`).
- **Tests never touch a real database.** PHPUnit is forced onto in-memory SQLite (`phpunit.xml` `force="true"` on both `<env>` and `<server>`, plus a guard in `tests/TestCase.php`). Dusk runs only in the `dusk-app` container against `iconvenue_testing`, guarded in `tests/DuskTestCase.php`. On 2026-11-06 a test run inside `app` wiped the dev database, which is why these guards exist. Keep them.

## Business context

Business process, booking rules, open initiatives and the decision log live in `docs/BUSINESS_RULES.md` (imported below). Use `/product-review <topic>` for a read-only product review that updates that document.

The domain rules below describe the **current code**. `docs/BUSINESS_RULES.md` → "Approved rules (not yet built)" lists agreed changes (short stays, room stay modes, extensions, staff-only completion). When implementing those, follow the approved rules and keep existing behaviour backward compatible.

@docs/BUSINESS_RULES.md

## Commands (Docker)

| Task | Command |
|---|---|
| Start the stack (starts Docker if it's off) | `/run-dev` (add `--testing` for Dusk, `--tools` for phpMyAdmin, `--build` to rebuild) |
| Artisan | `docker compose exec app php artisan <cmd>` |
| PHPUnit | `docker compose exec app php artisan test [--filter=…]` (in-memory SQLite) |
| BDD / Dusk | `docker compose exec dusk-app php artisan dusk [--filter=…]` |
| Format | `docker compose exec app vendor/bin/pint --dirty` |
| Re-seed dev DB | `docker compose exec app php artisan db:seed` (the entrypoint seeds only once, gated by `storage/.seeded`) |

Useful artisan commands: `schedule:work`, `bookings:complete-expired`, `bookings:send-reminders`, `bookings:generate-references`, `images:clean --dry-run`.

URLs: app http://localhost:8000, admin http://localhost:8000/login, Mailpit http://localhost:8025, phpMyAdmin :8080 (`--tools`), Selenium noVNC :7900 (password `secret`). MariaDB is on host port 3307. Credentials are in `docker-compose.yml`.

Seeded logins: `admin@iconvenue.com / admin123`, `staff@iconvenue.com / staff123`.

**Databases:**
- Docker dev: `iconvenue`. Dusk: `iconvenue_testing`. XAMPP setups use `venue_booking`.
- `.env.example` defaults to `sqlite`, but the real target is MariaDB/MySQL.
- `icon (3).sql` in the repo root is a phpMyAdmin dump of a real database. Treat it as reference data: don't edit it, and don't commit new dumps.

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
app/Helpers/ImageHelper + ImageServiceProvider   image_url()/image_urls()/image_exists() helpers
resources/views/        Blade: layouts/{public,admin,app}, public/*, admin/*
```

- **Legacy controllers are fat.** There's no service layer yet, and validation is inline `$request->validate()`. Leave existing actions as they are unless a refactor is approved. **New** actions, even inside a legacy controller, use FormRequests and `app/Services/{Module}/` (see `.claude/rules/mvc-architecture.md` and `service-modules.md`).
- **Roles**: `users.role_id` → `roles.name` (`admin` | `staff`). Use `$user->isAdmin()` / `isStaff()`. Every `/admin` route requires `auth`. Admin-only routes sit inside the `AdminMiddleware` group in `web.php`. The `public` middleware alias *redirects logged-in staff to the dashboard* and sets no-cache headers, so logged-in users can't view the public site.
- **Frontend**: Blade + Tailwind **via CDN** (`cdn.tailwindcss.com`), Font Awesome 6, Alpine.js and jQuery, all from CDNs in the layouts. The Vite/Tailwind v4 setup in `vite.config.js`/`resources/css` is scaffolding and **no** layout loads it (only the unused default `welcome.blade.php` calls `@vite`). Don't add `@vite`, and don't expect `npm run build` to change pages. Page JS lives inline in Blade partials (e.g. `admin/bookings/partials/create-script.blade.php`, `public/partials/venue-details-script.blade.php`).

## Domain rules (read before touching bookings/pricing)

- **One table, two types**: the `Venue` model maps to the table **`venues_and_suites`** (renamed from `venues`). `type` is `venue` or `suite`. Validation rules must use `exists:venues_and_suites,id`. Foreign keys are still named `venue_id`.
- **Time slots** (venues only): `morning` 8AM–12PM, `afternoon` 1PM–5PM, `evening` 6PM–10PM. `bookings.time_slots` is a JSON array, and **empty/null means full day**. Allowed combinations: a single slot, morning+afternoon, afternoon+evening, or full day. **Morning+evening is rejected.** A same-day booking can't include a slot that has already started.
- **Suites** are booked by the day (`number_of_days`, `end_date = booking_date + days - 1`). Check-in is 2:00 PM and auto-complete is 12:00 PM on `end_date`. Same-day walk-ins are allowed, and any date overlap blocks a suite.
- **Availability** lives in `Venue::isAvailable($start, $end, $excludeId, $slots)`. Cancelled bookings don't block. Reuse it instead of querying again.
- **Pricing** (see `BookingController@store`):
  - Venue full day = `price_per_day × days`.
  - Slots = the sum of `price_morning`/`price_afternoon`/`price_evening`, falling back to `price_per_day`.
  - Packages (`VenuePackage`) override venue pricing. A time-priced package's full day = the sum of its three slot prices.
  - Add-ons add `price × qty` and store `price_at_booking` on the `booking_addons` pivot.
  - Discounts (amount or %) set `original_amount`, `discount_amount` and `discount_percentage`. `total_amount` is the figure after the discount.
  - `update` recalculates only the base venue/package amount, without add-ons or discount.
- **Pricing is duplicated** in PHP (`BookingController`, `PublicController::calculateEstimatedCost`) and in the Blade JS estimators. Apply any pricing change in all of those places and keep them in sync.
- **Add-on stock**: when `track_stock` is true, creating a booking decrements `stock_quantity` inside the `DB::transaction`, and cancelling returns it. Keep stock changes transactional.
- **Status lifecycle**: bookings start `pending`. They become `confirmed` when verified payments ≥ `total_amount` (`PaymentController::verifyPayment`), `completed` automatically after the booking ends, or `cancelled`. `payment_status` is `unpaid`/`partial`/`paid`. Auto-completion logic exists in **both** the `AutoCompleteBookings` middleware (throttled via cache, runs on web requests) and the `bookings:complete-expired` command, so change both together. *Approved change: remove automatic completion entirely, and staff check guests out (see `docs/BUSINESS_RULES.md`).*
- **Booking reference** `IVS-YYYY-XXXX` is generated in `Booking::boot()` on create.
- Bookings and payments use **soft deletes**.
- Emails are sent synchronously inside a `try/catch` that only logs failures. A mail error must never break a booking or payment flow.

## Images / uploads

- Files are stored on the `public` disk (`storage/app/public/...`). Render them with `image_url($path)`, which falls back to `public/images/placeholder.jpg`.
- The code **copies** uploaded files into `public/storage/` to work around Windows/XAMPP symlink limits. Keep that behaviour when adding upload paths.
- `HandlesImageUploads` references Intervention Image behind a `class_exists` guard, but the package is **not** in `composer.json`, so don't assume it's installed.

## Testing

- **E2E BDD**: Gherkin + Laravel Dusk.
  - Features go in `tests/Browser/features/{module}/`, tests in `tests/Browser/{Module}/` and page objects in `tests/Browser/Pages/`.
  - Name every case as a verb with an objective: `<Actor> <verb>s <object> to <objective>`.
  - Conventions and how to run them: `docs/testing/bdd.md`.
  - Only `dev`/test databases, never production or a live schema. Ship new settings tables with a seeder for testing.
- **PHPUnit** tests are thin (`ImageUploadTest` + examples). They assume `role_id = 1` is admin, so create roles in test setup. `ImageUploadTest` and `Feature\ExampleTest` currently fail for exactly that reason (no roles, no migrations). Add a feature test when changing pricing, availability or payment logic.

## Conventions & gotchas

- Style: PSR-12 / Pint defaults, 4-space indent, LF (`.editorconfig`).
- Schema changes go in new timestamped migrations; never edit existing ones.
  - Migrations must be additive and reversible: new columns nullable, a working `down()`.
  - They must not change existing booking records. Code falls back to legacy rules when new columns are empty.
  - `2026_02_11_005918_add_allow_same_day_booking_…` and `2026_06_10_093113_add_time_slot_times_…` have empty `up()`/`down()` bodies.
- `php artisan serve` drops the container env, which is why the Dockerfile runs `php -S` directly. Keep it that way.
- Troubleshooting:
  - Images missing: `php artisan storage:link`.
  - Permission errors: `chmod -R 775 storage bootstrap/cache`.
  - Vite exits with `Cannot find module @rollup/rollup-linux-…`: run `docker compose rm -sf vite && docker volume rm iconvenue-suites_node_modules`, then `/run-dev`.
- `README.md` is written for people and is partly out of date: it describes XAMPP setup, lists email notifications and a calendar view as "future" (both exist), and links to files that don't exist (`SETUP_INSTRUCTIONS.md`, `PROJECT_OVERVIEW.md`, `SYSTEM_FLOW.md`, `QUICK_REFERENCE.md`). `USER_GUIDE.md` is the end-user guide and the source for BDD scenarios. Update it when user-visible flows change.
- Never commit `.env`, credentials, or DB dumps.

## Where things are

- Docs: `docs/README.md` (index and decision template), `docs/BUSINESS_RULES.md`, `docs/architecture.md`, `docs/testing/bdd.md`, `docs/decisions/`.
- Claude Code: the `bdd-qa-tester` agent, the `/run-dev` skill and the `/product-review` command. Hooks live in `.claude/settings.json`: a docs reminder after plan approval, and a BDD gate on Stop.
