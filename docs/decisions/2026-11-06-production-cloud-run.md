# 2026-11-06 — Production on Cloud Run + MariaDB on a free-tier VM (public, no Tailscale)

**Status:** Implemented (live 2026-11-06 at https://iconvenue-1091789543033.us-central1.run.app)
**Partially superseded by:** [2026-11-06 — Tailnet-only access](2026-11-06-tailnet-only-access.md) (access: no longer public)
**Supersedes:** [2026-11-06 — Production deployment on GCP (private, Tailscale-only)](2026-11-06-production-deployment-gcp.md)

## Context
The [VM + Tailscale plan](2026-11-06-production-deployment-gcp.md) was approved but never built. The Compute API was still
off on `icon-events-513902`, and no VM existed. The user wants to **see the app running on Google Cloud now**:
- skip Tailscale;
- move the app to **Cloud Run**;
- keep **MariaDB** (the engine the app was built on);
- pick the **cheapest** option;
- bootstrap the roles and the first admin (`innoendo.itsolutions@gmail.com`).

Research brief (approved): [Laravel 12 on Cloud Run + MariaDB on a free-tier e2-micro](../research/2026-11-06-cloud-run-mariadb-free-tier.md).

The same conversation also asked for a **researcher agent**. It reads tutorials and official docs to add context to
requirements, and approved findings go into `docs/research/`.

## What we discussed
- **Exposure without Tailscale.**
  - The first proposal was a Caddy container on the VM with a Let's Encrypt cert for `<ip>.sslip.io`.
  - Plain HTTP on the IP was rejected because the prod `.env` sets `SESSION_SECURE_COOKIE=true`, so login would fail
    over HTTP.
  - Superseded by the move to Cloud Run, where Google provides HTTPS on `*.run.app`.
  - → **Public HTTPS on `*.run.app`**, with `/login` open to the internet and protected by a **login rate limit**
    (`throttle:5,1`).
- **Hosting.** → **Cloud Run** (user's change of plan).
  - The earlier entry rejected Cloud Run only because of Tailscale (userspace networking, scale-to-zero dropping the
    tailnet). With Tailscale gone, that reason no longer applies.
- **Database.** The user first asked for a MySQL service, then for something that supports **MariaDB**, the cheapest.
  - Google Cloud has **no managed MariaDB**: Cloud SQL offers MySQL, PostgreSQL and SQL Server only.
  - Options weighed:
    - Cloud SQL for MySQL 8.0 `db-f1-micro`: managed, PITR, ~$10–14/month, no SLA on shared core, and MySQL rather than
      MariaDB.
    - MariaDB on an e2-micro in Singapore: ~$8–11/month.
    - **MariaDB on the Always Free e2-micro in `us-central1`: ~$0–3/month.**
    - MariaDB Cloud / SkySQL: a third-party vendor, with current GCP availability unverified.
  - The user asked whether the free tier works in Southeast Asia. **It doesn't:** the free e2-micro exists only in
    us-west1, us-central1 and us-east1 ([Google free tier](https://docs.cloud.google.com/free/docs/free-cloud-features)).
    Cloud Run's free tier has no region restriction.
  - → The user chose **free MariaDB VM in the US**. Cloud Run goes in `us-central1` too, because a DB across the Pacific
    would add latency to every query.
  - Accepted trade-offs:
    - ~150–200 ms extra round trip per page from the Philippines;
    - the team runs the DB (updates, backups);
    - 1 GB RAM;
    - no SLA.
- **Cloud Run → DB connection.** → **Direct VPC egress** (`private-ranges-only`) to the VM's internal IP. A firewall rule
  allows tcp:3306 only from the subnet, and the VM has no external IP after setup.
  - Rejected: a public DB IP with an allowlist, since Cloud Run has no fixed egress IP without Cloud NAT, which costs
    extra.
- **Uploads.** Cloud Run's filesystem is ephemeral.
  - → Mount a **Cloud Storage bucket** at `storage/app/public` and make `public/storage` a symlink to it.
  - `HandlesImageUploads::ensurePublicAccess` then finds the "copy" already present (same file, same mtime) and skips
    it, so the XAMPP copy workaround stays untouched.
  - Rejected: a Flysystem GCS driver, which means code changes across upload paths.
- **Scheduler.** Cloud Run has no always-on worker for `schedule:work`.
  - → **Cloud Scheduler → Cloud Run Job** (`iconvenue-artisan`):
    - `bookings:send-reminders` daily at 09:00 Asia/Manila;
    - `bookings:complete-expired` every 15 min. The `AutoCompleteBookings` middleware already does the same on web
      requests, and the approved rules remove auto-completion later.
  - Rejected: running `schedule:run` every minute, which means a container start every minute.
- **Migrations.** → A **Cloud Run Job** runs `migrate --force` before each deploy, not on service start, so several
  instances can't migrate at once.
- **First admin.** Cloud Run Jobs have no TTY for the hidden password prompt.
  - → `users:create-admin` gets `--password-env=<VAR>`. The user stores the password in Secret Manager from their own
    terminal, a one-off Job runs the command, and then the secret is deleted.
  - The password never goes in a file, in this chat, or in Git.
- **Code delivery.** → **`gcloud builds submit` from the local working tree** to Artifact Registry. Nothing needs pushing
  to GitHub, and no deploy key is needed. A `.gcloudignore` keeps `.env`, the SQL dump, `vendor`, `node_modules` and
  `storage` out.
- **Researcher agent.**
  - → `.claude/agents/researcher.md`, read-only (WebSearch, WebFetch, Read, Grep, Glob). It returns a sourced research
    brief.
  - Approved briefs are saved in `docs/research/` and linked from the decision entry.
  - The docs workflow gains an optional step 0: research.
- **Architecture page.** After the deploy works, republish the
  [architecture artifact](https://claude.ai/artifact/PCLmrYrXCbrrrvqzVsYx7P) to show what's actually deployed.

## Agreed decision
- **Region `us-central1`.** Project `icon-events-513902`.
- **Cloud Run service `iconvenue`:**
  - prod image (`php:8.3-apache`, port 80), public, min 0 / max 2 instances;
  - Direct VPC egress on `default`;
  - bucket `icon-events-513902-uploads` mounted at `/var/www/html/storage/app/public`;
  - `APP_KEY` and `DB_PASSWORD` from Secret Manager;
  - logs to stderr;
  - service account `iconvenue-run`, which can only read its secrets and use the uploads bucket.
- **Cloud Run Jobs:** `iconvenue-migrate` and `iconvenue-artisan`, scheduled by Cloud Scheduler.
- **DB VM `iconvenue-db`:**
  - e2-micro, `us-central1-a`, Debian 12, 30 GB pd-standard, Shielded VM, OS Login, tag `db`;
  - MariaDB 10.11 from apt, bound to the internal IP, ~256 MB buffer pool, 1 GB swap;
  - nightly `mariadb-dump` → `gs://icon-events-513902-db-backups` (30-day lifecycle), plus daily snapshots kept 7 days;
  - service account `iconvenue-db-vm`, which can only write to the backups bucket.
- **Firewall:**
  - the default ssh/rdp/icmp rules are deleted;
  - IAP SSH is allowed (`35.235.240.0/20` → 22);
  - tcp:3306 is allowed from `10.128.0.0/20` (the us-central1 default subnet) to tag `db`.
- **Login rate limit:** `throttle:5,1` on `POST /login`, with a readable message on 429.
- **Remove the VM-app pieces:** `deploy/deploy.sh`, `docker-compose.prod.yml`, the provisioning for Docker and
  Tailscale, and the prod Apache MPM sizing.
- **Cost:** about $0–5/month (VM and disk on the free tier; Cloud Run likely within its free tier).

## BDD scenarios
- Visitor repeats failed logins to get temporarily locked out (`tests/Browser/features/auth/`)
- Exempt (infra/config/tooling): the Cloud Run, Jobs, Scheduler, DB VM, Dockerfile and deploy script changes, and the
  researcher agent with its docs-workflow change. There's no user-visible flow change beyond the login limit.
  `users:create-admin --password-env` is an ops CLI covered by PHPUnit (`tests/Feature/Staff/CreateAdminUserTest.php`).

## Scope / Out of scope
- In scope: everything under "Agreed decision", the researcher agent and its first brief, and the updated architecture
  artifact.
- Out of scope:
  - a custom domain;
  - Resend mail (stays `MAIL_MAILER=log`);
  - moving to Southeast Asia;
  - importing `icon (3).sql`;
  - CI/CD from GitHub.

## Files affected
- **Added:**
  - `.claude/agents/researcher.md`, `docs/research/README.md`
  - `deploy/cloudrun-deploy.sh`, `deploy/cloudbuild.yaml`, `deploy/provision-db-vm.sh`, `.gcloudignore`
- **Changed:**
  - `deploy/backup-db.sh`, `deploy/env.production.example`
  - `docker/php/Dockerfile.prod`, `docker/php/apache.prod.conf`, `docker/php/entrypoint.prod.sh`
  - `app/Console/Commands/CreateAdminUser.php`, `tests/Feature/Staff/CreateAdminUserTest.php`
  - `routes/web.php`, the login view
  - `.claude/rules/docs-workflow.md`, `CLAUDE.md`
  - `docs/README.md`, `docs/deployment.md`, `docs/architecture.md`
- **Removed:** `deploy/deploy.sh`, `deploy/provision-vm.sh`, `docker-compose.prod.yml`.

## Open questions
- Resend sending domain (mail stays in the log until then).
- When to move to Southeast Asia (~$8–14/month) if US latency bothers staff.

## Deviations from plan
- **Researcher agent's first run.** A new agent type only registers when the next session starts, so the first brief
  ran as a general-purpose agent following `.claude/agents/researcher.md`.
- **Research-driven changes**, all approved with the brief:
  - `trustProxies(at: '*')`.
  - The `chown` skips the uploads mount.
  - Two fixed-arg scheduled jobs (`iconvenue-reminders`, `iconvenue-complete-expired`, `roles/run.invoker`) instead of
    one `iconvenue-artisan` job with overrides.
  - Private Google Access on `default/us-central1`.
  - A reserved static internal IP `iconvenue-db-ip` (10.128.0.2).
  - The MariaDB user is limited to `'iconvenue'@'10.128.%'`.
  - `post_max_size` 64M → 30M (Cloud Run's 32 MiB request cap).
  - Route, view and event caches built into the image, with only `config:cache` at start.
  - The DB wait is 150 s.
  - Snapshots are stored in-region and kept 7 days.
- **Apache MPM.** It was **resized, not dropped**: `MaxRequestWorkers 10`, matching `--concurrency 10`, with 1 GiB
  memory. Apache's default of 150 workers could run out of memory on one instance.
- **Login limiter.** A named limiter `throttle:login`, keyed by `lower(email)|ip`, that answers with a login-form error
  and `Retry-After`. The plan said a plain `throttle:5,1`, which keys by IP only and shows a bare 429 page.
- **BDD scenario actor.** "**Guest** repeats failed logins to get temporarily locked out": the actors are
  Guest/Staff/Admin (`.claude/rules/testing-bdd.md`), not "Visitor". The BDD agent added "Guest logs in with another
  email to stay unaffected by someone else's lockout".
- **Dusk cache store.** `docker-compose.yml` `dusk-app` uses `CACHE_STORE: file` instead of `array`. An array cache
  resets on every request, so no rate limit could ever trigger in E2E.
- **Env file.** `deploy/env.production.example` was replaced by the committed, non-secret `deploy/cloudrun.env.yaml`.
  `deploy/gcp-setup.sh` was added to script the one-time setup.
- **Secrets.** `APP_KEY` and the DB password were generated by `gcp-setup.sh` straight into Secret Manager.
- **Admin bootstrap.** It ran as a one-off job `iconvenue-create-admin` using secret `iconvenue-admin-bootstrap`. Both
  were deleted afterwards.
- **Role ids.** The `admin` role got id 2 (`staff` is 1). The app checks roles by name, so this is harmless.
