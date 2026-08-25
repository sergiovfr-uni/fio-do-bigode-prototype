FROM php:8.3-cli-alpine
RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev mysql-client libpng-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip gd
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private storage/app/public storage/logs \
    && chmod -R 775 bootstrap/cache storage
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod +x start.sh
EXPOSE 8080
CMD ["./start.sh"]
