# Install PHP dependencies (shared stage for Ziggy + production)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Build frontend assets
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Production image
FROM php:8.3-fpm-alpine AS app

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    && docker-php-ext-install \
    pdo_mysql \
    zip \
    intl \
    mbstring \
    pcntl \
    opcache \
    && rm -rf /var/cache/apk/*

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Copy vendor from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy built frontend assets from the frontend stage
COPY --from=frontend /app/public/build ./public/build

# Generate optimized autoloader
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# PHP production config
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "upload_max_filesize=100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 8080

# Entrypoint: run migrations, cache config, then start supervisor
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
