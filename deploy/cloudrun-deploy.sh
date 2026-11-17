#!/usr/bin/env bash
# Build and deploy the app to Cloud Run from your local working tree. Run from the repo root:
#   deploy/cloudrun-deploy.sh
# Steps: pre-deploy DB backup (aborts if it fails) → build (Cloud Build → Artifact Registry) → migrate job →
# service → scheduled jobs → deploy record → health check.
# Every deploy writes gs://<project>-db-backups/deploys/<tag>.json (previous revision/image, backup object,
# migration counts). The /rollback-prod skill reads it. Backups and records are deleted after 30 days.
# One-time prerequisites: deploy/gcp-setup.sh and the DB VM provisioning (docs/deployment.md).
set -euo pipefail

P=icon-events-513902
R=us-central1
SERVICE=iconvenue
RUN_SA="iconvenue-run@$P.iam.gserviceaccount.com"
SCHED_SA="iconvenue-scheduler@$P.iam.gserviceaccount.com"
UPLOADS_BUCKET="$P-uploads"
IMAGE_REPO="$R-docker.pkg.dev/$P/iconvenue/app"
BACKUP_BUCKET="gs://$P-db-backups"
DB_VM=iconvenue-db
DB_ZONE=us-central1-a

cd "$(dirname "${BASH_SOURCE[0]}")/.."
g() { gcloud --project "$P" --quiet "$@"; }
exists() { "$@" >/dev/null 2>&1; }
# Run a command on the DB VM as the IAP SSH user (stdin is passed through).
vm() { g compute ssh "$DB_VM" --zone "$DB_ZONE" --tunnel-through-iap --command "$1"; }
migration_count() {
  local n
  n="$(vm "sudo mariadb -N -B iconvenue -e 'SELECT COUNT(*) FROM migrations'" 2>/dev/null | tr -d '\r' | tail -n 1)"
  [[ "$n" =~ ^[0-9]+$ ]] && echo "$n" || echo ""
}

TAG="$(date +%Y%m%d-%H%M%S)-$(git rev-parse --short HEAD 2>/dev/null || echo nogit)"
[ -z "$(git status --porcelain 2>/dev/null)" ] || TAG="$TAG-dirty"
IMAGE="$IMAGE_REPO:$TAG"

