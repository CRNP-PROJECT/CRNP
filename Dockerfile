# Step 1: PHP & Dependencies
FROM php:8.2-fpm as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application code
COPY . /var/www

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Install NPM dependencies & build Vite/Tailwind assets
RUN npm install
RUN npm run build

# Expose port and start Laravel server
EXPOSE 8080
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8080}