# Research: Laravel 12 on Cloud Run (us-central1) + self-managed MariaDB 10.11 on an Always Free e2-micro
**Date:** 2026-11-06 · **Asked for:** checking the approved design in
[2026-11-06 — Production on Cloud Run + MariaDB on a free-tier VM](../decisions/2026-11-06-production-cloud-run.md)
before building it · **Status:** approved by the user on 2026-11-06

Prices and limits are as of 2026-11-06. Google's `*/pricing` pages render client-side, so where a page couldn't be
read in full, the figure comes from search excerpts of the official page and is marked Medium.

## Question
Can the design run for about $0–5/month? The design: a Laravel 12 app in a PHP 8.3 Apache image, public on `*.run.app`,
no Tailscale; uploads in a GCS volume mount; MariaDB 10.11 on a free e2-micro reached over Direct VPC egress; Scheduler
driving Jobs; secrets in Secret Manager.

## Changes the research made to the design (all applied)
1. **Login limiter keyed on one shared IP.** Without `trustProxies(at: '*')`, `request()->ip()` is Cloud Run's internal
   peer, so every visitor shares the IP half of the `email|ip` key. → `bootstrap/app.php`. (Finding 11)
2. **Recursive `chown` over the bucket.** `chown -R storage` in the entrypoint would walk the GCS mount, where ownership
   can't change anyway. → The chown skips the mount, and the mount uses `uid=33;gid=33`. (Finding 4)
3. **Scheduler permissions.** Running one job with overridden args needs `roles/run.jobsExecutorWithOverrides`, which
   is broader. → Two jobs with fixed args, each granting only `roles/run.invoker`. (Finding 5)
4. **DB VM network access.**
   - Once the external IP is gone, the VM needs **Private Google Access** to upload backups.
   - It has no apt access without a temporary external IP or Cloud NAT. (Finding 3)
5. **DB address.** An ephemeral internal IP can be released, so the DB VM gets a **reserved static internal IP**
   (`DB_HOST` is fixed at deploy time). (Finding 3)
6. **Instance sizing.**
   - Apache's prefork worker cap matches Cloud Run concurrency (10), with 1 GiB of memory.
   - `post_max_size` is under Cloud Run's 32 MiB HTTP/1 request limit. (Finding 7)
7. **Cold starts.** Route, view and event caches are built into the image, and only `config:cache` runs at start,
   since it holds the secrets. The DB wait is longer because Direct VPC can take a minute to connect. (Findings 2, 3)

## Findings
### 1. Always Free e2-micro
- **Regions.** One non-preemptible e2-micro per month, in **us-west1, us-central1 or us-east1 only**. Usage is combined
  across regions per billing account. ([free tier][free]) High.
- **Disk.** 30 GB-months of **standard** persistent disk. It must be `pd-standard`; `pd-balanced` is billed.
  ([free tier][free]) High. Whether the disk allowance is region-restricted is inferred: Medium.
- **Snapshots are not free.** Regional snapshots cost about $0.05/GB-month; multi-region costs more. → Snapshots are
  stored in `us-central1`, and a few GB comes to cents. ([announcement][snap]) Medium.
- **External IPv4.** $0.005/hr (about $3.65/month) since 2024-02-01. The free-tier page doesn't mention it. → The IP is
  removed after setup. ([IP pricing][ip]) Medium.
- **Outbound data.** 1 GB/month from North America for Compute, and a separate 1 GB/month for Cloud Run. Heavy public
  image galleries to the Philippines could exceed it. ([free tier][free]) High.

### 2. Cloud Run free tier, Jobs and cold starts
- **Free tier.** 2M requests, 180,000 vCPU-s, 360,000 GiB-s and 1 GB NA egress per month (request-based), aggregated
  per billing account. us-central1 is Tier 1. ([free tier][free], [pricing][runprice]) High/Medium.
- **Execution environment.** Jobs always run gen2, and a volume mount forces gen2 on the service. Gen2 can cold-start
  slower. ([environments][env]) High.
- **Startup advice.** Use startup CPU boost, do little work at startup, and optimize the Composer autoload.
  ([tips][tips]) High.
- **Direct VPC connection.** "Connection setup can take a minute or more at startup." ([direct VPC][dvpc]) High.

### 3. Direct VPC egress, firewall, VM internet access
- **Flags.** `--network=default --subnet=default --vpc-egress=private-ranges-only`, the same for services and jobs.
  - The Compute API must be on, and the subnet must be /26 or larger. Each instance uses 4 IPs.
  - Ingress firewall rules can't match on network tags or the service identity, so the DB rule uses a source range.
  - ([direct VPC][dvpc]) High.
- **Cost.** There's no connector compute charge; you pay network egress only. ([connecting][conn]) Medium.
- **Subnet.** The us-central1 auto-mode subnet is **`10.128.0.0/20`**. The MariaDB user is also limited to
  `'iconvenue'@'10.128.%'`. ([subnets][subnets], [MariaDB remote access][mremote]) High.
