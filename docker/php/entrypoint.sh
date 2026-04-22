#!/bin/sh
set -e
cd /var/www/html

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ ! -f .env ]; then
  echo "Нет файла .env — скопируй с сервера и задай APP_KEY, APP_DOMAIN, БД, TELEGRAM_BOT_TOKEN." >&2
  exit 1
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
fi

php artisan package:discover --ansi || true
php artisan config:clear || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache || true

exec php-fpm
