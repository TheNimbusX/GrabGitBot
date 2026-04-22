#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
  echo "Нет файла .env — скопируй и задай APP_KEY, БД, TELEGRAM_BOT_TOKEN." >&2
  exit 1
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
fi

exec php artisan telegram:poll "$@"
