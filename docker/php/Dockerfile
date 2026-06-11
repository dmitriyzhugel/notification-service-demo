# Stage 1 — build PHP extensions
FROM php:8.4-fpm-alpine AS builder

RUN apk add --no-cache \
    git \
    curl \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    postgresql-dev \
    icu-dev \
    linux-headers \
    autoconf \
    gcc \
    g++ \
    make

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    bcmath \
    mbstring \
    zip \
    exif \
    pcntl \
    gd \
    opcache \
    intl \
    sockets

RUN pecl install redis \
    && docker-php-ext-enable redis

# Stage 2 — runtime image
FROM php:8.4-fpm-alpine

LABEL maintainer="notification-service-demo"

RUN apk add --no-cache \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    oniguruma \
    libpq \
    icu-libs \
    curl \
    unzip \
    git

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.fast_shutdown=1"; \
} > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
    echo "upload_max_filesize=64M"; \
    echo "post_max_size=64M"; \
    echo "memory_limit=256M"; \
    echo "max_execution_time=120"; \
    echo "date.timezone=UTC"; \
} > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
