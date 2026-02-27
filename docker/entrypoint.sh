#!/bin/sh
set -e

cd /var/www/html

# Ensure storage directories exist (no brace expansion - Alpine ash doesn't support it)
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs
mkdir -p storage/app/private/imports
mkdir -p /var/log/supervisor

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# If a command is passed (e.g. queue worker, scheduler), run it directly
if [ $# -gt 0 ]; then
    exec "$@"
fi

# === App container startup below ===

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Wait for database to be ready (up to 30 seconds)
echo "Waiting for database..."
MAX_RETRIES=15
RETRY=0
until php artisan db:monitor --databases=mysql 2>/dev/null || [ $RETRY -ge $MAX_RETRIES ]; do
    RETRY=$((RETRY + 1))
    echo "Database not ready (attempt $RETRY/$MAX_RETRIES)..."
    sleep 2
done

if [ $RETRY -ge $MAX_RETRIES ]; then
    echo "WARNING: Database not ready after ${MAX_RETRIES} attempts, continuing anyway..."
fi

# Run migrations (don't crash if DB isn't ready)
php artisan migrate --force || echo "WARNING: Migration failed, will retry on next deploy"

# Seed default data
php artisan db:seed --force || echo "WARNING: Seeding failed"

# Cache config, routes, views for production
php artisan config:cache || echo "WARNING: Config cache failed"
php artisan route:cache || echo "WARNING: Route cache failed"
php artisan view:cache || echo "WARNING: View cache failed"

# Create storage link
php artisan storage:link --force 2>/dev/null || true

echo "Starting services..."

# Start supervisor (php-fpm + nginx)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
