---
paths:
  - "app/**/*.php"
  - "routes/**/*.php"
  - "resources/views/**/*.blade.php"
---

# Strict MVC

Every piece of new code lives in exactly one layer. Map: `docs/architecture.md`.

## Controller — thin
An action does four things: type-hint a FormRequest, call **one** service/action method, pick the response (view, redirect, JSON), flash the message. Aim for ~10 lines.
Queries, `DB::transaction`, `Mail::`, pricing/availability rules and loops over models belong in the service layer (`service-modules.md`).

```php
public function store(StoreBookingRequest $request, BookingService $bookings): RedirectResponse
{
    $booking = $bookings->create(BookingData::fromRequest($request));

    return redirect()->route('admin.bookings.show', $booking)->with('success', 'Booking created.');
}
```

## FormRequest — input
All validation and request-level authorization: `app/Http/Requests/{Module}/{Verb}{Entity}Request.php`, e.g. `Booking/StoreBookingRequest`. Normalise input in `prepareForValidation()`.

## Model — data
Relations, `$casts`, `$fillable`, query scopes, accessors/mutators and simple state checks (`isPending()`). Workflows that touch other aggregates, send mail or read the request go to a service.

## View (Blade) — presentation
Render data the controller passed in. Formatting comes from model accessors or a view model; Blade holds layout, loops over given data and `@dusk` selectors for tests.

## Authorization & routes
Role gates via middleware (`AdminMiddleware`, `StaffMiddleware`) or Policies. Every route is named and sits in the group for its actor (public, guest, `admin.` staff+admin, admin-only).

## Legacy code
Existing fat controllers stay as they are unless a refactor is requested and approved. New actions — including ones added to a legacy controller — follow this file.
