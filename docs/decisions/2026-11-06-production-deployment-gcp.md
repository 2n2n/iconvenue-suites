# 2026-11-06 — Production deployment on GCP (private, Tailscale-only)

**Status:** Superseded by [2026-11-06 — Production on Cloud Run + MariaDB on a free-tier VM](2026-11-06-production-cloud-run.md) (never built)

## Context
This is the first production release of the booking app. The target is the GCP project `icon-events-513902` (name
`icon-events`), created 2026-11-06. It has billing on and no resources yet: the Compute, Cloud Run and Cloud SQL Admin APIs
were all off. The business already runs a Tailscale tailnet and wants **only selected devices** to reach the app. The app
was built for Windows/XAMPP and has a Docker dev stack (`docker-compose.yml`, `docker/php/Dockerfile` running `php -S`).
There was no production setup.

The aim is a cheap, simple production host that the team can run, with no public exposure, working backups, and no chance of
dev or test data or credentials leaking into production.

## What we discussed
- **Hosting: Cloud Run vs Compute Engine VM.** → **One Compute Engine VM (e2-small) running Docker Compose.**
  - Cloud Run does run PHP containers. It was rejected for this app:
    - Tailscale would only work in userspace mode, without a TUN device, and outbound traffic would need a SOCKS proxy.
    - Instances scale to zero and the CPU is throttled between requests, which drops the tailnet connection. Keeping
      min-instances=1 with always-on CPU removes the cost benefit.
    - There is no SSH.
    - Uploads under `storage/app/public` and the copy into `public/storage` would be lost, because the filesystem doesn't
      persist.
    - There is no cron for `schedule:work`.
    - File sessions and cache would not be shared between instances.
  - Making Cloud Run work would have meant GCS storage, Cloud Scheduler with Cloud Run Jobs, Cloud SQL and a new web server.
  - The VM keeps the current Docker model and runs Tailscale normally.
- **VM size.** → **e2-small (2 shared vCPU, 2 GB)** in `asia-southeast1` (Singapore, the closest full region to the
  Philippines). It costs about $23–26/mo including disk, IPv4, snapshots and egress. e2-medium (~$38–42/mo) is the upgrade if
  memory gets tight; resizing means a few minutes of downtime and nothing is rebuilt. Prices are from third-party price sites
  and should be confirmed in the GCP Pricing Calculator.
- **Exposure.** Two options:
  - A: everything private.
  - B: public pages open, only `/admin` on Tailscale.

  → **A, everything private.** Accepted consequence: clients can't browse the public pages (availability, estimates). Staff
  share availability through other channels. Option B would have needed a public IP, a domain and HTTPS.
- **Database: MariaDB on the VM vs Cloud SQL for MySQL.** → **MariaDB 10.11 in Docker on the VM**, the same image as dev.
  - Cloud SQL was rejected:
    - It costs about +$12–18/mo for a dataset of a few KB (the reference dump `icon (3).sql` is 31 KB, 18 tables).
    - It has no MariaDB.
    - Shared-core tiers have no SLA.
  - The dump uses `utf8mb4_unicode_ci`, so moving to MySQL 8 or Cloud SQL later stays possible.
  - Backups are our job: a nightly `mysqldump` to a Cloud Storage bucket plus daily disk snapshots.
- **Starting data.** Options: start empty, import `icon (3).sql`, or run the dev seeder. → **Empty: roles + one admin.**
  - The admin email is `innoendo.itsolutions@gmail.com`, the user's choice.
  - The dev seeder (`admin@iconvenue.com / admin123`, `staff@iconvenue.com / staff123`, sample contact settings) never runs in
    production.
  - The admin password is typed on the server and never committed.
