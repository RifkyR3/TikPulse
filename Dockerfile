# ==============================================================================
# Stage 1: Build Frontend Assets (Svelte 5 + Inertia.js v3 + Vite + Tailwind CSS v4)
# ==============================================================================
FROM node:26-alpine AS frontend-builder
WORKDIR /app

RUN npm install -g corepack@latest && corepack enable && corepack prepare pnpm@^10.12 --activate

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml* ./
RUN pnpm install --frozen-lockfile

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.ts tsconfig.json svelte.config.js ./
RUN pnpm run build

# ==============================================================================
# Stage 2: Install Composer Dependencies
# ==============================================================================
FROM composer:2 AS composer-builder
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# ==============================================================================
# Stage 3: Production PHP Runtime (PHP 8.5 FPM Alpine)
# ==============================================================================
FROM php:8.5-fpm-alpine AS final

LABEL maintainer="TikPulse Team"
LABEL description="TikPulse Enterprise MikroTik Voucher Management System"

WORKDIR /var/www/html

# Install mlocati docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install system utilities and PHP extensions via docker-php-extension-installer
RUN apk add --no-cache curl unzip busybox-extras \
    && install-php-extensions \
        pdo_pgsql \
        pgsql \
        bcmath \
        sockets \
        pcntl \
        zip \
        intl \
        redis

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "memory_limit=512M" >> "$PHP_INI_DIR/conf.d/tikpulse.ini" \
    && echo "upload_max_filesize=64M" >> "$PHP_INI_DIR/conf.d/tikpulse.ini" \
    && echo "post_max_size=64M" >> "$PHP_INI_DIR/conf.d/tikpulse.ini" \
    && echo "max_execution_time=120" >> "$PHP_INI_DIR/conf.d/tikpulse.ini" \
    && echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/tikpulse.ini" \
    && echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/tikpulse.ini"

# Copy application source code
COPY . .

# Copy vendor from composer-builder stage
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# Copy built frontend assets from frontend-builder stage
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Setup storage, bootstrap cache permissions, and entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
