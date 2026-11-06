#!/usr/bin/env bash
# One-time GCP setup for production (Cloud Run + MariaDB on a free-tier e2-micro). Run from your machine.
# Re-runnable: each step skips what already exists. See docs/deployment.md and
# docs/decisions/2026-11-06-production-cloud-run.md.
#   deploy/gcp-setup.sh
# Secrets (APP_KEY, DB password) are generated here and piped straight into Secret Manager:
# they are never printed or written to disk.
set -euo pipefail

P=icon-events-513902
R=us-central1
Z=us-central1-a
SUBNET_RANGE=10.128.0.0/20 # us-central1 auto-mode default subnet (Cloud Run Direct VPC egress uses it)

DB_VM=iconvenue-db
DB_IP_NAME=iconvenue-db-ip
DB_SA=iconvenue-db-vm
RUN_SA=iconvenue-run
SCHED_SA=iconvenue-scheduler
BACKUP_BUCKET=gs://$P-db-backups
UPLOADS_BUCKET=gs://$P-uploads
AR_REPO=iconvenue
SECRET_APP_KEY=iconvenue-app-key
SECRET_DB_PASSWORD=iconvenue-db-password

g() { gcloud --project "$P" --quiet "$@"; }
exists() { "$@" >/dev/null 2>&1; }
sa() { echo "$1@$P.iam.gserviceaccount.com"; }

echo "==> APIs"
g services enable compute.googleapis.com run.googleapis.com artifactregistry.googleapis.com \
  cloudbuild.googleapis.com secretmanager.googleapis.com cloudscheduler.googleapis.com iap.googleapis.com

echo "==> Firewall: only IAP SSH, and MariaDB from the Cloud Run subnet"
for rule in default-allow-ssh default-allow-rdp default-allow-icmp; do
  exists g compute firewall-rules describe "$rule" && g compute firewall-rules delete "$rule"
done
exists g compute firewall-rules describe allow-iap-ssh || g compute firewall-rules create allow-iap-ssh \
  --network default --direction INGRESS --allow tcp:22 --source-ranges 35.235.240.0/20
exists g compute firewall-rules describe allow-mariadb-from-subnet || g compute firewall-rules create allow-mariadb-from-subnet \
  --network default --direction INGRESS --allow tcp:3306 --source-ranges "$SUBNET_RANGE" --target-tags db

echo "==> Private Google Access (DB VM backups reach Cloud Storage without an external IP)"
g compute networks subnets update default --region "$R" --enable-private-ip-google-access

echo "==> Static internal IP for the DB (DB_HOST never changes)"
exists g compute addresses describe "$DB_IP_NAME" --region "$R" || \
  g compute addresses create "$DB_IP_NAME" --region "$R" --subnet default

echo "==> Service accounts"
for s in "$DB_SA:iconvenue-db VM (backups only)" "$RUN_SA:Cloud Run service and jobs" "$SCHED_SA:Cloud Scheduler -> Cloud Run jobs"; do
  name="${s%%:*}"
  exists g iam service-accounts describe "$(sa "$name")" || g iam service-accounts create "$name" --display-name "${s#*:}"
done

echo "==> Buckets"
LIFECYCLE="$(mktemp)"
echo '{"rule":[{"action":{"type":"Delete"},"condition":{"age":30}}]}' > "$LIFECYCLE"
exists g storage buckets describe "$BACKUP_BUCKET" || \
  g storage buckets create "$BACKUP_BUCKET" --location "$R" --uniform-bucket-level-access --public-access-prevention
g storage buckets update "$BACKUP_BUCKET" --lifecycle-file "$LIFECYCLE"
rm -f "$LIFECYCLE"
exists g storage buckets describe "$UPLOADS_BUCKET" || \
  g storage buckets create "$UPLOADS_BUCKET" --location "$R" --uniform-bucket-level-access --public-access-prevention
g storage buckets add-iam-policy-binding "$BACKUP_BUCKET" --member "serviceAccount:$(sa "$DB_SA")" \
  --role roles/storage.objectCreator >/dev/null
g storage buckets add-iam-policy-binding "$UPLOADS_BUCKET" --member "serviceAccount:$(sa "$RUN_SA")" \
  --role roles/storage.objectUser >/dev/null

