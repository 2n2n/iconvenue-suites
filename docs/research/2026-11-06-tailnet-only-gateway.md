# Research: Tailnet-only access to the Cloud Run app, with the DB VM as a Tailscale gateway
**Date:** 2026-11-06 · **Asked for:** [2026-11-06 — Tailnet-only access](../decisions/2026-11-06-tailnet-only-access.md) ·
**Status:** approved by the user with the plan on 2026-11-06

## Question
Can the Cloud Run service `iconvenue` be reachable **only** from registered tailnet devices, public pages included, as
cheaply as possible? The path: tailnet device → `https://<host>.<tailnet>.ts.net` (`tailscale serve` on the VM
`iconvenue-db`) → `*.run.app` with ingress `internal`.

The user also asked about Tailscale's Cloud Run guide (https://tailscale.com/docs/install/cloud/cloudrun). That guide
runs `tailscaled --tun=userspace-networking --socks5-server=localhost:1055` and sets `ALL_PROXY` for the app, which
covers **outbound** traffic only (Cloud Run → tailnet). It doesn't restrict who can reach the app.

**Verdict:** it works, with five corrections:
1. The VM needs an external IP.
2. A local proxy (nginx) is required, because serve only proxies to 127.0.0.1.
3. The app must see the ts.net host.
4. Tailscale Personal is non-commercial.
5. The deploy health check must move off the public URL.

## Findings
1. **What counts as internal.** Requests from "VPC networks that are in the same project" are **internal even if the
   VM has an external IP**. "For requests from Compute Engine VM instances, no further setup is required for machines
   that have external IP addresses or that use Cloud NAT." Scheduler, Tasks, Pub/Sub and Eventarc using the run.app
   URL also count as internal. ([ingress][ing]) High.
2. **A VM without an external IP** needs PGA (already on), PSC or an internal ALB. ([private networking][priv]) High.
3. **IAM still applies on every ingress path.** Keep `--allow-unauthenticated`; the network is the gate. ([ingress][ing]) High.
4. **Don't disable the default run.app URL.** Scheduler and the like stop working, and the gateway uses that URL.
   ([ingress][ing]) High.
5. **Scheduler → Jobs** call the Admin API (`run.googleapis.com/v2/...:run`), not the service URL, so they're unaffected.
   Inference, Medium. Verified with a manual run after the switch.
6. **`tailscale serve` reverse-proxies to `http://127.0.0.1` only.** `--bg` persists across reboots. ([serve][serve],
   validated 2026-01-26) High.
7. **Serve's forwarding headers.**
   - It keeps the ts.net Host and sets `X-Forwarded-Host`/`-Proto: https`/`-For` (the tailnet client).
   - It adds `Tailscale-User-Login`/`-Name`/`-Profile-Pic` and strips client-supplied copies. These aren't sent for
     tagged source devices.
   - ([serve.go][servego] Medium, [serve headers][sheaders] High)
8. **nginx defaults.** `proxy_ssl_server_name` is **off** and `proxy_ssl_verify` is off. The default Host is
   `$proxy_host`. A variable in `proxy_pass` plus `resolver` re-resolves DNS. → Turn SNI and verification on, and use
   `resolver 169.254.169.254`. ([nginx proxy][nginx]) High.
9. **Caddy alternative.** It sets the upstream Host automatically since v2.11, but needs `trusted_proxies` or it
   overwrites serve's `X-Forwarded-*`. Debian 12's package is older. ([caddy][caddy]) High. → nginx (Debian main,
   small) was chosen.
10. **Laravel 12 `trustProxies('*')`** trusts only REMOTE_ADDR, using the X-Forwarded-For/Host/Port/Proto/Prefix
    headers. ([TrustProxies][ltp]) High.
    - Whether Cloud Run's front end passes `X-Forwarded-Host` through isn't documented ([contract][contract]). One
      blog says it does ([blog][blog], Low).
    - → The image sets `X-Forwarded-Host` itself in Apache, so it arrives from REMOTE_ADDR and is trusted.
11. **`URL::forceRootUrl` alone isn't enough.** `url()->full()`, `redirect()->guest()` (intended URL) and `previous()`
    use the request host, so login would bounce to run.app. The request host itself has to be the ts.net host.
    ([UrlGenerator][urlgen], [Redirector][redir]) High.
12. **Session cookie.** `SESSION_DOMAIN` is unset, so the cookie is host-only and bound to the ts.net host by the
    browser. No change is needed. Inference, Medium.
13. **Login limiter.** All requests come through the gateway, so `ip()` is effectively one address and the limit
    becomes per email, which matches its intent. `Tailscale-User-Login` could be a better key, but whether it survives
    the front end is untested. Low.
