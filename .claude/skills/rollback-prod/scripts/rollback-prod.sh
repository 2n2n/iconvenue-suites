#!/usr/bin/env bash
# Roll production back to the build before a deploy, and restore that deploy's pre-deploy backup when the deploy
# applied migrations. Reads the deploy record written by deploy/cloudrun-deploy.sh
# (gs://icon-events-513902-db-backups/deploys/<tag>.json, kept 30 days).
#
#   rollback-prod.sh [<deploy-tag>] [--restore-db] [--yes]
#     <deploy-tag>   which deploy to undo (default: the newest record)
#     --restore-db   restore the pre-deploy backup even if that deploy applied no migrations
#     --yes          execute; without it the script only prints the plan (dry run) and changes nothing
#   rollback-prod.sh --record <file>                   dry-run plan from a local record file (testing)
#   rollback-prod.sh [<deploy-tag>] --test-restore <db> restore the backup into scratch DB <db> on the VM,
#                                                       verify it, then drop it. Production is untouched.
#
# Execution order (--yes): safety backup (prerollback-<tag>) → traffic to the previous revision → jobs re-pointed to the
# previous image → DB restore (if needed) → health check. Runbook: docs/deployment.md.
set -euo pipefail

P=icon-events-513902
R=us-central1
SERVICE=iconvenue
BACKUP_BUCKET="gs://$P-db-backups"
DB_NAME=iconvenue
DB_VM=iconvenue-db
DB_ZONE=us-central1-a
JOBS=(iconvenue-migrate iconvenue-reminders iconvenue-complete-expired)

cd "$(dirname "${BASH_SOURCE[0]}")/../../../.."
g() { gcloud --project "$P" --quiet "$@"; }
exists() { "$@" >/dev/null 2>&1; }
vm() { g compute ssh "$DB_VM" --zone "$DB_ZONE" --tunnel-through-iap --command "$1"; }
die() { echo "rollback: $*" >&2; exit 1; }

TAG="" RESTORE_FORCED="" YES="" RECORD_FILE="" TEST_DB=""
while [ $# -gt 0 ]; do
  case "$1" in
    --restore-db) RESTORE_FORCED=1 ;;
    --yes) YES=1 ;;
    --record) RECORD_FILE="${2:?--record needs a file}"; shift ;;
    --test-restore) TEST_DB="${2:?--test-restore needs a database name}"; shift ;;
    -h|--help) sed -n '2,17p' "${BASH_SOURCE[0]}"; exit 0 ;;
    -*) die "unknown option $1" ;;
    *) TAG="$1" ;;
  esac
  shift
done
if [ -n "$TEST_DB" ]; then
  [[ "$TEST_DB" =~ ^[A-Za-z0-9_]+$ ]] || die "invalid scratch database name"
  [ "$TEST_DB" != "$DB_NAME" ] || die "--test-restore must not target the production database"
fi
[ -z "$RECORD_FILE" ] || [ -z "$YES" ] || die "--record is for dry runs only"

# ---- Load the deploy record ----------------------------------------------------------------------------------
if [ -n "$RECORD_FILE" ]; then
  RECORD="$(cat "$RECORD_FILE")"
else
  if [ -z "$TAG" ]; then
    # Tags start with a timestamp, so the newest sorts last.
    latest="$(g storage ls "$BACKUP_BUCKET/deploys/" 2>/dev/null | sort | tail -n 1)"
    [ -n "$latest" ] || die "no deploy records in $BACKUP_BUCKET/deploys/ (records are kept 30 days)"
    TAG="$(basename "$latest" .json)"
  fi
  RECORD="$(g storage cat "$BACKUP_BUCKET/deploys/$TAG.json" 2>/dev/null)" || die "no deploy record for '$TAG'"
