FROM php:8.2-fpm-bookworm

# PECL "pecl install redis" often fails with "REST info xml not available" — use pinned install.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libzip-dev \
    libpng-dev \
    libpq-dev \
    libxml2-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql zip xml gd pcntl \
    && install-php-extensions redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts \
    || composer update --no-interaction --prefer-dist --no-scripts

COPY . .

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

RUN composer dump-autoload --optimize

COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
