# Career Quest

Laravel 13 / PHP 8.4+, SQLite, Blade + Tailwind. Snapshot данных: **2026-10-01**.

## Запуск

### Docker (одна команда)

```sh
cp .env.example .env   # один раз; вписать OPENAI_API_KEY (или ANTHROPIC_API_KEY + LLM_DRIVER=anthropic)
docker compose up --build
```

Ключи берутся из `.env` автоматически. Без ключа тоже работает: рекомендации выдаёт детерминированный движок (fallback).

Сервер доступен на `http://localhost:8000/employees`. SQLite хранится в томе
`sqlite_data`; миграции запускаются автоматически, стартовый датасет импортируется
один раз. Перезапуск сохраняет прогресс. Для обновления исходного датасета вручную:
`docker compose exec app php artisan data:import` (заменяет загруженные оценки навыков).

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
- POST complete принимает `event_id`, возвращает ровно контракт из PLAN.md. Повторное завершение запрещено с 422, кроме EV_036. Прирост ограничен gain, max_level и 5; уже достигнутый уровень не снижается. Для Lead следующего грейда нет: `grade: null, covered: 0, total: 0`.
- POST recommendations возвращает **200** с 1–3 рекомендациями и `source=llm|fallback`; без доступного полезного шага — `{"recommendations": []}`. Последний ответ сохраняется в БД и заменяется при новом запросе; complete очищает устаревшие рекомендации.
- `HrAnalyticsService::employeesWithoutNextStep()` использует тот же движок, что и рекомендации, включая расписание, историю и положительный вклад в навыки. Внешний LLM для HR не вызывается.
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

## AI-слой участника B

Проверка на стартовом датасете без Docker и базы (нужны PHP 8.4+ и `composer install`):

```sh
php artisan ai:recommend E0028 --offline
```

Команда возвращает JSON по контракту `PLAN.md`. Другая папка с данными:
`php artisan ai:recommend E0028 --dataset=path/to/dataset --offline`.
Без `--offline` команда обращается к выбранному LLM-провайдеру.

В локальном `.env` задаются `LLM_DRIVER=openai`, `OPENAI_API_KEY`,
`OPENAI_BASE_URL`, `OPENAI_MODEL=gpt-4o-mini`, `LLM_TIMEOUT=8`.
Для Anthropic: `LLM_DRIVER=anthropic` и соответствующие `ANTHROPIC_*`.
`LLM_DRIVER=disabled` полностью отключает сеть. Ключи не должны попадать в Git.
Для OpenAI-compatible сервера без JSON Schema: `OPENAI_STRUCTURED_OUTPUTS=false`;
локальная проверка ответа сохраняется. Повторных запросов нет, таймаут ограничен
8 секундами даже при большем значении в `.env`.

### Контракт сервисов

```php
$result = app(\App\Services\LlmService::class)->recommend(
    employee: $employeeData,
    events: $eventRows,
    roleProfiles: $roleProfileRows,
    history: $activityRows,
    skillCatalog: $skillRows,
    asOf: '2026-10-01',
    skillsAlreadyCurrent: false,
);
// $result === ['recommendations' => [...]]
```

Все аргументы — массивы, поля совпадают с JSON/CSV датасета; для моделей
используйте `toArray()` с приведёнными к массивам JSON-полями.
Сервисы ничего не записывают в БД. Контроллер сохраняет ответ и обеспечивает
доступ сотрудника только к своему профилю.

`skillsAlreadyCurrent=false` означает исходные навыки на `last_review_date`:
`SkillProjector` добавляет завершения после этой даты в хронологическом порядке,
не меняя исходный профиль. Завершения, уже включённые `ProgressService` в
сохранённые навыки, помечаются внутренним `skills_applied=true` и пропускаются
при начислении. Поле не входит в CSV. Повторный импорт оценки сотрудника
сбрасывает эти отметки, чтобы проекция строилась от новой оценки.
Для внешнего вызова с полностью актуальными навыками можно передать
`skillsAlreadyCurrent=true`. История всё равно используется для фильтров и скоринга.
При наличии `completed_at` используется эта дата; иначе — `date` из датасета.

Для HR можно вызвать `RecommendationEngine::analyze()` с теми же аргументами:
он вернёт актуальные навыки, разрывы, сводку истории и полный список кандидатов.
Пустой список означает отсутствие доступного полезного шага; искусственные
рекомендации для заполнения трёх карточек не создаются.

### Правила выбора и объяснения

- Исключаются обязательные, неподходящие по роли/грейду, недоступные по
  prerequisites, завершённые (кроме `EV_036`) и уже начатые активности.
- У событий по расписанию должна быть сессия не раньше даты среза;
  `self_paced` доступен всегда. Исключаются события без полезного прироста
  к требованиям следующего грейда или карьерной цели.
- Реальный прирост: `max(0, min(gain, max_level - current, 5 - current))`.
  Для Lead используются требования Lead, без вымышленного следующего грейда.
- Вес закрытия разрыва — 4 за уровень, критичного навыка — дополнительно 3,
  карьерной цели — дополнительно 2. Штрафы за `no_show/declined/dropped`:
  4 за то же событие, 2 за другое с общими навыками, 0.5 только за общий тип.
  Одна запись получает только один штраф; обязательные события не штрафуют.
- Завершённые добровольные события с общими навыками дают до 1 балла,
  подтверждённые завершения в срок — до 0.5. Последнее требует `completed_at`
  и `due_date`: CSV не даёт оснований считать дату зачисления датой завершения.
- Доступность даёт 1 балл для `self_paced`, иначе `1 / (1 + days / 30)`.
  При равенстве баллов порядок определяется `event_id`.
- LLM получает обезличенный профиль, разрывы, сводку истории и топ-8.
  Первый кандидат движка обязателен; LLM выбирает до двух дополнительных.
  Для каждого шага выбираются минимум три проверяемых фактора: грейд, разрыв
  и история; критичный навык также обязателен, если он есть.
- Обоснование собирается из точных русских фраз выбранных факторов.
  Локальная проверка отклоняет выдуманные числа, неизвестные события,
  дубликаты, пропущенные факторы, отказ модели и обрезанный JSON.
  При любой ошибке провайдера возвращается топ-3 движка с `source=fallback`.

OpenAI использует [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs),
Anthropic — [Messages API с инструментом](https://platform.claude.com/docs/en/api/messages/create).
В журнал пишутся только категория сбоя и HTTP-статус, без ключей и ответов провайдера.

### Проверка AI-слоя

```sh
php artisan test --compact tests/Unit/RecommendationEngineTest.php tests/Feature/LlmServiceTest.php tests/Feature/RecommendEmployeeCommandTest.php tests/Feature/RecommendationIntegrationTest.php
```

Тесты отключают реальную сеть. Покрыты антипример из ТЗ, граничные условия,
оба провайдера, невалидные ответы, ошибки связи и все 200 профилей стартового кита.
Реальный OpenAI-запрос отдельно проверен на искусственном примере без данных
из стартового кита; Anthropic проверен через имитацию HTTP-ответов.
