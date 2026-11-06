FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        default-mysql-client \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]

# `php artisan serve` spawns the actual PHP built-in server as a child
# process with a stripped-down environment (it only forwards a small
# allowlist of vars), so it silently ignores the DB_*/REDIS_*/MAIL_*
# variables set via docker-compose and falls back to whatever is on disk
# in .env. Invoking `php -S` directly with Laravel's router script runs
# it as PID 1 instead, inheriting the full container environment.
# The router script resolves the public path via getcwd(), so it must
# be launched with `public/` as the working directory (same as how
# artisan serve itself invokes it).
CMD ["sh", "-c", "cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
