# Laravel 12 + Firebase RTDB served by PHP's built-in server. No asset build
# step: the app ships plain CSS/JS, so there is no npm stage.
FROM php:8.4-fpm

# GD needs its JPEG/PNG/WebP/freetype libraries at compile time: proof
# screenshots and catalog images are re-encoded server-side with GD.
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd mbstring bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/exports \
        storage/logs \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Render routes traffic to $PORT (default 10000) on 0.0.0.0.
EXPOSE 10000

ENTRYPOINT ["/bin/sh", "/var/www/docker/entrypoint.sh"]
