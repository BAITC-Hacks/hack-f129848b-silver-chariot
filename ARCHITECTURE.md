# Career Quest — Architecture

HackAlem AI · трек Halyk Bank · Кейс 1: AI-навигатор развития сотрудника.

## Стек

- **Laravel 13 (PHP 8.4+) + Blade + Tailwind CSS (Vite)** — фактические зависимости проекта
- **SQLite** — один файл, ноль инфраструктуры, запуск одной командой
- **LLM** — OpenAI-compatible API (`gpt-4o-mini`) или Anthropic-compatible эндпоинт; драйвер выбирается через `.env`. При недоступности LLM — fallback на детерминированное обоснование

## Слои

```
HTTP (routes/web.php)
 ├─ EmployeeController        GET /employees, GET /employees/{id}
 ├─ RecommendationController  POST /employees/{id}/recommendations
 ├─ CompletionController      POST /employees/{id}/complete
 ├─ HrController              GET /hr/*            (middleware: role hr)
 └─ UploadController          GET|POST /admin/upload

Domain (app/Services)
 ├─ RecommendationEngine   детерминированный скоринг кандидатов (ядро качества)
 ├─ LlmService             выбор 1–3 событий + обоснование; валидация; fallback
 ├─ ProgressService        применение gain/max_level, запись в историю, дельта прогресса
 ├─ HrAnalyticsService     проседающие навыки, сотрудники без шага, участие
 └─ DatasetImporter        стартовый кит и проверочные данные жюри → SQLite
```

## Сущности (SQLite, 1:1 со схемой стартового кита)

| Таблица | Поля | Связи |
|---|---|---|
| `skills` | skill_id (pk), name, type, category, description | — |
| `role_profiles` | id, role, grade, required_skills json, critical_skills json | required_skills → skills |
| `employees` | employee_id (pk), full_name, department, role, grade, manager_id, hire_date, tenure_months, work_format, preferred_language, career_goal json, skills json, last_review_date | (role, grade) → role_profiles; manager_id → employees |
| `events` | event_id (pk), title, description, type, format, duration_hours, mandatory, target_roles json, target_grades json, develops_skills json, prerequisites json, upcoming_sessions json | develops_skills → skills |
| `activity_records` | record_id (pk), employee_id, event_id, date, due_date, status, completion_pct, score, feedback_rating, assigned_by, skills_applied (внутренний флаг) | → employees, → events |
| `recommendations` | id, employee_id, event_id, rank, score, factors json, rationale, source (llm/fallback), created_at | → employees, → events |

`recommendations` — единственная производная сущность: кэш ответа AI-слоя для воспроизводимости на защите.

Уровни навыков: 0–5 по `proficiency_scale`; отсутствующий навык = 0. Snapshot date: `2026-10-01` (все «будущие» сессии считаются от неё).

## Рекомендация — главный поток

```
1. Кандидаты (жёсткие фильтры):
   mandatory=false · role ∈ target_roles · grade ∈ target_grades
   · prerequisites выполнены · нет completed в истории (кроме EV_036)
   · нет in_progress · есть будущая сессия или self_paced
   · есть положительный вклад в требования грейда или карьерной цели

2. Скоринг каждого кандидата (RecommendationEngine):
   + вклад в разрывы до следующего грейда:
     Σ max(0, min(gain, gap, max_level - current, 5 - current)) по develops_skills
     (gap = required_next_grade - current)
   + бонус за critical_skills следующего грейда
   + бонус за совпадение с career_goal (target_role/target_grade)
   − штраф за no_show/declined/dropped на этом событии или его типе
   + бонус за завершения похожих активностей и подтверждённые завершения в срок
   + бонус за ближайшую upcoming_session / self_paced

3. LLM (LlmService):
   промпт: профиль, разрывы, история, топ-8 кандидатов с факторами
   → strict JSON {recommendations: [{event_id, rationale, factors}]} (1–3 шт)
   валидация event_id против кандидатов; обоснование ≥3 подтверждённых факторов
   первый кандидат движка сохраняется; rationale состоит из выбранных evidence
   таймаут/ошибка/невалидный ответ → fallback: топ-3 движка
   + шаблонное обоснование из factors
```

Лимиты по ТЗ: отклик интерфейса < 2 с, AI-рекомендация < 10 с (LLM-таймаут 8 с).

Реализованный AI-слой принимает массивы и не зависит от наличия моделей/БД.
`SkillProjector` рассчитывает навыки с учётом завершений после последней оценки.
Записи с `skills_applied=true` уже включены в сохранённые навыки и повторно
не начисляются. `ProgressService` сохраняет проекцию и новые приросты одной
транзакцией; повторный импорт оценки сбрасывает флаги для этого сотрудника.
Для уже актуализированных навыков вызывающая сторона передаёт
`skillsAlreadyCurrent=true`, чтобы избежать двойного начисления.
У Lead используются требования текущего грейда. Без подходящих кандидатов
возвращается пустой список без обращения к LLM. Точные веса и контракт — в README.
Своевременность нельзя вывести из даты зачисления: бонус требует явного
`completed_at` вместе с `due_date`, которых нет вместе в стартовом CSV.

## Обновление прогресса

`ProgressService.complete(employee, event)`:
для каждого `{skill_id, gain, max_level}` из `develops_skills`:
`level = level + max(0, min(gain, max_level - level, 5 - level))` → запись `activity_records(status=completed, completion_pct=100)` → пересчёт разрывов → UI показывает дельту по навыкам и сдвиг по траектории. Потолок события не должен снижать уже достигнутый уровень.

## HR-аналитика

- **Проседающие навыки**: агрегация `max(0, required_next − current)` по всем сотрудникам → топ навыков
- **Без рекомендованного шага**: сотрудники, у которых движок вернул 0 кандидатов
- **Участие**: по каждому событию — completed / no_show / dropped / declined rates

## Роли и приватность

- Сессионный переключатель «Сотрудник / HR» (без паролей — хакатон)
- Middleware `role:hr` на `/hr/*`; сотрудник видит только свой профиль
- Данные о вовлечённости одного сотрудника не показываются другим

## Структура репозитория

```
├── app/
│   ├── Http/Controllers/   Employee, Recommendation, Completion, Hr, Upload
│   ├── Services/           RecommendationEngine, LlmService, ProgressService,
│   │                       HrAnalyticsService, DatasetImporter
│   └── Models/             Employee, Skill, RoleProfile, Event, ActivityRecord, Recommendation
├── resources/views/        layout, employees/, hr/, admin/
├── routes/web.php
├── database/migrations/
├── setup.sh                composer install → .env → migrate → data:import → serve
└── docs/                   ТЗ и стартовый кит
```

## Конфигурация (.env)

```
DB_CONNECTION=sqlite
LLM_DRIVER=openai             # openai | anthropic | disabled
OPENAI_API_KEY=               # при выборе openai
OPENAI_MODEL=gpt-4o-mini
ANTHROPIC_API_KEY=            # при выборе anthropic
ANTHROPIC_MODEL=claude-haiku-...
LLM_TIMEOUT=8
```

## Принципы

1. **Движок правильный без LLM** — LLM формулирует, движок решает. Защита от проверочных профилей и от упавшей сети
2. **Explainability** — каждая рекомендация показывает факторы; дельта прогресса видна при complete
3. **Ноль инфраструктуры** — `./setup.sh` и всё работает
