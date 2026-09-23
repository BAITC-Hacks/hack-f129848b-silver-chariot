#!/bin/sh
set -e
cd /var/www/html

DB_FILE="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
mkdir -p "$(dirname "$DB_FILE")"
[ -f "$DB_FILE" ] || touch "$DB_FILE"
export DB_CONNECTION=sqlite DB_DATABASE="$DB_FILE"

if [ -z "$APP_KEY" ]; then
    KEY_FILE="$(dirname "$DB_FILE")/.app-key"
    if [ ! -s "$KEY_FILE" ]; then
        (umask 077; php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE")
    fi
    export APP_KEY="$(cat "$KEY_FILE")"
fi

php artisan migrate --force
IMPORT_MARKER="$(dirname "$DB_FILE")/.dataset-imported"
if [ ! -f "$IMPORT_MARKER" ]; then
    php artisan data:import --path=docs/case_1/career_quest_dataset
    touch "$IMPORT_MARKER"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
