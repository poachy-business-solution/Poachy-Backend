# =============================================================
# Stage 1 — Composer dependencies (no-dev, optimised)
# =============================================================
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --ignore-platform-reqs: composer image lacks ext-redis/pcntl/etc.
# Those extensions are installed in stage 2.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# =============================================================
# Stage 2 — PHP-FPM runtime (app container, port 9000)
# =============================================================
FROM php:8.4-fpm-alpine AS production

LABEL maintainer="Poachy"

# ---- System packages ----
RUN apk add --no-cache \
    curl \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-libs \
    libzip \
    libxml2 \
    oniguruma \
    busybox-extras

# ---- PHP extensions ----
RUN apk add --no-cache --virtual .build-deps \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev libzip-dev libxml2-dev oniguruma-dev \
        autoconf gcc g++ make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        bcmath \
        intl \
        gd \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# ---- PHP configuration ----
COPY docker/php/php.ini                  /usr/local/etc/php/php.ini
COPY docker/php/www.conf                 /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/docker-entrypoint.sh     /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

# ---- Application files ----
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer
COPY . .

# Generate optimised classmap now that app/ and all source dirs exist
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && rm /usr/local/bin/composer

# Remove dev/test directories not needed at runtime
RUN rm -rf tests/ .github/ docker/

# ---- Permissions ----
RUN chown -R www-data:www-data \
        storage/ \
        bootstrap/cache/ \
    && chmod -R 775 \
        storage/ \
        bootstrap/cache/ \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=20s \
    CMD sh -c "nc -z localhost 9000 || exit 1"

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]

# =============================================================
# Stage 3 — Nginx (nginx container, port 80)
# Copies only public/ from the production stage so nginx can
# serve static files without touching the PHP-FPM container.
# =============================================================
FROM nginx:1.27-alpine AS web

COPY docker/nginx/nginx.conf          /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/app.conf     /etc/nginx/conf.d/default.conf

COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 80
