# Deployment — production on GCP (Cloud Run + MariaDB on a free-tier VM, tailnet-only)

Living runbook. Why it's built this way:
- hosting: [Cloud Run decision](decisions/2026-11-06-production-cloud-run.md) and its
  [research](research/2026-11-06-cloud-run-mariadb-free-tier.md);
- access: [tailnet-only decision](decisions/2026-11-06-tailnet-only-access.md) and its
  [research](research/2026-11-06-tailnet-only-gateway.md).

Registering devices and inviting staff: [tailscale-guide.md](tailscale-guide.md).

## Topology
| What | Value |
|---|---|
| GCP project | `icon-events-513902` (always pass `--project icon-events-513902`; the local gcloud default is a different project) |
| Region | `us-central1` (Always Free e2-micro is US-only) |
| App URL | **https://iconvenue.tailf0e670.ts.net**: **tailnet only** (registered devices). The run.app URL `https://iconvenue-1091789543033.us-central1.run.app` returns **404** from the internet (`--ingress internal`) |
| Access path | tailnet device → `tailscale serve` :443 on `iconvenue-db` (Let's Encrypt cert for the ts.net name, auto-renewed) → nginx `127.0.0.1:8080` → HTTPS to run.app (SNI + Host = run.app) → Cloud Run. Same-project VPC traffic counts as internal |
| Tailnet node | `iconvenue` (100.67.168.23), `tag:iconvenue-prod`, tailnet `tailf0e670.ts.net` (Personal plan). Policy: `autogroup:member` → tcp:443, `autogroup:admin` → tcp:22 (see the guide) |
| Cloud Run service | `iconvenue`: `php:8.3-apache` image, port 80, 1 vCPU / 1 GiB, concurrency 10, 0–2 instances, gen2, SA `iconvenue-run` |
| Uploads | `gs://icon-events-513902-uploads`, mounted at `/var/www/html/storage/app/public` (`uid=33;gid=33`) |
| Database | VM `iconvenue-db` (e2-micro, `us-central1-a`, 30 GB pd-standard), MariaDB 10.11 on **`10.128.0.2:3306`** (reserved `iconvenue-db-ip`). Also the **tailnet gateway**. Static external IP `iconvenue-db-public` (35.238.99.208) for Tailscale/apt; inbound firewall stays IAP:22, subnet:3306, udp:41641 only |
| DB access | Firewall `allow-mariadb-from-subnet` (tcp:3306 from `10.128.0.0/20` → tag `db`), MariaDB user `'iconvenue'@'10.128.%'`. Cloud Run reaches it over Direct VPC egress |
| Secrets | Secret Manager: `iconvenue-app-key`, `iconvenue-db-password` |
| Jobs | `iconvenue-migrate` (each deploy), `iconvenue-reminders` (09:00 Manila), `iconvenue-complete-expired` (every 15 min), via Cloud Scheduler as SA `iconvenue-scheduler` |
| Images | Artifact Registry `us-central1-docker.pkg.dev/icon-events-513902/iconvenue/app` (keeps the newest 3) |
| Backups | nightly 02:00 Manila **and before every deploy/rollback**: `mariadb-dump` → `gs://icon-events-513902-db-backups` (30 days), 7 days locally, daily disk snapshot (7 days). Deploy records in `deploys/` |
| SSH | IAP (`35.235.240.0/20` → tcp:22): `gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap`. Tailscale SSH is enabled (`--ssh`) but the tailnet's ssh rule still needs checking (server reports no one allowed), so IAP is the working path |

**Never in production:** `db:seed`, Dusk, `migrate:fresh` or `RefreshDatabase`. The prod entrypoint refuses to start
unless `APP_ENV=production`, and it never seeds.

## Files
| File | Purpose |
|---|---|
| `deploy/gcp-setup.sh` | One-time GCP resources (APIs, firewall, PGA, static IP, SAs, buckets, secrets, registry, snapshots, DB VM). Re-runnable |
| `deploy/provision-db-vm.sh` | One-time MariaDB setup on the DB VM (needs the temporary external IP for apt) |
| `deploy/backup-db.sh` | Installed on the DB VM as `/opt/iconvenue-db/backup-db.sh` |
| `deploy/provision-gateway.sh` | Tailnet gateway on the DB VM: Tailscale (bookworm repo) + nginx + `tailscale serve`. Re-runnable |
| `deploy/cloudrun-deploy.sh` | Build + migrate + deploy service + scheduled jobs + health check. `APP_PUBLIC_HOST` in `cloudrun.env.yaml` switches on tailnet-only mode (`--ingress internal`) |
| `deploy/cloudbuild.yaml`, `.gcloudignore` | Cloud Build config; what gets uploaded (never `.env` or SQL dumps) |
| `deploy/cloudrun.env.yaml` | Non-secret env for the service and jobs (committed) |
| `docker/php/Dockerfile.prod`, `entrypoint.prod.sh`, `apache.prod.conf`, `php.prod.ini` | Production image |

## First-time setup (done 2026-11-06)
1. `deploy/gcp-setup.sh`
2. Provision the DB VM (it still has its temporary external IP):
   ```bash
   G="--zone us-central1-a --project icon-events-513902 --tunnel-through-iap"
   gcloud compute scp deploy/backup-db.sh iconvenue-db:/tmp/backup-db.sh $G
   gcloud compute ssh iconvenue-db $G --command 'sudo bash -s' < deploy/provision-db-vm.sh
   ```
3. Remove the external IP: `gcloud compute instances delete-access-config iconvenue-db --zone us-central1-a --project icon-events-513902 --access-config-name external-nat`
4. `deploy/cloudrun-deploy.sh`
5. Create the first admin (the password never touches a file or chat):
   ```zsh
   # you, in your terminal:
   read -rs "PW?New admin password (min 12 chars): " && echo && printf '%s' "$PW" | \
     gcloud secrets create iconvenue-admin-bootstrap --data-file=- --replication-policy automatic --project icon-events-513902; unset PW
   ```
   Then grant `iconvenue-run` `secretAccessor` on it, deploy a one-off job `iconvenue-create-admin` (same flags as the
   jobs in `cloudrun-deploy.sh`, plus `ADMIN_BOOTSTRAP_PASSWORD=iconvenue-admin-bootstrap:1` in `--set-secrets`) with
   args `php,artisan,users:create-admin,<email>,--name=Admin,--password-env=ADMIN_BOOTSTRAP_PASSWORD`, execute it with
   `--wait`, and **delete the job and the secret**. This creates the `admin`/`staff` roles too.
6. **Tailnet-only access** (done 2026-11-06, in this order so the app never goes dark):
   1. Tailscale admin console: MagicDNS + HTTPS Certificates, Device approval, and the policy from
      [tailscale-guide.md](tailscale-guide.md) §1.
   2. `deploy/gcp-setup.sh` again. It attaches the static external IP `iconvenue-db-public` and adds the
      `allow-tailscale-direct` rule.
   3. Run the gateway setup:
      ```bash
      gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap \
        --command 'sudo bash -s' < deploy/provision-gateway.sh
      ```
      Open the `https://login.tailscale.com/a/…` link it prints and approve as the admin. It then prints the ts.net URL.
   4. Check from a registered device that `https://iconvenue.tailf0e670.ts.net/up` returns 200. run.app is still
      public at this point.
   5. Set `APP_PUBLIC_HOST: "iconvenue.tailf0e670.ts.net"` in `deploy/cloudrun.env.yaml` and run
      `deploy/cloudrun-deploy.sh`. It deploys with `--ingress internal` and fails if run.app still answers 200 from
      the internet.

   **Going back to public:** delete the `APP_PUBLIC_HOST` line and redeploy (ingress becomes `all`).

## Go-live checklist
1. Sign in at `/login` as `innoendo.itsolutions@gmail.com`. ✔ account created 2026-11-06.
2. **Admin → Settings: save the contact details first.** The public Contact page returns 500 until a `contact_settings`
   row exists.
3. Create staff accounts (Admin → Staff), then venues, suites, packages and add-ons.
4. Upload one image, run `deploy/cloudrun-deploy.sh` again, and check the image still shows (bucket mount).
5. Mail: once Resend is set up (below), create a booking for yourself and check the email arrives.
6. Test a restore into a scratch DB (below). ✔ first backup uploaded 2026-11-06.

## Routine deploy
```bash
deploy/cloudrun-deploy.sh     # from the repo root; builds what's in your working tree. Confirm before running.
```
Steps, in order ([decision](decisions/2026-11-17-predeploy-backup-and-rollback.md)):
1. **Current production.** Notes the revision serving traffic and its image.
2. **Pre-deploy backup.** It pipes `deploy/backup-db.sh` to the VM over IAP SSH, which uploads
   `iconvenue-predeploy-<tag>-<ts>.sql.gz` to the backup bucket. It also reads the applied migration count. **If either
   step fails, nothing is deployed.**
3. **Build** with Cloud Build.
4. **Migrations.** Runs `iconvenue-migrate` (`migrate --force`), waits, and prints how many new migrations ran, or
   "none to apply".
5. **Service.** A startup probe on `/up` must pass before the new revision takes traffic. Traffic is then set
   `--to-latest`, which also un-pins it after a rollback.
6. **Scheduled jobs.** Updated to the new image.
7. **Deploy record.** Written to `gs://icon-events-513902-db-backups/deploys/<tag>.json`:
   - the previous revision and image;
   - the backup object;
   - the migration counts before and after, and `migrated`;
   - the status.

   If a step after the backup fails, the record is still written with `status: failed-at-<step>`.
8. **Health check.** run.app must return **404** from the internet. If this machine is on the tailnet, ts.net `/up`
   must return 200.

Tags are `<timestamp>-<git sha>[-dirty]`.

## Rollback
Use the **`/rollback-prod`** skill (`.claude/skills/rollback-prod/scripts/rollback-prod.sh`).
- **Dry run.** `rollback-prod.sh [<tag>]` prints the plan for the newest deploy (or `<tag>`) and changes nothing.
- **Execute.** `--yes` runs these steps:
  1. safety backup `prerollback-<tag>`;
  2. traffic to the previous revision;
  3. `iconvenue-migrate`, `iconvenue-reminders` and `iconvenue-complete-expired` re-pointed to the previous image;
  4. **DB restore of the pre-deploy backup, only if that deploy applied migrations** (or with `--restore-db`). Data
     written since the deploy is lost;
  5. `/up` check, from this Mac over the tailnet, or from the VM.
- **How the restore works.**
  1. It downloads the dump on your machine and checks it.
  2. It streams the dump to the VM, which verifies it in a temp file.
  3. Only then does the VM drop and recreate `iconvenue` (utf8mb4_unicode_ci), load the dump, and check the table
     count and the migration count.

  The VM's service account can only *create* backup objects, not read them, so downloads go through your gcloud login.
- **Test a restore without touching production:** `rollback-prod.sh [<tag>] --test-restore restore_check`.
- **Limits:**
  - records and backups are deleted after 30 days;
  - Artifact Registry keeps only the 3 newest images;
  - the app shows errors for the seconds the restore takes;
  - uploads (images) aren't rolled back.
- **Manual equivalent:**
  1. `gcloud run services update-traffic iconvenue --region us-central1 --project icon-events-513902 --to-revisions <rev>=100`.
     List revisions with `gcloud run revisions list --service iconvenue --region us-central1 --project icon-events-513902`.
  2. Restore the dump as in "Backups and restore".

## Mail (Resend)
1. In Resend, verify a **sending domain** and create an API key.
2. Store it: `printf '%s' 're_...' | gcloud secrets create iconvenue-mail-password --data-file=- --project icon-events-513902`,
   grant `iconvenue-run` `secretAccessor`, add `MAIL_PASSWORD=iconvenue-mail-password:latest` to `--set-secrets` in
   `cloudrun-deploy.sh`, and set `MAIL_MAILER: "smtp"` and `MAIL_FROM_ADDRESS` in `deploy/cloudrun.env.yaml`.
3. `deploy/cloudrun-deploy.sh`.

Until then mail stays `MAIL_MAILER=log` (Cloud Logging). Mail failures never break a booking (`try/catch` + log).

## DB VM maintenance
Since the tailnet switch, the VM has a static external IP, so `apt` works directly:
```bash
gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap \
  --command 'sudo apt-get update && sudo apt-get -y upgrade'
```
This also upgrades Tailscale (its repo is installed) and nginx.
- Restarting tailscaled or nginx, or rebooting the VM, takes the **whole app** offline for those seconds: the VM is the
  only way in.
- Patch in quiet hours.

## Tailnet gateway (operations)
- **Status on the VM:** `sudo tailscale status`, `sudo tailscale serve status` (expect
  `https://iconvenue.tailf0e670.ts.net (tailnet only) |-- / proxy http://127.0.0.1:8080`), and `systemctl status nginx`.
- **End-to-end check from the VM itself.** This works even when your own device isn't on the tailnet:
  ```bash
  H=iconvenue.tailf0e670.ts.net; IP=$(tailscale ip -4)
  curl -s -o /dev/null -w '%{http_code}\n' --resolve $H:443:$IP https://$H/up                 # 200
  curl -s --resolve $H:443:$IP https://$H/login | grep -o 'action="[^"]*"'               # ts.net host, never run.app
  ```
- **Proxy only** (nginx → Cloud Run): `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/up` should
  return 200.
- **Certificate:** Let's Encrypt for the ts.net name, renewed by tailscaled. Check it with
  `echo | openssl s_client -connect $(tailscale ip -4):443 -servername iconvenue.tailf0e670.ts.net | openssl x509 -noout -enddate`.
- **Re-register the node** (rebuilt VM, or the node was removed): remove `iconvenue` on the Machines page, then re-run
  `provision-gateway.sh` and approve the printed link. If the tailnet name changes, update `APP_PUBLIC_HOST` and redeploy.
- **How the app knows its address.** The prod entrypoint writes
  `RequestHeader set X-Forwarded-Host "$APP_PUBLIC_HOST"` into Apache, and Laravel's `trustProxies('*')` trusts it.
  Links, form actions and post-login redirects therefore use the ts.net host, even though Cloud Run receives
  Host = run.app.

### What we hit during setup (2026-11-06)
| Symptom | Cause | Fix |
|---|---|---|
| Mac: "can't connect" after installing Tailscale | The app was installed but **logged out** (`Tailscale status` → "Logged out") | Menu bar icon → Log in with the admin account. Allow the VPN configuration / network extension in macOS. Approve the Mac on the Machines page |
| `curl: (6) Could not resolve host: iconvenue.tailf0e670.ts.net` | Tailscale is **stopped** on that machine (MagicDNS only resolves while connected) | Turn Tailscale on, or test from the VM with `--resolve` (above) |
| run.app still public after the gateway worked | Expected until the switch deploy | Set `APP_PUBLIC_HOST` and redeploy (First-time setup §6.5) |
| VM health: "Tailscale SSH enabled, but access controls don't allow anyone" | The tailnet policy's `ssh` rule for `tag:iconvenue-prod` isn't taking effect | Open: re-check the `ssh` block in the policy. IAP SSH works meanwhile |

## Backups and restore
- **Where:**
  - `/var/backups/iconvenue/*.sql.gz` on the VM (7 days);
  - `gs://icon-events-513902-db-backups` (30 days, lifecycle rule);
  - disk snapshots (7 days, `us-central1`).
- **Names:**
  - nightly `iconvenue-<ts>.sql.gz`;
  - before each deploy `iconvenue-predeploy-<tag>-<ts>.sql.gz`;
  - before each rollback `iconvenue-prerollback-<tag>-<ts>.sql.gz`.
  - Deploy records live in `deploys/<tag>.json` in the same bucket.
- **Log:** `/var/log/iconvenue-backup.log`.
- **On demand:** `sudo /opt/iconvenue-db/backup-db.sh [label]`. Deploys and rollbacks pipe the repo copy instead, so
  the installed copy is used only by the nightly cron.
- **Restore a dump** (overwrites production; take a fresh backup first). On the VM:
  ```bash
  gcloud storage cp gs://icon-events-513902-db-backups/iconvenue-YYYYMMDD-HHMMSS.sql.gz /tmp/
  gunzip -c /tmp/iconvenue-YYYYMMDD-HHMMSS.sql.gz | sudo mariadb iconvenue
  ```
- **Test restore** (doesn't touch production):
  ```bash
  sudo mariadb -e "CREATE DATABASE restore_check"
  gunzip -c /tmp/<dump>.sql.gz | sudo mariadb restore_check
  sudo mariadb -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='restore_check'; DROP DATABASE restore_check"
  ```
- Uploaded images live in the uploads bucket (no versioning yet).

## Operations
- **Logs:** `gcloud run services logs read iconvenue --region us-central1 --project icon-events-513902`, or Cloud
  Logging in the console. Jobs: `gcloud logging read 'resource.type="cloud_run_job"' --project icon-events-513902 --limit 50`.
- **Artisan one-off:** deploy a job like `iconvenue-create-admin` above with the command in `--args`, execute with `--wait`, delete it.
- **Database shell:** `gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap --command 'sudo mariadb iconvenue'`
- **Run a scheduled job now:** `gcloud scheduler jobs run iconvenue-reminders --location us-central1 --project icon-events-513902`
- **Rotate secrets:** add a new version (`gcloud secrets versions add ... --data-file=-`), then redeploy. Don't rotate
  `APP_KEY` unless you must (it logs everyone out). For the DB password, change it in MariaDB first
  (`ALTER USER 'iconvenue'@'10.128.%' IDENTIFIED BY ...`), then add the secret version and redeploy.

## Cost (estimate, 2026-11)
About **$4–9/month**, all Google Cloud. Tailscale Personal is free (up to 6 users; its terms say non-commercial, which
the user accepted).
- **Always Free:** the e2-micro and its 30 GB pd-standard disk.
- **The VM's static external IP:** about **$3.65**, needed by Tailscale.
- **Probably inside their free tiers at this traffic:** Cloud Run, Cloud Build, Scheduler (2 jobs), Secret Manager and
  Storage.
- **Cents:** snapshots, the reserved internal IP, and Artifact Registry above 0.5 GB.

Check the billing report after the first week. Page responses now leave Google from the VM, so they count against
Compute Engine's 1 GB/month of free egress.

**Moving to Southeast Asia later** (lower latency for PH users) leaves the free tier: about $8–11/month for a MariaDB VM
in `asia-southeast1`, or $11–14/month for Cloud SQL MySQL.