- **Private Google Access.** It reaches Google APIs only, not deb.debian.org, and is enabled per subnet.
  ([PGA][pga], [configure PGA][pgacfg]) High.
  - For patching, attach a temporary external IP. Cloud NAT is roughly $4–5/month. ([NAT pricing][nat]) Medium.
  - Unattended upgrades can't reach the mirrors while the VM has no route out.
- **Internal IPs.** An ephemeral internal IP "in general" is released when the resource stops; you can reserve a
  static one. ([static internal IP][sip]) High.

### 4. Cloud Storage volume mount (uploads)
- **Mount.** `--add-volume name=…,type=cloud-storage,bucket=…,mount-options=uid=33;gid=33` plus `--add-volume-mount`.
  - It requires gen2, and the default owner is root.
  - There's no file locking (last write wins). If the mount fails, the service fails to start.
  - ([volume mounts][gcsmount], [jobs][gcsmountjobs]) High.
- **gcsfuse semantics.**
  - chmod and chown are silently ignored, and mtime is tracked.
  - Symlinks are supported, but directory rename isn't on flat buckets.
  - Data is only in GCS after the file is closed.
  - ([semantics][gcsfuse], [overview][gcsfuseov]) High.
- **Applies here.**
  - The `public/storage` symlink is in the image, and Apache `FollowSymLinks` serves through it.
  - `HandlesImageUploads::ensurePublicAccess` and the controller copy helpers resolve to the same object, so
    `file_exists` is true and no copy happens. The XAMPP workaround stays unchanged.
- **Storage free tier.** 5 GB regional (US), 5k Class A and 50k Class B operations per month, so the bucket must be in
  us-central1. ([free tier][free]) High.

### 5. Cloud Scheduler → Cloud Run Job
- **How it's called.** `POST https://run.googleapis.com/v2/projects/P/locations/R/jobs/JOB:run`, with
  `--oauth-service-account-email` (OAuth, not OIDC). `--time-zone Asia/Manila` works.
  ([jobs on schedule][sched], [gcloud scheduler][gsched]) High.
- **IAM.** `roles/run.invoker` includes `run.jobs.run` but not `run.jobs.runWithOverrides`.
  ([IAM roles][iam], [execute jobs][execjobs]) High.
- **Price.** 3 Scheduler jobs are free per billing account, then $0.10 per job per month. → 2 jobs cost $0.
  ([pricing][schedprice]) Medium.

### 6. Secret Manager
- **Syntax.** `--set-secrets=KEY=secret:version`, on services and jobs.
  - The runtime SA needs `secretAccessor`. Values are resolved at instance start.
  - Google recommends pinning versions. We use `latest` for simple rotation; values only change on a new
    instance/deploy.
  - ([secrets][secrets], [jobs deploy][jobsdeploy]) High.
- **Free tier.** 6 active versions and 10k accesses per month. ([free tier][free]) High.

### 7. Port 80 and Apache
- **Port.** `--port 80` routes traffic there and sets `PORT`. The container must listen on 0.0.0.0, and the image runs
  as root if no user is set. ([containers][containers], [contract][contract]) High.
  - Google's PHP quickstart rewrites Apache's Listen to `${PORT}` instead, which is a fallback if binding 80 ever fails.
    ([PHP quickstart][phpqs]) Medium.
- **Logs.** The official `php:8.3-apache` image links Apache's logs to stdout/stderr. ([image][phpimg]) High.
- **Limits.** Default concurrency is 80 × vCPU (lower it for memory-heavy requests). HTTP/1 requests are capped at
  32 MiB. `/tmp` lives in memory. ([concurrency][conc], [quotas][quotas], [contract][contract]) High.

### 8. Laravel behind the Cloud Run TLS front end
- Laravel recommends `trustProxies(at: '*')` behind cloud load balancers. `'*'` trusts only the immediate peer.
  ([Laravel 12 trusted proxies][ltp], [TrustProxies source][ltpsrc]) High.
- `URL::forceScheme('https')`, `SESSION_SECURE_COOKIE=true` and `LOG_CHANNEL=stderr` are correct.

### 9. MariaDB 10.11 on 1 GB RAM
- **Package.** Debian 12 ships `mariadb-server 1:10.11.19-0+deb12u1`. ([Debian][deb]) High.
- **Settings.** `performance_schema` is off by default. The buffer pool should hold the hot data set without causing
  swapping. Set `bind-address` under `[mysqld]`. ([perf schema][perf], [buffer pool][bp], [remote access][mremote]) High.
  - Inference: about 256 MB of buffer pool and `max_connections ≈ 50` (2 instances × 10 workers + jobs).

### 10. Cloud Build and Artifact Registry
- **Build config.** A non-root Dockerfile path needs `--config` with a build step. Without a `.gcloudignore`, gcloud
  generates one from `.gitignore`, which wouldn't exclude `icon (3).sql`. ([builds submit][cb]) High.
- **Cloud Build free tier.** 2,500 build-minutes per month. ([free tier][free]) High.
- **Artifact Registry.** 0.5 GB free, then $0.10/GB-month. → A cleanup policy keeps the newest 3 images.
  ([AR pricing][ar]) Medium.

