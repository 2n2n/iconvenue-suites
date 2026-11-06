# Architecture

## Current state (as of 2026-11-06)
- Laravel 12, Blade + Tailwind 4 + Vite, MariaDB, Docker Compose.
- Business logic lives in controllers: `Admin/BookingController` (738 lines), `PublicController` (426), `Admin/VenueController` (413). Validation is inline `$request->validate()`, transactions and `Mail::` calls sit in actions.
- No service layer, no FormRequests, no Policies. Authorization is middleware (`AdminMiddleware`, `StaffMiddleware`).
- Shared code worth reusing: `app/Helpers/ImageHelper.php`, `app/Traits/HandlesImageUploads.php`, Mailables in `app/Mail/`, scheduled commands in `routes/console.php`.

## Target architecture (all new code)

```
Route → Controller → FormRequest (validate + authorize)
                   → {Module}Service / Action  → Models, Events, other modules' Services
                   ← Model | DTO
        Controller → View / Redirect / JSON
```

| Layer | Location | Owns |
|---|---|---|
| Controller | `app/Http/Controllers/{Admin}/` | Receive request, call one service method, return response |
| FormRequest | `app/Http/Requests/{Module}/{Verb}{Entity}Request.php` | Validation rules, `authorize()` |
| Service | `app/Services/{Module}/{Name}Service.php` | Use cases, transactions, business rules |
| Action | `app/Actions/{Module}/{Verb}{Entity}.php` | Single-purpose use case (optional) |
| DTO | `app/Data/{Module}/` | Typed data crossing layers |
| Exception | `app/Exceptions/{Module}/` | Domain failures |
| Model | `app/Models/` | Relations, casts, scopes, accessors |
| View | `resources/views/` | Presentation only |

**Modules:** Booking, Venue (venues, suites, packages), Addon, Payment, Client, Staff, Report, Settings, Carousel, Media.

## Legacy policy
**New code only.** Existing fat controllers are left as they are unless a refactor is explicitly requested and approved. New actions — even inside a legacy controller — follow the target architecture.