echo "==> Secrets (generated and stored without printing)"
if ! exists g secrets describe "$SECRET_APP_KEY"; then
  printf 'base64:%s' "$(openssl rand -base64 32)" | g secrets create "$SECRET_APP_KEY" --replication-policy automatic --data-file=-
fi
if ! exists g secrets describe "$SECRET_DB_PASSWORD"; then
  printf '%s' "$(openssl rand -hex 24)" | g secrets create "$SECRET_DB_PASSWORD" --replication-policy automatic --data-file=-
fi
g secrets add-iam-policy-binding "$SECRET_APP_KEY" --member "serviceAccount:$(sa "$RUN_SA")" \
  --role roles/secretmanager.secretAccessor >/dev/null
for who in "$RUN_SA" "$DB_SA"; do
  g secrets add-iam-policy-binding "$SECRET_DB_PASSWORD" --member "serviceAccount:$(sa "$who")" \
    --role roles/secretmanager.secretAccessor >/dev/null
done

echo "==> Artifact Registry (keep the 3 newest images)"
exists g artifacts repositories describe "$AR_REPO" --location "$R" || \
  g artifacts repositories create "$AR_REPO" --location "$R" --repository-format docker
POLICY="$(mktemp)"
cat > "$POLICY" <<'JSON'
[
  {"name": "keep-newest-3", "action": {"type": "Keep"}, "mostRecentVersions": {"keepCount": 3}},
  {"name": "delete-older", "action": {"type": "Delete"}, "condition": {"tagState": "any", "olderThan": "1d"}}
]
JSON
g artifacts repositories set-cleanup-policies "$AR_REPO" --location "$R" --policy "$POLICY" --no-dry-run >/dev/null
rm -f "$POLICY"

echo "==> Snapshot schedule (daily 02:00 Manila = 18:00 UTC, 7 days, stored in $R)"
exists g compute resource-policies describe iconvenue-db-daily --region "$R" || \
  g compute resource-policies create snapshot-schedule iconvenue-db-daily --region "$R" \
    --daily-schedule --start-time 18:00 --max-retention-days 7 --storage-location "$R" \
    --on-source-disk-delete keep-auto-snapshots

echo "==> DB VM (e2-micro, pd-standard 30 GB: Always Free in $R)"
if ! exists g compute instances describe "$DB_VM" --zone "$Z"; then
  DB_IP="$(g compute addresses describe "$DB_IP_NAME" --region "$R" --format 'value(address)')"
  # Starts with an ephemeral external IP for provisioning; the gateway step below swaps in the static one.
  g compute instances create "$DB_VM" --zone "$Z" \
    --machine-type e2-micro --image-family debian-12 --image-project debian-cloud \
    --boot-disk-size 30GB --boot-disk-type pd-standard \
    --private-network-ip "$DB_IP" --tags db \
    --service-account "$(sa "$DB_SA")" --scopes cloud-platform \
    --shielded-secure-boot --shielded-vtpm --shielded-integrity-monitoring \
    --metadata enable-oslogin=TRUE
  g compute disks add-resource-policies "$DB_VM" --zone "$Z" --resource-policies iconvenue-db-daily
fi

echo "==> Tailnet gateway: static external IP on the DB VM (Tailscale control plane, DERP, apt)"
# docs/decisions/2026-11-06-tailnet-only-access.md. Ingress firewall stays closed except IAP:22,
# subnet:3306 and Tailscale's direct-connection UDP port.
exists g compute addresses describe iconvenue-db-public --region "$R" || \
  g compute addresses create iconvenue-db-public --region "$R"
if [ -z "$(g compute instances describe "$DB_VM" --zone "$Z" --format 'value(networkInterfaces[0].accessConfigs[0].natIP)')" ]; then
  PUB_IP="$(g compute addresses describe iconvenue-db-public --region "$R" --format 'value(address)')"
  g compute instances add-access-config "$DB_VM" --zone "$Z" --access-config-name external-nat --address "$PUB_IP"
fi
exists g compute firewall-rules describe allow-tailscale-direct || g compute firewall-rules create allow-tailscale-direct \
  --network default --direction INGRESS --allow udp:41641 --source-ranges 0.0.0.0/0 --target-tags db

echo
echo "GCP setup done. Next (docs/deployment.md → First deploy):"
echo "  1. Provision the DB VM, 2. remove its external IP, 3. deploy/cloudrun-deploy.sh, 4. create the admin."
