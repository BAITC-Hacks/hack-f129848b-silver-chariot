# Career Quest

Laravel 13 / PHP 8.4+, SQLite, Blade + Tailwind. Snapshot данных: **2026-10-01**.

## Запуск

### Docker (одна команда)

```sh
ANTHROPIC_API_KEY=sk-... docker compose up --build
# или с OpenAI:
LLM_DRIVER=openai OPENAI_API_KEY=sk-... docker compose up --build
```

Приложение: http://localhost:8000 — миграции и импорт датасета выполняются автоматически при старте контейнера. Без LLM-ключа тоже работает: рекомендации выдаёт детерминированный движок (fallback).

### Локально

Требуются PHP с SQLite, Composer, Node.js и npm.

```sh
./setup.sh
php artisan serve
```

Откройте `/employees`. Без сборки frontend минимальные страницы трека A также работают:

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan data:import
php artisan serve
```

Повторный `data:import` обновляет записи по первичному ключу; role_profiles — по паре `(role, grade)`. Это merge исходных данных, поэтому навыки существующих сотрудников заменяются значениями из загруженного профиля. Импорт истории сам по себе не начисляет навыки повторно.

## Сценарий для жюри

1. В режиме «Сотрудник» откройте `/employees/E0001`: профиль, разрывы до Middle и история. Сотрудник по умолчанию — E0001; другой профиль недоступен. Идентификатор хранится в сессии как `employee_id`, роль — `role` (`employee` / `hr`). Это демонстрационная модель без паролей.
2. Выберите EV_036 и нажмите «Отметить выполненной». Ответ JSON содержит `skill_deltas` и `grade_readiness`. История пополняется завершением от 2026-10-01; обновлённый профиль доступен после возврата на страницу.
3. Переключитесь в HR через форму в шапке. Теперь доступны все сотрудники, поиск по имени/роли/грейду, `/hr` и `/admin/upload`.
4. Загрузите `employees.json` и/или `activity_history.csv`. Профили имеют оболочку `{"employees": [...]}`; CSV — заголовок как в стартовом ките. Новые записи добавляются, существующие обновляются по ID. Обе загрузки выполняются одной транзакцией; ошибка отменяет весь merge. Сначала импортируйте стартовый каталог, затем проверочные данные.
5. Откройте профиль загруженного сотрудника в HR и проверьте историю и разрывы. HR-аналитика пересчитывается при каждом запросе.

CLI для отдельных файлов или каталога с любым подмножеством четырёх файлов:

```sh
php artisan data:import --path=/path/to/jury-data
php artisan data:import --path=/path/to/employees.json
php artisan data:import --path=/path/to/activity_history.csv
```

## Контракты для интеграции B / C

- Именованные роуты: `employees.index`, `employees.show`, `employees.recommendations`, `employees.complete`, `hr.index`, `admin.upload`, `admin.upload.store`, `session.role`.
- POST complete принимает `event_id`, возвращает ровно контракт из PLAN.md. Повторное завершение запрещено с 422, кроме EV_036. Применяется заданная формула `min(level + gain, max_level, 5)`. Для Lead следующего грейда нет: `grade: null, covered: 0, total: 0`.
- POST recommendations пока возвращает **501** и `{"recommendations": []}`. Подключение движка — трек B.
- `HrAnalyticsService::employeesWithoutNextStep()` пока проверяет mandatory, роль, грейд, prerequisites и завершения (с исключением EV_036). При интеграции заменить источник кандидатов движком B.
- GET списка, профиля и HR поддерживают `Accept: application/json`. Ключи профиля: `employee`, `gaps`, `grade_readiness`, `history`, `skills`, `events`; HR: `skill_gaps`, `employees_without_next_step`, `participation`.
- Поля multipart-загрузки: `employees`, `activity_history`. Ответ JSON: `{"imported": {"employees": 2, "activity_records": 3}}` с ключами фактически загруженных файлов.
- Все POST используют стандартную CSRF-защиту Laravel: cookie сессии + `X-CSRF-TOKEN` из meta-тега страницы либо поле `_token`. POST `/session/role` принимает `role=employee|hr`.
- JSON-поля skills/required_skills — карты `skill_id → level`. Eloquent-связи: `activityRecords`, `recommendations`, `manager`, `reports`, `employee`, `event`. Для составного ключа роли используются методы `Employee::roleProfile()` и `nextGradeProfile()`, возвращающие модель или null.
- Blade-шаблоны минимальны, оформление и клиентский complete-flow остаются треку C.

## Проверка

Команда `migrate:fresh` удаляет локальные данные:

```sh
php artisan migrate:fresh --force
php artisan data:import
php artisan test --compact
```

Стартовые количества: **60 skills, 32 role_profiles, 200 employees, 40 events, 2743 activity_records**. Тесты используют отдельную SQLite in-memory базу; проверяют повторный импорт, частичный merge, откат ошибочной загрузки, прогресс, повторные завершения, роли, HTTP-контракты и HR-агрегации.