- **Empty `contact_settings` row.** `PublicController` reads `ContactSetting::first()` and `public/contact.blade.php` uses
  `$contact->phone` without a null check, so the Contact page returns 500 on an empty table. Options:
  - Seed a blank row.
  - Fill in Settings before use.
  - Change the page code.

  → **Fill Admin → Settings right after go-live** (the user's choice). It's a go-live checklist step and needs no code change.
- **Mail.** Options: Gmail SMTP with an app password, a transactional provider, or log only. → **Resend over SMTP**
  (`smtp.resend.com:587`, user `resend`, password = the API key). We chose SMTP over Laravel's native `resend` transport so
  no new Composer package is needed. GCP blocks port 25, and 587 works. Resend needs a verified sending domain; see Open
  questions.
- **Deploy method: git pull vs Artifact Registry image.** → **Git pull with a read-only GitHub deploy key** on
  `2n2n/iconvenue-suites`, plus a `deploy/deploy.sh` that builds the image on the VM. We rejected Artifact Registry because it
  adds moving parts and a small extra cost for one VM.
- **Tailscale.** → **Join the existing tailnet** as `tag:iconvenue-prod`, hostname `iconvenue`.
  - HTTPS comes from `tailscale serve`, which uses a `*.ts.net` certificate and proxies to `127.0.0.1:8080`.
  - Tailnet ACLs decide which devices can reach it.
- **HTTPS URLs.** `tailscale serve` ends TLS and sends plain HTTP to Apache, so Laravel would build `http://` links.
  - Rejected: trusting proxy headers. It depends on which headers `tailscale serve` sends and on the Docker bridge IP.
  - → **`URL::forceScheme('https')` behind a new `config('app.force_https')`** (`APP_FORCE_HTTPS`, off by default so dev
    and Dusk are unchanged), plus `SESSION_SECURE_COOKIE=true`.
- **Web server.** The dev image runs PHP's built-in server (`php -S`), which isn't meant for production.
  → **A `php:8.3-apache` production image**: familiar from XAMPP, `.htaccess` already exists, and no extra nginx container.
  The code is baked into the image, `composer install --no-dev`, opcache is on, and production reads `.env` with
  `config:cache`. That avoids the `artisan serve` env-stripping problem entirely.
- **Queue and Redis.** → **Neither.** `QUEUE_CONNECTION=sync`. The Mailables only import `ShouldQueue` and send synchronously.
  Sessions and cache use the `database` driver, whose tables already exist.

## Agreed decision
- **Production host:** GCE VM `iconvenue-prod` in project `icon-events-513902`, zone `asia-southeast1-b`:
  - e2-small, Debian 12, 30 GB pd-balanced, Shielded VM, OS Login.
  - Its service account `iconvenue-vm` can only write to the backup bucket.
- **Network:**
  - The default VPC's `default-allow-ssh/rdp/icmp` rules are removed, so nothing on the internet can connect in.
  - Break-glass SSH is allowed only from the IAP range `35.235.240.0/20` on tcp:22.
  - An ephemeral external IP is used for outbound traffic only (Tailscale, GitHub, Resend, apt).
- **Stack** (`docker-compose.prod.yml`):
  - `app`: php:8.3-apache, bound to `127.0.0.1:8080`.
  - `scheduler`: same image, runs `php artisan schedule:work`.
  - `mysql`: mariadb:10.11, no published port.
  - Volumes: `mysql-data` and the app storage.
- **Access:** `tailscale serve` at `https://iconvenue.<tailnet>.ts.net`. Only ACL-approved tailnet devices can reach it.
- **Backups:**
  - Nightly `mysqldump` at 02:00 Manila time to `gs://icon-events-513902-db-backups`, with a 30-day lifecycle.
  - Daily snapshot schedule, 14-day retention.
  - One test restore before go-live.
- **First data:** a new `php artisan users:create-admin {email}` command, built as a thin command over
  `App\Services\Staff\AdminAccountService`.
  - It makes sure the `admin`/`staff` roles exist and creates one admin with a hidden-prompt password of at least 12
    characters.
  - It refuses an email that already exists.
  - The production entrypoint never seeds.
- **Mail:** Resend SMTP once a sending domain is verified; `MAIL_MAILER=log` until then.

## BDD scenarios
Exempt (infra/config). There is no change to a user-visible flow: no route, controller action or Blade change.
- The prod Dockerfile, compose file and deploy/backup/provision scripts are infra.
- `app.force_https` is config, off by default.
- `users:create-admin` is an ops CLI. Instead of Dusk it is covered by a PHPUnit feature test (in-memory SQLite):
  - Admin creates the first admin account to bootstrap production
  - Admin retries an existing email to keep the original account safe
  - Admin enters a short password to be asked for a stronger one

The existing Dusk suite must stay green.

## Scope / Out of scope
- **In:**
  - The prod image, compose file, entrypoint and env template.
  - Deploy, backup and provision scripts.
  - The `users:create-admin` command and service, and the `force_https` config.
  - GCP resources: Compute API, firewall, service account, bucket, VM, snapshot schedule.
  - The runbook `docs/deployment.md`.
- **Out:**
  - Public exposure or a custom domain for the app.
  - CI/CD.
  - Staging environment.
  - Monitoring/alerting beyond GCP defaults.
  - Importing `icon (3).sql`.
  - Fixing the Contact page's null handling.
  - The approved "no auto-completion" business change (`bookings:complete-expired` keeps running every minute as it does today).

## Files affected
- New:
  - `docker/php/Dockerfile.prod`, `docker/php/entrypoint.prod.sh`, `docker/php/apache-vhost.conf`
  - `docker-compose.prod.yml`
  - `deploy/env.production.example`, `deploy/deploy.sh`, `deploy/backup-db.sh`, `deploy/provision-vm.sh`
  - `app/Services/Staff/AdminAccountService.php`, `app/Console/Commands/CreateAdminUser.php`
  - `tests/Feature/Staff/CreateAdminUserTest.php`
  - `docs/deployment.md`
- Edited:
  - `config/app.php`, `app/Providers/AppServiceProvider.php`, `.dockerignore`
  - `docs/README.md`, `docs/architecture.md`, `CLAUDE.md`

## Open questions
- **Resend sending domain.** Resend only delivers to arbitrary recipients from a verified domain (DNS records). Without one
  it can send only from `onboarding@resend.dev` to the account owner. Which domain will the business use? Until it's
  verified, production runs with `MAIL_MAILER=log`, so no client emails go out.
- **Tailnet ACL.** Which devices or users get access to `tag:iconvenue-prod`?

## Deviations from plan
Filled in when implementation finishes.
