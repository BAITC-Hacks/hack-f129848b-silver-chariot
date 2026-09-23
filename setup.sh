#!/usr/bin/env bash
set -e
cd "$(dirname "$0")"

composer install --no-interaction --prefer-dist
npm install --no-audit --no-fund

[ -f .env ] || cp .env.example .env
php artisan key:generate --force --quiet

[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force
php artisan data:import --path=docs/case_1/career_quest_dataset || true

npm run build

echo ""
echo "Career Quest готов. Запуск: php artisan serve"
echo "  http://127.0.0.1:8000"
