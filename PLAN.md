# Career Quest — План работы (3 участника)

Формат: три параллельных трека. Общий репозиторий, ветки по трекам, merge по готовности маркеров. Контракты API фиксируются до расхождения по задачам.

## Участник A — Backend / Данные

- [x] Скаффолд Laravel в корень репо, SQLite, `setup.sh` (запуск одной командой)
- [x] Миграции 6 таблиц: `skills`, `role_profiles`, `employees`, `events`, `activity_records`, `recommendations`
- [x] Модели Eloquent
- [x] `DatasetImporter` + `php artisan data:import` (все 4 файла стартового кита)
- [x] `ProgressService`: complete → применение gain/max_level → запись истории → дельта разрывов
- [x] `HrAnalyticsService`: проседающие навыки, сотрудники без шага, участие по событиям
- [x] Контроллеры и роуты: профиль, recommendations, complete, HR, upload
- [x] `/admin/upload` — merge проверочных `employees.json` + `activity_history.csv` от жюри
- [x] Middleware ролей (сотрудник/HR, сессионный переключатель)
- [x] Docker: Dockerfile + docker-compose.yml (развёртывание одной командой)
- [x] README: запуск, сценарий для жюри

## Участник B — AI-слой

- [x] `RecommendationEngine`: жёсткие фильтры кандидатов (mandatory, target_roles/grades, prerequisites, не пройдено)
- [x] `RecommendationEngine`: многофакторный скоринг — вклад в разрывы до след. грейда (с учётом max_level), critical_skills, career_goal, штрафы истории (no_show/declined/dropped), своевременность завершений, ближайшие сессии
- [x] `LlmService`: драйверы OpenAI-compatible / Anthropic-compatible (через .env)
- [x] `LlmService`: промпт — профиль, разрывы, история, топ-8 кандидатов с факторами
- [x] `LlmService`: strict JSON → валидация event_id против кандидатов → 1–3 рекомендации с обоснованием ≥3 факторов
- [x] `LlmService`: таймаут 8 с, fallback на шаблонное обоснование из factors движка
- [x] Тест на анти-примере из ТЗ: ниже всего Public Speaking, но 3 пропуска таких активностей и критичен System Design → движок рекомендует System Design
- [x] Доводка промпта: обоснования на русском, с конкретными цифрами («2 при требуемых 4 для Senior»)

## Участник C — Frontend / UI

Blade + Tailwind (Vite). До интеграции — на моках по контрактам ниже.

- [x] Layout + дизайн-тема
- [x] Список сотрудников — поиск, роль/грейд
- [x] Профиль: карьерная траектория (grade ladder Junior→Lead, текущая позиция)
- [x] Профиль: навыки vs требования следующего грейда (разрывы подсвечены)
- [x] Профиль: таймлайн истории активностей (статусы, оценки)
- [x] Рекомендации: кнопка «Получить рекомендацию» со skeleton-загрузкой
- [x] Рекомендации: карточки событий (название, тип, factor-бейджи, обоснование, пометка source llm/fallback)
- [x] Complete-flow: кнопка «Отметить выполненной» → дельта прогресса по навыкам и готовности к грейду
- [x] HR-дашборд (`/hr`): проседающие навыки, сотрудники без рекомендованного шага, участие по активностям
- [x] Admin: форма `/admin/upload` (employees.json + activity_history.csv)
- [x] Переключатель роли сотрудник/HR в шапке

## Маркеры интеграции

- [x] **M0** — `php artisan serve` поднимается, контракты зафиксированы
- [x] **M1** — импорт данных работает; движок на фикстуре выдаёт осмысленный топ-3
- [x] **M2** — POST /recommendations возвращает 1–3 события с обоснованием ≥3 факторов (llm и fallback)
- [ ] **M3** — полный сценарий сотрудника end-to-end на живых данных
- [ ] **M4** — все 5 must-have из ТЗ проходят ручную проверку → README, прогон на проверочном профиле, freeze, репетиция демо

## Контракты API

**POST /employees/{id}/recommendations** →
```json
{"recommendations":[
  {"event_id":"EV_012","rank":1,"score":8.4,
   "title":"System Design Workshop","type":"workshop",
   "rationale":"System Design — 2 при требуемых 4 для Senior...",
   "factors":["skill_gap","critical_skill","history_clean"],
   "source":"llm"}]}
```

**POST /employees/{id}/complete {event_id}** →
```json
{"skill_deltas":[{"skill_id":"SK_SYSTEM_DESIGN","from":2,"to":4}],
 "grade_readiness":{"grade":"Senior","covered":7,"total":9}}
```

## Не делаем (сознательно)

Геймификация, локализация kk/ru, мобильная адаптация, мессенджер-интеграции, конструктор событий, прогноз оттока.

## Риски

| Риск | Страховка |
|---|---|
| LLM недоступна на защите | fallback-обоснование движка, пометка source в UI |
| Проверочные профили ломают правила | движок многофакторный; тест на анти-примере обязателен |
| Интеграция затянется | контракты зафиксированы на M0, B и C работают на моках до M3 |
