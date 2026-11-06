#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
  php artisan key:generate --ansi
fi

echo "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until php -r "exit(@fsockopen('${DB_HOST:-mysql}', ${DB_PORT:-3306}) ? 0 : 1);" 2>/dev/null; do
  sleep 1
done
echo "Database is up."

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  [ -L public/storage ] || php artisan storage:link

  if [ ! -f storage/.seeded ]; then
    php artisan db:seed --force
    touch storage/.seeded
  fi
fi

exec "$@"
