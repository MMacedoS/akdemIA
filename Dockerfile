# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4

FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --ignore-platform-reqs \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.ts postcss.config.js tailwind.config.js tsconfig.json components.json ./
RUN npm run build

FROM php:${PHP_VERSION}-fpm-alpine AS app
WORKDIR /var/www

RUN set -eux; \
    apk add --no-cache \
        bash \
        curl \
        icu-dev \
        libzip-dev \
        linux-headers \
        unzip \
        zip \
        $PHPIZE_DEPS; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /tmp/pear

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/php/scripts/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]