14. **HTTPS certs.** They need MagicDNS + HTTPS enabled and are Let's Encrypt, 90 days. Serve renews them, and **machine
    names appear in public CT logs**. ([enabling HTTPS][https], validated 2025-12-10) High.
15. **Plans.** Personal is free: up to 6 users, unlimited user devices, 50 tagged resources, and **"only suitable for
    non-commercial use"**. Standard is $8/user/month. ([pricing][tsprice]) High.
    - → **The user chose Personal** (2026-11-06).
16. **Tagged server login.** Tagged devices have key expiry off by default. ([auth keys][authkeys], validated
    2026-06-30) High.
    - → We use the interactive login URL with `--advertise-tags`, so no auth key is handled.
17. **Policy.** Grants (`src`/`dst`/`ip`) are deny-by-default, but the **default policy file allows all**, so it must
    be replaced. ([grants][grants], [policy syntax][psyntax]) High/Medium.
18. **Tailscale SSH.** Enabled with `--ssh`. It needs a grant plus an `ssh` rule, and a tailscaled restart drops
    sessions. Keep IAP as break-glass access. ([tailscale ssh][tssh]) High.
19. **Ports.** Outbound TCP 443 and UDP 3478/41641. Allowing inbound UDP 41641 helps direct connections; otherwise
    traffic goes through DERP relays. Install from the `pkgs.tailscale.com` bookworm repo. ([firewall ports][ports],
    [packages][pkgs]) High.
20. **Cost.**
    - External IPv4 on a VM: $0.005/h, about $3.65/month. ([network pricing][netprice]) Medium.
    - Free tier: no mention of external IPs. ([free tier][free]) High.
    - Cloud NAT is about $4–5/month or more. Low.
21. **Health checks.** `--startup-probe httpGet.path=/up,...` replaces curling run.app from the laptop, which would fail
    under internal ingress. ([health checks][hc]) High.

## Risks and gotchas
- **Single gateway.** The VM is now in the request path: a tailscaled or nginx restart or a VM reboot takes the whole
  app offline.
- **Email links and images** pointing at ts.net are unreachable for clients. → The logo is embedded inline (CID).
- **Clients lose the public site.** This is a business-rule change, recorded in `docs/BUSINESS_RULES.md`.
- **nginx settings.** Without `proxy_ssl_server_name on`, the TLS handshake to Google fails. Without a variable plus
  `resolver`, the IP is resolved only once.
- **Exposure.** The VM gains a public IP. Firewall ingress stays IAP:22 + subnet:3306 (+ optional udp:41641).
- **Public hostname.** The machine name shows in CT logs, so pick a neutral hostname.

## Open questions
- Does `Tailscale-User-Login` survive Cloud Run's front end? Untested; only matters for limiter keying.
- External IPv6-only for the VM (possibly cheaper) wasn't researched.

[ing]: https://docs.cloud.google.com/run/docs/securing/ingress
[priv]: https://docs.cloud.google.com/run/docs/securing/private-networking
[contract]: https://docs.cloud.google.com/run/docs/container-contract
[hc]: https://docs.cloud.google.com/run/docs/configuring/healthchecks
[free]: https://docs.cloud.google.com/free/docs/free-cloud-features
[netprice]: https://cloud.google.com/vpc/network-pricing
[serve]: https://tailscale.com/kb/1242/tailscale-serve
[sheaders]: https://tailscale.com/s/serve-headers
[servego]: https://raw.githubusercontent.com/tailscale/tailscale/main/ipn/ipnlocal/serve.go
[tsprice]: https://tailscale.com/pricing
[authkeys]: https://tailscale.com/kb/1085/auth-keys
[grants]: https://tailscale.com/kb/1324/grants
[psyntax]: https://tailscale.com/kb/1337/policy-syntax
[https]: https://tailscale.com/kb/1153/enabling-https
[tssh]: https://tailscale.com/kb/1193/tailscale-ssh
[ports]: https://tailscale.com/kb/1082/firewall-ports
[pkgs]: https://pkgs.tailscale.com/stable/#debian-bookworm
[caddy]: https://caddyserver.com/docs/caddyfile/directives/reverse_proxy
[nginx]: https://nginx.org/en/docs/http/ngx_http_proxy_module.html
[ltp]: https://raw.githubusercontent.com/laravel/framework/12.x/src/Illuminate/Http/Middleware/TrustProxies.php
[urlgen]: https://raw.githubusercontent.com/laravel/framework/12.x/src/Illuminate/Routing/UrlGenerator.php
[redir]: https://raw.githubusercontent.com/laravel/framework/12.x/src/Illuminate/Routing/Redirector.php
[blog]: https://sharats.me/posts/tale-of-two-forwarded-headers/
