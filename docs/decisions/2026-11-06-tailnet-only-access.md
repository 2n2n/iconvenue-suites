# 2026-11-06 — Tailnet-only access: Tailscale gateway on the DB VM → Cloud Run (internal ingress)

**Status:** Implemented (live 2026-11-06 at https://iconvenue.tailf0e670.ts.net; run.app → 404)
**Partially supersedes:** [2026-11-06 — Production on Cloud Run + MariaDB on a free-tier VM](2026-11-06-production-cloud-run.md)
(the "public, no Tailscale" access part only; hosting, DB, jobs and backups are unchanged)

## Context
Production went live earlier the same day, **public** on `*.run.app`. The user now wants **"only registered devices can
access the system"**, for the **whole app, public pages included**. They linked Tailscale's Cloud Run guide
(https://tailscale.com/docs/install/cloud/cloudrun).

Research brief (approved): [Tailnet-only access via a gateway on the DB VM](../research/2026-11-06-tailnet-only-gateway.md).

## What we discussed
- **The linked guide.**
  - It runs `tailscaled` in userspace mode with a SOCKS5 proxy inside the container, which covers **outbound** traffic
    only (Cloud Run → tailnet). It doesn't stop anyone from reaching the app.
  - Using Tailscale inside Cloud Run for *inbound* access would need `tailscale serve` in the container, min = max = 1
    instance and always-on CPU, about **$40–50/month**.
  - Each restart would also produce a new node identity and a new HTTPS cert, and cert issuance is rate-limited.
  - → **Rejected.**
- **Gateway on the existing DB VM.** → **Chosen.**
  - Tailscale (kernel mode, persistent state) runs on `iconvenue-db`, and `tailscale serve` provides the stable ts.net
    HTTPS name and cert.
  - nginx on 127.0.0.1 forwards each request to the run.app URL, and Cloud Run ingress becomes `internal`.
  - Requests from a VM in the same project's VPC count as internal even when the VM has an external IP.
  - Cloud Run keeps scaling to zero.
  - Cost: the VM needs an external IP to reach Tailscale's control plane, +~$3.65/month. That also fixes OS patching on
    the VM.
- **Scope.** The whole app or only `/login` + `/admin`? → **Whole app** (user's choice).
  - Clients can no longer browse availability on the web. That's a business-rule change, recorded in
    `docs/BUSINESS_RULES.md`.
- **Proxy: nginx or Caddy.** → **nginx** (Debian main, small). Debian's Caddy predates v2.11, so it would need its own
  apt repo and `trusted_proxies`.
- **Internet route for the VM: external IP or Cloud NAT.** → **External IP**, the cheaper one. It also allows direct
  WireGuard connections.
- **Rejected: external ALB + IAP.** It costs well above budget and identifies Google accounts, not registered devices.
- **Host the app sees.** Laravel builds intended-redirect URLs from the request host, so the request host must be the
  ts.net name. `URL::forceRootUrl` alone isn't enough.
  - → Apache in the image sets `X-Forwarded-Host` from `APP_PUBLIC_HOST`, and Laravel's `trustProxies('*')` trusts it.
- **Email assets.** Logo URLs would point at ts.net, which clients can't load. → Embed the logo inline (CID).
- **Tailscale plan.** Personal is free (6 users, unlimited devices) but its terms say non-commercial, and Standard is
  $8/user/month. → **The user chose Personal**, knowing the terms. Switching later changes nothing technical.
- **Tailscale node login.** → The interactive login URL with `--advertise-tags=tag:iconvenue-prod`, so no auth key
  passes through Claude or a file.
- **Who gets access: an explicit group or all members.** An explicit `group:iconvenue-staff` means listing every login
  by hand; groups that sync from an identity provider need a paid plan.
  - → **`autogroup:member`** (user's choice). Access follows invite + user approval + device approval.
  - Trade-off: anyone invited to the tailnet gets the website. Lending a device uses **Share**, not an invite.
- **Architecture page.** The user asked for it to be updated during planning, so the proposed diagram was published
  before any code.

## Agreed decision
- **Access path:** registered tailnet device → `https://iconvenue.<tailnet>.ts.net` (`tailscale serve` on
  `iconvenue-db`) → nginx `127.0.0.1:8080` (HTTPS to run.app, SNI on, verify on, re-resolved through
  `169.254.169.254`) → Cloud Run `iconvenue` with `--ingress internal`.
  - `--allow-unauthenticated` and the default URL stay.
- **Tailscale policy:** grants only, replacing the default allow-all.
  - `autogroup:member` → `tag:iconvenue-prod:tcp:443`. Every invited, approved user of the business tailnet is staff,
    so there's no email list to maintain.
  - `autogroup:admin` → `tcp:22`, plus a Tailscale SSH check rule.
  - `autogroup:member` → `autogroup:self` keeps each person's own devices connected.
  - Tag owner: `autogroup:admin`.
  - MagicDNS, HTTPS, **device approval** and user approval are turned on.
  - The full policy is in [the Tailscale guide](../tailscale-guide.md).
- **GCP:**
  - static external IP `iconvenue-db-public` on `iconvenue-db`;
  - firewall `allow-tailscale-direct` (udp:41641 → tag `db`);
  - IAP SSH kept as break-glass access.
- **App and image:**
  - `APP_URL` and `APP_PUBLIC_HOST` set to the ts.net host;
  - Apache sets `X-Forwarded-Host`;
  - the email logo is embedded;
  - the login limiter is unchanged (effectively per email behind the gateway).
- **Deploy:**
  - `--ingress internal` and a startup probe on `/up`;
  - after the deploy, a check that run.app returns 404 from outside, and a ts.net `/up` check when Tailscale is on the
    machine.
- **Cost:** ~$4–9/month on Google Cloud. Tailscale Personal is $0.

## BDD scenarios
- Exempt (infra/config): network ingress, the Tailscale/nginx gateway, and the `APP_URL`/`X-Forwarded-Host` config.
  No route or controller flow changes. Dusk runs without a tailnet, so it can't exercise this path.
- The email logo embed is covered by PHPUnit (Dusk can't open emails): *Staff creates a booking to send the client an
  email with the embedded logo*.

## Scope / Out of scope
- In: everything under Agreed decision, the docs, and the architecture page.
- Out:
  - a custom domain;
  - Resend mail;
  - keying the limiter on `Tailscale-User-Login`;
  - Tailscale Standard;
  - removing IAP SSH.

## Files affected
- **Added:** `deploy/provision-gateway.sh`, `docs/research/2026-11-06-tailnet-only-gateway.md`, a mail test.
- **Changed:**
  - `deploy/gcp-setup.sh`, `deploy/cloudrun-deploy.sh`, `deploy/cloudrun.env.yaml`
  - `docker/php/apache.prod.conf`
  - `resources/views/emails/*.blade.php`, `app/Providers/AppServiceProvider.php` (comment)
  - `docs/deployment.md`, `docs/architecture.md`, `docs/BUSINESS_RULES.md`, `docs/README.md`
  - the architecture artifact

## Open questions
- Tailscale plan terms (Personal, non-commercial) are accepted by the user. Revisit if staff grows beyond 6 users.

## Deviations from plan
- **Access policy.** It uses `autogroup:member` instead of an explicit `group:iconvenue-staff` (user's choice, recorded
  above). Device approval and user approval are on.
- **Public host header.** It's written by `docker/php/entrypoint.prod.sh` into
  `/etc/apache2/conf-enabled/zz-public-host.conf`, and only when `APP_PUBLIC_HOST` is a valid hostname. The plan put it
  in `apache.prod.conf`, but a literal `${APP_PUBLIC_HOST}` would break when the variable is unset (local smoke tests,
  public mode).
- **Mode switch.** Tailnet-only mode is switched by the `APP_PUBLIC_HOST` line in `deploy/cloudrun.env.yaml`. Removing
  it goes back to public (`--ingress all`).
- **Email test.** It builds in-memory models: `Booking` has no `HasFactory` and its factory is out of date.
- **Deploy health check.** It finds the macOS Tailscale CLI at `/Applications/Tailscale.app/Contents/MacOS/Tailscale`.
- **Verification.** From a tailnet device and from the gateway itself (`curl --resolve`):
  - `/up`, `/`, `/venues` and `/login` return 200;
  - the login form posts to the ts.net host;
  - guest `/admin/bookings` → 302 to `https://iconvenue.tailf0e670.ts.net/login`;
  - Let's Encrypt cert valid until 2027-02-04;
  - run.app → 404 from the internet;
  - the `iconvenue-reminders` manual run succeeded.
  - VM memory: MariaDB 137 MB, tailscaled 43 MB, nginx about 30 MB, 443 MB available.
- **Still open: Tailscale SSH.** The server reports "access controls don't allow anyone", so the policy's `ssh` rule
  isn't taking effect yet. IAP SSH remains the access path.
- **Setup note.** The user's Mac first "couldn't connect" because the freshly installed app was logged out. That's now
  in the guide and the runbook troubleshooting.
