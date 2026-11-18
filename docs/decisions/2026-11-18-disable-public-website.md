# 2026-11-18 — Public website turned off; the home page is the staff sign-in

**Status:** Implemented

## Context
Since [tailnet-only access](2026-11-06-tailnet-only-access.md), bookings are staff-only and the site is internal.
The public website was still routed, though:
- `/` was the public home page;
- `/venues`, `/suites` and `/venue/{id}` showed venue details with a "send booking request via WhatsApp" button;
- `/venue/{id}/addons`, `/contact` and the JSON endpoints `calendar-data`, `addons-data` and
  `POST /check-availability` were also reachable.

A guest therefore still had a way to start a booking outside the back office. The user asked to fix the routing "for
now": opening the app shows the login page, and there is no way for a guest to book outside the platform.

## What we discussed
- **Remove the public routes; `/` redirects to `/login`.** Old public URLs return 404. **Chosen**: the smallest
  change, and it leaves nothing a guest can use.
- **Redirect every old public URL to `/login`.** Friendlier for old bookmarks, but it keeps a list of dead routes
  around. Not chosen, because the site has been internal since 2026-11-06.
- **Delete `PublicController` and the public views.** Rejected for now: the user said "for now", so the code stays.
  Turning the site back on means restoring the route group.

## Agreed decision
- `routes/web.php`: the `public` route group is replaced by `Route::redirect('/', '/login')->name('home')`.
  - The `guest` middleware on `/login` already sends signed-in staff and admins to the dashboard.
  - The `home` name is kept, so existing `route('home')` calls still work.
- Signing out redirects to `/login`.
- The login page loses its "Back to Website" link.
- Kept but unused:
  - `PublicController`, `resources/views/public/*`, `layouts/public` and the `public` middleware alias.
  - The admin settings for the contact page and the carousel stay, but they have no visible effect while the site is
    off.
- No migration and no data change.

## BDD scenarios
- Guest opens the website to land on the staff sign-in page
- Guest opens an old venue page to find there is no public booking path
- Staff signs out to return to the sign-in page

## Scope / Out of scope
**In scope:** public routing, the logout redirect and the login page link.

**Out of scope:**
- deleting the public code;
- the contact and carousel admin settings;
- the cancel-via-edit bypass.

## Files affected
- `routes/web.php`, `app/Http/Controllers/AuthController.php`, `resources/views/auth/login.blade.php`
- `tests/Feature/Auth/PublicSiteDisabledTest.php`, `tests/Feature/ExampleTest.php`, Dusk tests under `tests/Browser/Auth/`
- Docs: `docs/BUSINESS_RULES.md`, `docs/architecture.md`, `USER_GUIDE.md`, `CLAUDE.md`

## Open questions
None.

## Deviations from plan
- `tests/Feature/ExampleTest` (a known failure) now uses `RefreshDatabase` and asserts the redirect to `/login`, so it
  passes.
- The plan file was saved to the wrong path, so the approval screen showed an older plan. The details above were
  reported to the user after implementation.
- PHPUnit covers the JSON and POST endpoints and signed-in staff at `/`; Dusk covers the pages a person would open.
