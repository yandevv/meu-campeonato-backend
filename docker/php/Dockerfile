# =============================================================================
# Stage 1: Install Composer dependencies
# =============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# =============================================================================
# Stage 2: PHP-FPM production image
# =============================================================================
FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    python3 \
    curl \
    libpq \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-libs \
    && apk add --no-cache --virtual .build-deps \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pgsql \
    bcmath \
    gd \
    zip \
    opcache \
    pcntl \
    intl \
    && apk del .build-deps

# Copy composer binary from builder stage
COPY --from=vendor /usr/bin/composer /usr/bin/composer

# Copy configuration files
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy application code
COPY --chown=www-data:www-data . .

# Copy vendor from builder stage
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

# Clear stale bootstrap cache and re-discover packages (excludes dev-only providers like Boost)
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi \
    && composer dump-autoload --optimize --no-dev --no-interaction

# Set storage and cache permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm", "-F"]
