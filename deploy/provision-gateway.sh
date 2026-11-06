#!/usr/bin/env bash
# Tailnet gateway on the DB VM `iconvenue-db`: Tailscale + nginx → Cloud Run (ingress internal).
# Only registered tailnet devices can reach the app. Safe to re-run.
# See docs/decisions/2026-11-06-tailnet-only-access.md and docs/deployment.md.
#
# Run from your machine over IAP after `deploy/gcp-setup.sh` gave the VM its external IP:
#   gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap \
#     --command 'sudo bash -s' < deploy/provision-gateway.sh
# First run: `tailscale up` prints a login URL. Open it as the tailnet admin to approve the machine
# (no auth key is ever stored). The script waits until the machine is approved.
set -euo pipefail

RUN_HOST=iconvenue-1091789543033.us-central1.run.app
TS_HOSTNAME=iconvenue
TS_TAG=tag:iconvenue-prod
LOCAL_PORT=8080

echo "==> Tailscale (official bookworm repo)"
if ! command -v tailscale >/dev/null; then
  install -d -m 0755 /usr/share/keyrings
  curl -fsSL https://pkgs.tailscale.com/stable/debian/bookworm.noarmor.gpg -o /usr/share/keyrings/tailscale-archive-keyring.gpg
  curl -fsSL https://pkgs.tailscale.com/stable/debian/bookworm.tailscale-keyring.list -o /etc/apt/sources.list.d/tailscale.list
  apt-get update
  apt-get install -y --no-install-recommends tailscale
fi
systemctl enable --now tailscaled

echo "==> nginx (127.0.0.1:${LOCAL_PORT} → https://${RUN_HOST})"
DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx-light
rm -f /etc/nginx/sites-enabled/default
cat > /etc/nginx/sites-available/iconvenue-gateway <<NGINX
# Installed by deploy/provision-gateway.sh. tailscale serve (:443 on the tailnet) → here → Cloud Run.
# Cloud Run routes by Host + SNI, so both must be the run.app name; the variable + resolver makes
# nginx re-resolve Google's front-end IPs instead of pinning the first answer.
resolver 169.254.169.254 valid=300s ipv6=off;
server {
    listen 127.0.0.1:${LOCAL_PORT};
    server_name _;
    client_max_body_size 30m;   # = php post_max_size; Cloud Run caps HTTP/1 bodies at 32 MiB

    location / {
        set \$run_host ${RUN_HOST};
        proxy_pass https://\$run_host;
        proxy_set_header Host \$run_host;
        proxy_ssl_server_name on;
        proxy_ssl_name \$run_host;
        proxy_ssl_verify on;
        proxy_ssl_verify_depth 3;
        proxy_ssl_trusted_certificate /etc/ssl/certs/ca-certificates.crt;
        proxy_http_version 1.1;
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;
        # From tailscale serve: the tailnet client and the ts.net host. The app pins the host itself
        # (APP_PUBLIC_HOST), these are kept for logs.
        proxy_set_header X-Forwarded-Host \$http_x_forwarded_host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For \$http_x_forwarded_for;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/iconvenue-gateway /etc/nginx/sites-enabled/iconvenue-gateway
nginx -t
systemctl enable nginx
systemctl reload-or-restart nginx

echo "==> Joining the tailnet as ${TS_HOSTNAME} (${TS_TAG})"
if ! tailscale status >/dev/null 2>&1; then
  # Prints a login URL; the admin opens it to approve. --accept-dns=false keeps GCE metadata DNS.
  tailscale up --hostname="$TS_HOSTNAME" --advertise-tags="$TS_TAG" --ssh --accept-dns=false --timeout=15m
fi

echo "==> tailscale serve: https://<name>.ts.net → 127.0.0.1:${LOCAL_PORT}"
tailscale serve --bg --https=443 "http://127.0.0.1:${LOCAL_PORT}"

TS_NAME="$(tailscale status --json | sed -n 's/.*"DNSName": *"\([^"]*\)\.".*/\1/p' | head -1)"
echo
echo "Gateway ready: https://${TS_NAME}"
echo "Memory:"; free -m | head -2
ps -o rss=,comm= -C tailscaled,nginx,mariadbd | awk '{printf "  %-10s %6.0f MB\n", $2, $1/1024}'
