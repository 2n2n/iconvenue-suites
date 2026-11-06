#!/usr/bin/env bash
# One-time setup of the database VM `iconvenue-db` (Debian 12, e2-micro). Safe to re-run.
# Run from your machine over IAP while the VM still has its temporary external IP (needed for apt):
#   gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap \
#     --command 'sudo bash -s' < deploy/provision-db-vm.sh
# Installs MariaDB 10.11 bound to the internal IP, tuned for 1 GB RAM, creates the app DB/user
# (password read from Secret Manager, never typed or stored in a file here), and the nightly backup cron.
# See docs/deployment.md.
set -euo pipefail

PROJECT=icon-events-513902
DB_NAME=iconvenue
DB_USER=iconvenue
DB_SECRET=iconvenue-db-password
# Cloud Run Direct VPC egress uses addresses from this subnet (us-central1 auto-mode default).
APP_SUBNET_HOST='10.128.%'
BACKUP_DIR=/opt/iconvenue-db

echo "==> Timezone (cron and logs in Manila time)"
timedatectl set-timezone Asia/Manila

echo "==> Swap (1 GB)"
if ! swapon --show | grep -q /swapfile; then
  fallocate -l 1G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Packages"
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends mariadb-server cron ca-certificates
systemctl enable --now cron

INTERNAL_IP="$(curl -fsS -H 'Metadata-Flavor: Google' \
  http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/ip)"
echo "==> MariaDB config (bind ${INTERNAL_IP}, sized for 1 GB RAM)"
cat > /etc/mysql/mariadb.conf.d/90-iconvenue.cnf <<CNF
# Installed by deploy/provision-db-vm.sh
[mysqld]
bind-address            = ${INTERNAL_IP}
skip-name-resolve
innodb_buffer_pool_size = 256M
innodb_log_file_size    = 64M
max_connections         = 50
performance_schema      = OFF
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
CNF
systemctl restart mariadb

echo "==> Database and user (password from Secret Manager: ${DB_SECRET})"
DB_PASSWORD="$(gcloud secrets versions access latest --secret="$DB_SECRET" --project="$PROJECT")"
[ -n "$DB_PASSWORD" ] || { echo "empty DB password secret" >&2; exit 1; }
# Root uses unix_socket auth on Debian, so no root password is needed or stored.
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${APP_SUBNET_HOST}' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'${APP_SUBNET_HOST}' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${APP_SUBNET_HOST}';
FLUSH PRIVILEGES;
SQL
unset DB_PASSWORD

echo "==> Backup script + nightly cron (02:00 Manila)"
install -d -m 0755 "$BACKUP_DIR"
# The backup script is piped in separately (see docs/deployment.md); install it if present.
if [ -f /tmp/backup-db.sh ]; then
  install -m 0755 /tmp/backup-db.sh "$BACKUP_DIR/backup-db.sh"
fi
cat > /etc/cron.d/iconvenue-backup <<CRON
# Installed by deploy/provision-db-vm.sh
0 2 * * * root $BACKUP_DIR/backup-db.sh >> /var/log/iconvenue-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/iconvenue-backup

echo
echo "Done. MariaDB $(mariadb -N -e 'select version()') listening on ${INTERNAL_IP}:3306."
echo "Next: remove the VM's external IP (docs/deployment.md)."