fi
field() { printf '%s' "$RECORD" | jq -r ".$1 // empty"; }
TAG="$(field tag)"
STATUS="$(field status)"
DEPLOYED_AT="$(field deployed_at)"
NEW_IMAGE="$(field image)"
PREV_REVISION="$(field previous_revision)"
PREV_IMAGE="$(field previous_image)"
BACKUP_OBJECT="$(field backup_object)"
MIG_BEFORE="$(field migrations_before)"
MIG_AFTER="$(field migrations_after)"
MIGRATED="$(field migrated)"

RESTORE=""
[ "$MIGRATED" = true ] && RESTORE=1
[ -n "$RESTORE_FORCED" ] && RESTORE=1

# ---- Restore helper: stream the dump to the VM, verify it there, then (and only then) replace <db> ------------
restore_db() { # target database, expected migration count
  local target="$1" expect="$2" local_dump remote b64
  local_dump="$(mktemp)"
  g storage cp "$BACKUP_OBJECT" "$local_dump" >/dev/null 2>&1 || { rm -f "$local_dump"; die "could not download $BACKUP_OBJECT"; }
  gzip -t "$local_dump" || { rm -f "$local_dump"; die "backup is corrupt: $BACKUP_OBJECT"; }
  # Runs on the VM as root. The whole dump lands in a temp file and is checked before the database is dropped,
  # so a broken transfer never leaves an empty database. The DB-level grant for iconvenue@10.128.% survives the drop.
  remote="$(cat <<'REMOTE'
set -euo pipefail
db="$1"; expect="$2"
f="$(mktemp)"; trap 'rm -f "$f"' EXIT
cat > "$f"
gzip -t "$f"
zcat "$f" | tail -n 1 | grep -q 'Dump completed' || { echo "restore: dump incomplete" >&2; exit 1; }
mariadb -e "DROP DATABASE IF EXISTS \`$db\`; CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c "$f" | mariadb "$db"
tables="$(mariadb -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db'")"
migs="$(mariadb -N -B "$db" -e 'SELECT COUNT(*) FROM migrations')"
echo "RESTORE tables=$tables migrations=$migs"
[ "$tables" -gt 0 ] || { echo "restore: no tables after restore" >&2; exit 1; }
[ -z "$expect" ] || [ "$migs" = "$expect" ] || { echo "restore: migrations $migs, expected $expect" >&2; exit 1; }
REMOTE
)"
  b64="$(printf '%s' "$remote" | base64 | tr -d '\n')"
  vm "f=\$(mktemp) && echo $b64 | base64 -d > \$f && sudo bash \$f $target '$expect'; rc=\$?; rm -f \$f; exit \$rc" \
    < "$local_dump" || { rm -f "$local_dump"; die "restore into '$target' failed"; }
  rm -f "$local_dump"
}

if [ -n "$TEST_DB" ]; then
  [ -n "$BACKUP_OBJECT" ] || die "record has no backup object"
  echo "==> Test restore of $BACKUP_OBJECT into scratch database '$TEST_DB' (production untouched)"
  restore_db "$TEST_DB" "$MIG_BEFORE"
  vm "sudo mariadb -e 'DROP DATABASE IF EXISTS \`$TEST_DB\`'" >/dev/null
  echo "==> Test restore OK; '$TEST_DB' dropped"
  exit 0
fi

# ---- Plan -------------------------------------------------------------------------------------------------------
[ -n "$PREV_REVISION" ] && [ -n "$PREV_IMAGE" ] || die "record $TAG has no previous revision/image (first deploy?)"
CURRENT_REVISION=""
if [ -z "$RECORD_FILE" ]; then
  CURRENT_REVISION="$(g run services describe "$SERVICE" --region "$R" --format 'value(status.traffic[0].revisionName)')"
  exists g run revisions describe "$PREV_REVISION" --region "$R" || die "previous revision $PREV_REVISION no longer exists"
  if [ -n "$RESTORE" ]; then
    exists g storage ls "$BACKUP_OBJECT" || die "backup $BACKUP_OBJECT is gone (backups are kept 30 days); cannot restore"
  fi
fi

