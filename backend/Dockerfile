FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip libzip-dev libpng-dev \
    && docker-php-ext-install bcmath gd pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
