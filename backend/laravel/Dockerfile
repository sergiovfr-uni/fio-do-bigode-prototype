FROM php:8.3-cli-alpine
RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev mysql-client && docker-php-ext-install pdo_mysql mbstring intl zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private storage/app/public storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
CMD php artisan config:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
