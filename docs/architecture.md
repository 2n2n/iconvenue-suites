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

## Production topology (as of 2026-11-06)
See [deployment.md](deployment.md) and [the decision](decisions/2026-11-06-production-cloud-run.md).

```
Registered tailnet device ──WireGuard──> iconvenue-db: tailscale serve https://iconvenue.tailf0e670.ts.net
  └─> nginx 127.0.0.1:8080 ──HTTPS (Host/SNI = run.app)──> Cloud Run service iconvenue (--ingress internal;
      run.app from the internet = 404; us-central1, php:8.3-apache, 0–2 instances)
                     ├─ /var/www/html/storage/app/public = gs://icon-events-513902-uploads (GCS FUSE mount)
                     ├─ APP_KEY, DB_PASSWORD from Secret Manager
                     └─ Direct VPC egress ──tcp:3306──> VM iconvenue-db 10.128.0.2 (e2-micro, MariaDB 10.11, no external IP)
Cloud Scheduler ──> Cloud Run Jobs: iconvenue-reminders (09:00 Manila), iconvenue-complete-expired (every 15 min)
deploy/cloudrun-deploy.sh ──> Cloud Build ──> Artifact Registry ──> job iconvenue-migrate ──> service
iconvenue-db: nightly mariadb-dump → gs://icon-events-513902-db-backups (via Private Google Access) · daily snapshots (7 days)
```
- **The whole app is reachable only from registered tailnet devices**
  ([decision](decisions/2026-11-06-tailnet-only-access.md), [guide](tailscale-guide.md)).
  - `APP_PUBLIC_HOST` makes the prod entrypoint set `X-Forwarded-Host` in Apache, so Laravel builds every URL and
    redirect on the ts.net host.
  - `POST /login` is rate-limited (`throttle:login`, 5/min per email + IP; behind the gateway this is effectively per
    email).
- `trustProxies(at: '*')` in `bootstrap/app.php` gives Laravel the real client IP and scheme behind Cloud Run.
- `QUEUE_CONNECTION=sync` (mail is synchronous). Sessions, cache and the login limiter use the `database` driver,
  shared across instances. There is no Redis in production.
- Logs go to stderr (Cloud Logging). The image caches routes, views and events at build time; config is cached at
  container start because it contains the secrets.
- The production entrypoint never seeds. The first admin comes from `php artisan users:create-admin --password-env=…`
  run as a one-off Cloud Run Job.

## Legacy policy
**New code only.** Existing fat controllers are left as they are unless a refactor is explicitly requested and approved. New actions — even inside a legacy controller — follow the target architecture.
