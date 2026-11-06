#!/usr/bin/env bash
# Build and deploy the app to Cloud Run from your local working tree. Run from the repo root:
#   deploy/cloudrun-deploy.sh
# Steps: build (Cloud Build → Artifact Registry) → migrate job → service → scheduled jobs → health check.
# One-time prerequisites: deploy/gcp-setup.sh and the DB VM provisioning (docs/deployment.md).
set -euo pipefail

P=icon-events-513902
R=us-central1
SERVICE=iconvenue
RUN_SA="iconvenue-run@$P.iam.gserviceaccount.com"
SCHED_SA="iconvenue-scheduler@$P.iam.gserviceaccount.com"
UPLOADS_BUCKET="$P-uploads"
IMAGE_REPO="$R-docker.pkg.dev/$P/iconvenue/app"

cd "$(dirname "${BASH_SOURCE[0]}")/.."
g() { gcloud --project "$P" --quiet "$@"; }
exists() { "$@" >/dev/null 2>&1; }

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
trap 'rm -f "$ENV_FILE"' EXIT
{
  cat deploy/cloudrun.env.yaml
  echo "APP_URL: \"$APP_URL\""
  echo "DB_HOST: \"$DB_HOST\""
  echo "RUN_MIGRATIONS: \"false\""
} > "$ENV_FILE"

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
deploy_job iconvenue-migrate migrate --force
g run jobs execute iconvenue-migrate --region "$R" --wait

echo "==> Service $SERVICE"
g run deploy "$SERVICE" "${COMMON[@]}" \
  --port 80 --allow-unauthenticated --ingress "$INGRESS" \
  --startup-probe "httpGet.path=/up,httpGet.port=80,initialDelaySeconds=0,periodSeconds=5,timeoutSeconds=3,failureThreshold=36" \
  --execution-environment gen2 --cpu 1 --memory 1Gi --cpu-boost \
  --concurrency 10 --min-instances 0 --max-instances 2 --timeout 300 \
  --clear-volumes --clear-volume-mounts \
  --add-volume "name=uploads,type=cloud-storage,bucket=$UPLOADS_BUCKET,mount-options=uid=33;gid=33" \
  --add-volume-mount "volume=uploads,mount-path=/var/www/html/storage/app/public"

echo "==> Scheduled jobs (Cloud Scheduler → Cloud Run Jobs, Asia/Manila)"
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
