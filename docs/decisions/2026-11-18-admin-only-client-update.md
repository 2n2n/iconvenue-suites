# 2026-11-18 — Only admins can update client details

**Status:** Implemented

## Context
The client page shows the Edit Client button only to admins (`admin/clients/show.blade.php`), but the route behind it,
`PUT /admin/clients/{key}` (`admin.clients.update`), sat in the staff-and-admin route group. A staff account could
send the request directly (e.g. with a crafted form or browser dev tools) and rename a client or change their email
and phone across **all** of that client's bookings. Found during QA of the optional client email
([2026-11-17](2026-11-17-optional-client-email.md)); the gap is older than that work.

## What we discussed
- **Move the route into the `AdminMiddleware` group.** One-line change, matches the button's visibility and how
  every other admin-only action is protected (403 for staff). **Chosen** (user asked to fix it).
- **Let staff edit clients too** (show the button to staff). Rejected: it would change who may edit client records,
  which is a product decision nobody asked for.
- **Policy/Gate on the controller action.** Equivalent result but a second authorization style in a legacy
  controller; the route-group middleware is the existing convention. Rejected.

## Agreed decision
- `Route::put('clients/{email}', …)->name('admin.clients.update')` moves inside the admin-only group in
  `routes/web.php`. URL, name and controller are unchanged; listing and viewing clients stay open to staff.
- Staff sending the update get **403**, and no booking is changed.

## BDD scenarios
- Admin updates a client's details to keep their contact information correct
- Staff views a client without an edit option to keep client records admin-controlled

PHPUnit additionally covers: Staff is refused when updating a client directly (403, data unchanged).

## Scope / Out of scope
**In scope:** authorization of the client update route.

**Out of scope:** the login throttle; the cancel-via-edit bypass.

## Files affected
- `routes/web.php`
- `resources/views/admin/clients/show.blade.php` (Dusk attributes only)
- `tests/Feature/Client/ClientUpdateAuthorizationTest.php`
- `tests/Browser/features/clients/update-client-details.feature`, `tests/Browser/Clients/UpdateClientDetailsTest.php`, `tests/Browser/Pages/ClientPage.php`

## Open questions
None.

## Deviations from plan
None. Dusk attributes were added to the client page (`client-name`, `client-edit-modal`, `client-edit-name`, `client-edit-phone`) for the tests.
