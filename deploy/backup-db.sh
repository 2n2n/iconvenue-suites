#!/usr/bin/env bash
# Dump the production MariaDB on the DB VM and upload it to Cloud Storage.
# Installed at /opt/iconvenue-db/backup-db.sh by deploy/provision-db-vm.sh; runs nightly from
# /etc/cron.d/iconvenue-backup and on demand (`sudo /opt/iconvenue-db/backup-db.sh`).
# Uploads go over Private Google Access (the VM has no external IP). Restore: docs/deployment.md.
set -euo pipefail

DB_NAME=iconvenue
BUCKET=gs://icon-events-513902-db-backups
LOCAL_DIR=/var/backups/iconvenue
KEEP_LOCAL_DAYS=7

mkdir -p "$LOCAL_DIR"
chmod 700 "$LOCAL_DIR"
file="$LOCAL_DIR/iconvenue-$(date +%Y%m%d-%H%M%S).sql.gz"

# Root connects over the unix socket (unix_socket auth): no password on the command line.
mariadb-dump --single-transaction --routines --triggers --events "$DB_NAME" | gzip -9 > "$file"

# A dump that fails part-way still leaves a small file; refuse to upload it.
gzip -t "$file"
zcat "$file" | tail -n 1 | grep -q 'Dump completed' || { echo "backup: dump looks incomplete: $file" >&2; exit 1; }

gcloud storage cp --quiet "$file" "$BUCKET/"
find "$LOCAL_DIR" -name 'iconvenue-*.sql.gz' -mtime +"$KEEP_LOCAL_DAYS" -delete
echo "backup: uploaded $(basename "$file") to $BUCKET"