### 11. `throttle:login` behind Cloud Run
- **Behind Cloud Run.** Cloud Run appends the real client IP as the **last** X-Forwarded-For entry
  ([forum, 2026-10-30][xff]; Low–Medium).
  - With `trustProxies('*')`, Symfony walks the header from the right, so `ip()` is that appended value, which callers
    can't spoof (inference).
- **Shared state.** `CACHE_STORE=database`, so instances share the limiter state.

## Options and trade-offs
| Option | Pros | Cons | Cost |
|---|---|---|---|
| Patch the VM via a temporary external IP | Cheapest; no standing exposure | Manual; no unattended upgrades | ~$0.005/hr while attached |
| Cloud NAT | Always-on apt | Uses up most of the budget | ~$4–5/month + $0.045/GiB |
| Two fixed-arg jobs (chosen) | `run.invoker` only; clear logs | One more job definition | $0 |
| Build-time route/view/event caches (chosen) | Faster cold starts; secrets stay out of the image | Small Dockerfile change | $0 |

## Risks and gotchas
- Enable PGA before removing the DB VM's external IP, or backups fail.
- Direct VPC can take over a minute to connect on a cold start, so the entrypoint waits up to 150 s for the DB.
- The free tier allows only one e2-micro per billing account. A second VM or a `pd-balanced` disk is billed.
- Image egress to APAC above 1 GB/month is billed; the rate couldn't be confirmed.

## Open questions
- Is an in-use external IPv4 on the free e2-micro billed? Check the billing SKU "External IP Charge on a Standard VM".
- Is a reserved static *internal* IP charged? The docs don't say.
- The value syntax for `file-mode`/`dir-mode` mount options isn't documented.

[free]: https://docs.cloud.google.com/free/docs/free-cloud-features
[snap]: https://cloud.google.com/compute/pricing-announce
[ip]: https://cloud.google.com/vpc/pricing-announce-external-ips
[runprice]: https://cloud.google.com/run/pricing
[env]: https://docs.cloud.google.com/run/docs/about-execution-environments
[tips]: https://docs.cloud.google.com/run/docs/tips/general
[dvpc]: https://docs.cloud.google.com/run/docs/configuring/vpc-direct-vpc
[conn]: https://docs.cloud.google.com/run/docs/configuring/connecting-vpc
[subnets]: https://docs.cloud.google.com/vpc/docs/subnets
[pga]: https://docs.cloud.google.com/vpc/docs/private-google-access
[pgacfg]: https://docs.cloud.google.com/vpc/docs/configure-private-google-access
[nat]: https://cloud.google.com/nat/pricing
[sip]: https://docs.cloud.google.com/compute/docs/ip-addresses/reserve-static-internal-ip-address
[gcsmount]: https://docs.cloud.google.com/run/docs/configuring/services/cloud-storage-volume-mounts
[gcsmountjobs]: https://docs.cloud.google.com/run/docs/configuring/jobs/cloud-storage-volume-mounts
[gcsfuse]: https://github.com/GoogleCloudPlatform/gcsfuse/blob/master/docs/semantics.md
[gcsfuseov]: https://docs.cloud.google.com/storage/docs/cloud-storage-fuse/overview
[sched]: https://docs.cloud.google.com/run/docs/execute/jobs-on-schedule
[gsched]: https://docs.cloud.google.com/sdk/gcloud/reference/scheduler/jobs/create/http
[iam]: https://docs.cloud.google.com/run/docs/reference/iam/roles
[execjobs]: https://docs.cloud.google.com/run/docs/execute/jobs
[schedprice]: https://cloud.google.com/scheduler/pricing
[secrets]: https://docs.cloud.google.com/run/docs/configuring/services/secrets
[jobsdeploy]: https://docs.cloud.google.com/sdk/gcloud/reference/run/jobs/deploy
[containers]: https://docs.cloud.google.com/run/docs/configuring/services/containers
[contract]: https://docs.cloud.google.com/run/docs/container-contract
[phpqs]: https://docs.cloud.google.com/run/docs/quickstarts/build-and-deploy/php
[phpimg]: https://github.com/docker-library/php/blob/master/8.3/bookworm/apache/Dockerfile
[conc]: https://docs.cloud.google.com/run/docs/about-concurrency
[quotas]: https://docs.cloud.google.com/run/quotas
[ltp]: https://laravel.com/docs/12.x/requests#configuring-trusted-proxies
[ltpsrc]: https://github.com/laravel/framework/blob/12.x/src/Illuminate/Http/Middleware/TrustProxies.php
[deb]: https://packages.debian.org/bookworm/mariadb-server
[perf]: https://mariadb.com/docs/server/reference/system-tables/performance-schema/performance-schema-overview
[bp]: https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-buffer-pool
[mremote]: https://mariadb.com/kb/en/configuring-mariadb-for-remote-client-access/
[cb]: https://docs.cloud.google.com/build/docs/running-builds/submit-build-via-cli-api
[ar]: https://cloud.google.com/artifact-registry/pricing
[xff]: https://discuss.google.dev/t/cloud-run-which-client-ip-can-i-safely-use-for-rate-limiting/409611
