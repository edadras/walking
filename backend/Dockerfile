# syntax=docker/dockerfile:1.7
# Gamyar API + panels. One image, several roles (php-fpm, horizon, scheduler).

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache icu-libs libzip fcgi \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath pcntl opcache zip \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del .build

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-gamyar.ini
COPY deploy/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-gamyar.conf

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=vendor /usr/bin/composer /usr/bin/composer
# Autoloader + package discovery + Filament/Horizon assets (no DB needed).
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && php artisan package:discover --ansi \
 && php artisan filament:assets \
 && php artisan horizon:publish \
 && php artisan storage:link --relative \
 && rm /usr/bin/composer \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
HEALTHCHECK --interval=30s --timeout=5s CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
CMD ["php-fpm"]

# Static files + reverse proxy, built from the same sources so assets always match the code.
FROM nginx:1.27-alpine AS web
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
