#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    export APP_KEY="base64:$(php -r "echo base64_encode(random_bytes(32));")"
fi

DB_FILE="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
mkdir -p "$(dirname "$DB_FILE")"
[ -f "$DB_FILE" ] || touch "$DB_FILE"

php artisan migrate --force
php artisan data:import --path=docs/case_1/career_quest_dataset || echo "dataset import skipped (already imported or missing)"

exec php artisan serve --host=0.0.0.0 --port=8000
