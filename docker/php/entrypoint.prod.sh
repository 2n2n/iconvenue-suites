#!/bin/sh
# Production entrypoint for the Cloud Run service and jobs. Never seeds: the first admin
# comes from `php artisan users:create-admin` run as a Cloud Run Job (docs/deployment.md).
set -eu

cd /var/www/html

fail() {
  echo "entrypoint.prod: $*" >&2
  exit 1
}

[ "${APP_ENV:-}" = "production" ] || fail "APP_ENV must be 'production' (got '${APP_ENV:-}'). Refusing to start."
case "${APP_KEY:-}" in
  base64:*) ;;
  *) fail "APP_KEY is missing. Generate one (docs/deployment.md) and put it in .env." ;;
esac

echo "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
tries=0
until php -r "exit(@fsockopen('${DB_HOST:-mysql}', ${DB_PORT:-3306}) ? 0 : 1);" 2>/dev/null; do
  tries=$((tries + 1))
  # Direct VPC egress can take a minute or more to come up on a cold start.
  [ "$tries" -lt 150 ] || fail "database not reachable after 150s"
  sleep 1
done
echo "Database is up."

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

# Behind the tailnet gateway (tailscale serve → nginx → Cloud Run) the request arrives with the
# run.app Host. Tell Laravel the public host so links and post-login redirects use it; trustProxies('*')
# trusts this header because Apache adds it after the request reaches the container.
if [ -n "${APP_PUBLIC_HOST:-}" ] && [ "$(id -u)" = "0" ]; then
  case "$APP_PUBLIC_HOST" in
    *[!A-Za-z0-9.-]*) fail "APP_PUBLIC_HOST must be a bare hostname (got '$APP_PUBLIC_HOST')" ;;
  esac
  cat > /etc/apache2/conf-enabled/zz-public-host.conf <<CONF
RequestHeader set X-Forwarded-Host "${APP_PUBLIC_HOST}"
RequestHeader set X-Forwarded-Proto "https"
CONF
fi

# Routes, views and events are cached in the image (Dockerfile.prod). Config is cached here
# because it holds the secrets Cloud Run injects at start, which must never be baked into the image.
php artisan config:cache

# Files written by the root-run commands above must stay writable for Apache (www-data).
# storage/app/public is the Cloud Storage mount on Cloud Run: its ownership comes from the mount
# options (uid/gid 33), and a recursive chown there would walk the whole bucket, so leave it out.
if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data storage/framework storage/logs bootstrap/cache
  chown www-data:www-data storage storage/app
fi

exec "$@"