echo "Rollback plan for deploy $TAG (deployed $DEPLOYED_AT, status: $STATUS)"
echo "  Code:     ${CURRENT_REVISION:-<current>} → $PREV_REVISION"
echo "            image $NEW_IMAGE → $PREV_IMAGE (service and jobs: ${JOBS[*]})"
if [ -n "$RESTORE" ]; then
  why="the deploy applied migrations ($MIG_BEFORE → ${MIG_AFTER:-unknown})"
  [ -n "$RESTORE_FORCED" ] && [ "$MIGRATED" != true ] && why="--restore-db was given"
  echo "  Database: RESTORE $BACKUP_OBJECT, because $why"
  echo "            Every booking, payment and other change made since $DEPLOYED_AT will be LOST."
  echo "            A safety backup (prerollback-$TAG) is taken first, so this can be undone."
else
  echo "  Database: kept as is (the deploy applied no migrations: $MIG_BEFORE → $MIG_AFTER). No data is lost."
fi
if [ -n "$CURRENT_REVISION" ] && [ "$CURRENT_REVISION" = "$PREV_REVISION" ]; then
  echo "  Note: $PREV_REVISION is already serving; the code part is a no-op."
fi

if [ -z "$YES" ]; then
  echo
  echo "Dry run: nothing changed. Re-run with --yes to execute."
  exit 0
fi

# ---- Execute ----------------------------------------------------------------------------------------------------
echo "==> Safety backup of the current database"
out="$(vm "sudo bash -s -- prerollback-$TAG" < deploy/backup-db.sh)" || { echo "$out" >&2; die "safety backup failed; nothing changed"; }
SAFETY_OBJECT="$(printf '%s\n' "$out" | tr -d '\r' | sed -n 's/^BACKUP_OBJECT=//p' | tail -n 1)"
echo "    $SAFETY_OBJECT"

echo "==> Traffic → $PREV_REVISION"
g run services update-traffic "$SERVICE" --region "$R" --to-revisions "$PREV_REVISION=100" >/dev/null

echo "==> Jobs → $PREV_IMAGE"
for job in "${JOBS[@]}"; do
  if exists g run jobs describe "$job" --region "$R"; then
    g run jobs update "$job" --region "$R" --image "$PREV_IMAGE" >/dev/null
    echo "    $job"
  fi
done

if [ -n "$RESTORE" ]; then
  echo "==> Restoring $BACKUP_OBJECT into $DB_NAME"
  restore_db "$DB_NAME" "$MIG_BEFORE"
fi

echo "==> Health check"
PUBLIC_HOST="$(sed -n 's/^APP_PUBLIC_HOST: *"\{0,1\}\([^"]*\)"\{0,1\}$/\1/p' deploy/cloudrun.env.yaml)"
TS_CLI="$(command -v tailscale || echo /Applications/Tailscale.app/Contents/MacOS/Tailscale)"
code=""
if [ -n "$PUBLIC_HOST" ] && [ -x "$TS_CLI" ] && "$TS_CLI" status >/dev/null 2>&1; then
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "https://$PUBLIC_HOST/up" || true)"
elif [ -n "$PUBLIC_HOST" ]; then
  # This Mac isn't on the tailnet: check from the gateway VM itself.
  code="$(vm "H=$PUBLIC_HOST; IP=\$(tailscale ip -4); curl -s -o /dev/null -w '%{http_code}' --max-time 30 --resolve \$H:443:\$IP https://\$H/up" \
    2>/dev/null | tr -d '\r' | tail -n 1 || true)"
fi
echo "    /up: HTTP ${code:-unknown}"

echo
echo "==> Rolled back deploy $TAG. Serving $PREV_REVISION."
[ -n "$RESTORE" ] && echo "    Database restored from $BACKUP_OBJECT."
echo "    Undo this rollback: restore $SAFETY_OBJECT and route traffic back to ${CURRENT_REVISION:-the newer revision}."
echo "    The next deploy/cloudrun-deploy.sh moves traffic to the new build as usual."
[ "${code:-}" = 200 ] || die "health check did not return 200; check the logs (docs/deployment.md → Operations)"