PROJECT_NUMBER="$(g projects describe "$P" --format 'value(projectNumber)')"
# Deterministic Cloud Run URL (what the tailnet gateway's nginx forwards to).
RUN_URL="https://$SERVICE-$PROJECT_NUMBER.$R.run.app"
# Tailnet-only mode (docs/decisions/2026-11-06-tailnet-only-access.md): when APP_PUBLIC_HOST is set in
# deploy/cloudrun.env.yaml, the app is served only at https://<that host> through the gateway on
# iconvenue-db and Cloud Run ingress is internal. Unset = public on run.app.
PUBLIC_HOST="$(sed -n 's/^APP_PUBLIC_HOST: *"\{0,1\}\([^"]*\)"\{0,1\}$/\1/p' deploy/cloudrun.env.yaml)"
if [ -n "$PUBLIC_HOST" ]; then
  APP_URL="https://$PUBLIC_HOST"; INGRESS=internal
else
  APP_URL="$RUN_URL"; INGRESS=all
fi
DB_HOST="$(g compute addresses describe iconvenue-db-ip --region "$R" --format 'value(address)')"

# One env file for service and jobs: the committed config plus the deploy-time values.
ENV_FILE="$(mktemp)"
RECORD_FILE="$(mktemp)"
STAGE=init
RECORD_WRITTEN=""
PREV_REVISION="" PREV_IMAGE="" BACKUP_OBJECT="" MIGRATIONS_BEFORE="" MIGRATIONS_AFTER=""

# The deploy record the rollback skill reads. Written on success and on any failure after the backup.
write_record() { # status
  local status="$1" migrated=false
  [ -n "$MIGRATIONS_AFTER" ] && [ -n "$MIGRATIONS_BEFORE" ] && [ "$MIGRATIONS_AFTER" != "$MIGRATIONS_BEFORE" ] && migrated=true
  # Unknown counts after a failure: assume migrations may have run, so a rollback restores the backup.
  [ -z "$MIGRATIONS_AFTER" ] && [ "$status" != deployed ] && migrated=true
  cat > "$RECORD_FILE" <<JSON
{
  "tag": "$TAG",
  "status": "$status",
  "deployed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "git_sha": "$(git rev-parse HEAD 2>/dev/null || echo nogit)",
  "image": "$IMAGE",
  "previous_revision": "$PREV_REVISION",
  "previous_image": "$PREV_IMAGE",
  "backup_object": "$BACKUP_OBJECT",
  "migrations_before": "${MIGRATIONS_BEFORE}",
  "migrations_after": "${MIGRATIONS_AFTER}",
  "migrated": $migrated
}
JSON
  g storage cp "$RECORD_FILE" "$BACKUP_BUCKET/deploys/$TAG.json" >/dev/null && RECORD_WRITTEN=1
  echo "    deploy record: $BACKUP_BUCKET/deploys/$TAG.json ($status, migrated=$migrated)"
}
on_exit() {
  local rc=$?
  if [ "$rc" -ne 0 ] && [ -n "$BACKUP_OBJECT" ] && [ -z "$RECORD_WRITTEN" ]; then
    MIGRATIONS_AFTER="$(migration_count || true)"
    write_record "failed-at-$STAGE" || true
    echo "deploy: failed at '$STAGE'. Backup: $BACKUP_OBJECT. To undo: /rollback-prod $TAG" >&2
  fi
  rm -f "$ENV_FILE" "$RECORD_FILE"
}
trap on_exit EXIT
{
  cat deploy/cloudrun.env.yaml
  echo "APP_URL: \"$APP_URL\""
  echo "DB_HOST: \"$DB_HOST\""
  echo "RUN_MIGRATIONS: \"false\""
} > "$ENV_FILE"

echo "==> Current production"
if exists g run services describe "$SERVICE" --region "$R"; then
  PREV_REVISION="$(g run services describe "$SERVICE" --region "$R" \
    --format 'value(status.traffic[0].revisionName)')"
  [ -n "$PREV_REVISION" ] && PREV_IMAGE="$(g run revisions describe "$PREV_REVISION" --region "$R" \
    --format 'value(spec.containers[0].image)')"
fi
echo "    serving: ${PREV_REVISION:-<none>} (${PREV_IMAGE:-no image})"

echo "==> Pre-deploy database backup (deploy aborts if this fails)"
STAGE=backup
MIGRATIONS_BEFORE="$(migration_count)"
[ -n "$MIGRATIONS_BEFORE" ] || { echo "deploy: could not read the migrations table on $DB_VM; not deploying" >&2; exit 1; }
BACKUP_OUT="$(vm "sudo bash -s -- predeploy-$TAG" < deploy/backup-db.sh)" || {
  echo "$BACKUP_OUT" >&2; echo "deploy: pre-deploy backup failed; nothing was deployed" >&2; exit 1; }
BACKUP_OBJECT="$(printf '%s\n' "$BACKUP_OUT" | tr -d '\r' | sed -n 's/^BACKUP_OBJECT=//p' | tail -n 1)"
[ -n "$BACKUP_OBJECT" ] || { echo "$BACKUP_OUT" >&2; echo "deploy: backup gave no object path; not deploying" >&2; exit 1; }
echo "    $BACKUP_OBJECT (migrations applied so far: $MIGRATIONS_BEFORE)"

STAGE=build
echo "==> Building $IMAGE (Cloud Build, local working tree filtered by .gcloudignore)"
g builds submit --region "$R" --config deploy/cloudbuild.yaml --substitutions "_IMAGE=$IMAGE" .

# Shared by the service and every job: same image, config, secrets and private route to the DB.
COMMON=(
  --region "$R"
  --image "$IMAGE"
  --service-account "$RUN_SA"
  --env-vars-file "$ENV_FILE"
  --set-secrets "APP_KEY=iconvenue-app-key:latest,DB_PASSWORD=iconvenue-db-password:latest"
  --network default --subnet default --vpc-egress private-ranges-only
)

deploy_job() { # name, then the artisan args
  local name="$1"; shift
  local args; args="$(IFS=,; echo "php,artisan,$*")"
  g run jobs deploy "$name" "${COMMON[@]}" --args "$args" \
    --tasks 1 --max-retries 0 --task-timeout 15m --memory 1Gi --cpu 1
}

echo "==> Migrations (job iconvenue-migrate)"
STAGE=migrate
deploy_job iconvenue-migrate migrate --force
g run jobs execute iconvenue-migrate --region "$R" --wait
MIGRATIONS_AFTER="$(migration_count)"
if [ -n "$MIGRATIONS_AFTER" ] && [ "$MIGRATIONS_AFTER" != "$MIGRATIONS_BEFORE" ]; then
  echo "    applied $((MIGRATIONS_AFTER - MIGRATIONS_BEFORE)) new migration(s)"
else
  echo "    none to apply"
fi

echo "==> Service $SERVICE"
STAGE=service
g run deploy "$SERVICE" "${COMMON[@]}" \
  --port 80 --allow-unauthenticated --ingress "$INGRESS" \
  --startup-probe "httpGet.path=/up,httpGet.port=80,initialDelaySeconds=0,periodSeconds=5,timeoutSeconds=3,failureThreshold=36" \
  --execution-environment gen2 --cpu 1 --memory 1Gi --cpu-boost \
  --concurrency 10 --min-instances 0 --max-instances 2 --timeout 300 \
  --clear-volumes --clear-volume-mounts \
  --add-volume "name=uploads,type=cloud-storage,bucket=$UPLOADS_BUCKET,mount-options=uid=33;gid=33" \
  --add-volume-mount "volume=uploads,mount-path=/var/www/html/storage/app/public"
# After a /rollback-prod, traffic is pinned to an old revision and a deploy alone would not move it.
g run services update-traffic "$SERVICE" --region "$R" --to-latest >/dev/null

echo "==> Scheduled jobs (Cloud Scheduler → Cloud Run Jobs, Asia/Manila)"
STAGE=scheduled-jobs
schedule() { # job name, cron, artisan command
  local job="$1" cron="$2" cmd="$3"
  deploy_job "$job" "$cmd"
  g run jobs add-iam-policy-binding "$job" --region "$R" \
    --member "serviceAccount:$SCHED_SA" --role roles/run.invoker >/dev/null
  local uri="https://run.googleapis.com/v2/projects/$P/locations/$R/jobs/$job:run"
  local verb=create
  exists g scheduler jobs describe "$job" --location "$R" && verb=update
  g scheduler jobs "$verb" http "$job" --location "$R" --schedule "$cron" --time-zone Asia/Manila \
    --uri "$uri" --http-method POST --oauth-service-account-email "$SCHED_SA" >/dev/null
  echo "    $job: '$cron' → $cmd"
}
schedule iconvenue-reminders "0 9 * * *" bookings:send-reminders
schedule iconvenue-complete-expired "*/15 * * * *" bookings:complete-expired

echo "==> Deploy record"
write_record deployed

STAGE=health-check
echo "==> Health check"
# The startup probe above already required /up = 200 before the revision took traffic.
if [ "$INGRESS" = internal ]; then
  code="$(curl -s -o /dev/null -w '%{http_code}' "$RUN_URL/up" || true)"
  if [ "$code" = 200 ]; then
    echo "deploy: $RUN_URL answered 200 from the internet; ingress should be internal" >&2
    exit 1
  fi
  echo "    run.app from the internet: HTTP $code (blocked, as intended)"
  # macOS app bundles its CLI here instead of on PATH.
  TS_CLI="$(command -v tailscale || echo /Applications/Tailscale.app/Contents/MacOS/Tailscale)"
  if [ -x "$TS_CLI" ] && "$TS_CLI" status >/dev/null 2>&1 && curl -fsS -o /dev/null --max-time 20 "$APP_URL/up"; then
    echo "    $APP_URL/up: 200 over the tailnet"
  else
    echo "    (this machine isn't connected to the tailnet: open $APP_URL/up from a registered device to check)"
  fi
  echo "==> Deployed $TAG. Live at $APP_URL (tailnet only)"
  exit 0
fi
for _ in $(seq 1 20); do
  if curl -fsS -o /dev/null "$APP_URL/up"; then
    echo "==> Deployed $TAG. Live at $APP_URL"
    exit 0
  fi
  sleep 6
done
echo "deploy: $APP_URL/up did not return 200 (check: gcloud run services logs read $SERVICE --region $R --project $P)" >&2
exit 1
