FROM php:8.3-cli-alpine
RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev mysql-client && docker-php-ext-install pdo_mysql mbstring intl zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
CMD php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080