#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ not found — running composer install..."
    composer install --no-interaction --prefer-dist
fi

if [ -n "$DB_HOST" ]; then
    echo "Waiting for database..."
    until php -r "
        try {
            new PDO(
                'pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD')
            );
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        sleep 2
    done
fi

php artisan migrate --force --no-interaction

if [ "${REPROCESS_STUCK_ON_START:-true}" = "true" ]; then
    php artisan documents:reprocess-stuck --no-interaction 2>/dev/null || true
fi

exec "$@"
