@extends('layout')

@section('title', $employee->full_name)

@section('content')
    @php
        $grades = ['Junior', 'Middle', 'Senior', 'Lead'];
        $currentGradeIndex = array_search($employee->grade, $grades, true);
        $readinessPercent = $grade_readiness['total'] > 0 ? round($grade_readiness['covered'] / $grade_readiness['total'] * 100) : 0;
        $factorLabels = [
            'grade_requirement' => 'Следующий грейд',
            'skill_gap' => 'Закрывает разрыв',
            'critical_skill' => 'Критичный навык',
            'career_goal' => 'Карьерная цель',
            'history_clean' => 'Подходит по истории',
            'history_penalty' => 'Учтены пропуски',
            'completion_history' => 'Успешный опыт',
            'on_time_history' => 'Завершение в срок',
            'availability' => 'Доступно для участия',
        ];
        $typeLabels = ['course' => 'Курс', 'workshop' => 'Воркшоп', 'mentoring' => 'Менторство', 'meetup' => 'Митап', 'certification' => 'Сертификация', 'conference' => 'Конференция', 'hackathon' => 'Хакатон', 'compliance' => 'Обязательное обучение', 'onboarding' => 'Онбординг'];
        $statusLabels = ['completed' => 'Завершено', 'in_progress' => 'В процессе', 'enrolled' => 'Записан', 'planned' => 'Запланировано', 'no_show' => 'Пропуск', 'declined' => 'Отказ', 'dropped' => 'Прервано', 'overdue' => 'Просрочено'];
        $statusStyles = ['completed' => 'bg-emerald-50 text-emerald-800', 'in_progress' => 'bg-sky-50 text-sky-800', 'no_show' => 'bg-amber-50 text-amber-800', 'declined' => 'bg-rose-50 text-rose-800', 'dropped' => 'bg-orange-50 text-orange-800', 'overdue' => 'bg-rose-50 text-rose-800'];
    @endphp

    <div data-employee-profile data-profile-url="{{ route('employees.show', $employee) }}" data-factor-labels="{{ json_encode($factorLabels, JSON_UNESCAPED_UNICODE) }}" data-type-labels="{{ json_encode($typeLabels, JSON_UNESCAPED_UNICODE) }}" class="space-y-8">
        @if(session('role', 'employee') === 'hr')
            <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-800"><span aria-hidden="true">←</span> К списку сотрудников</a>
        @endif

        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-3">
                <p class="eyebrow">Профиль развития · {{ $employee->employee_id }}</p>
                <h1 class="page-title">{{ $employee->full_name }}</h1>
                <p class="text-slate-500">{{ $employee->role }} <span class="px-2 text-slate-300">/</span> {{ $employee->department }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3">
                <p class="text-xs text-emerald-700">Текущий грейд</p>
                <p class="mt-1 text-xl font-semibold text-emerald-950">{{ $employee->grade }}</p>
            </div>
        </header>

        <div data-profile-error role="alert" tabindex="-1" hidden class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"></div>
        <section data-completion-result role="status" aria-live="polite" tabindex="-1" hidden class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6"></section>

        <section class="panel p-6 lg:p-8" aria-labelledby="trajectory-title">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <p class="eyebrow">Карьерная траектория</p>
                    <h2 id="trajectory-title" class="section-title mt-2">{{ $employee->grade === 'Lead' ? 'Развитие на уровне Lead' : 'Следующая ступень — '.($grade_readiness['grade'] ?? 'пока не определена') }}</h2>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-semibold tracking-tight text-emerald-800" data-readiness-percent>{{ $grade_readiness['total'] > 0 ? $readinessPercent.'%' : '—' }}</p>
                    <p class="mt-1 text-xs text-slate-500" data-readiness-summary>{{ $grade_readiness['total'] > 0 ? $grade_readiness['covered'].' из '.$grade_readiness['total'].' требований закрыто' : 'Требования не заданы' }}</p>
                </div>
            </div>
            <ol class="mt-8 grid grid-cols-4 gap-2" aria-label="Ступени карьеры">
                @foreach($grades as $index => $grade)
                    <li @if($employee->grade === $grade) aria-current="step" @endif class="space-y-3">
                        <div @class(['h-1.5 rounded-full', 'bg-emerald-700' => $currentGradeIndex !== false && $index <= $currentGradeIndex, 'bg-slate-100' => $currentGradeIndex === false || $index > $currentGradeIndex])></div>
                        <p @class(['text-sm font-semibold', 'text-emerald-800' => $employee->grade === $grade, 'text-slate-400' => $employee->grade !== $grade])>{{ $grade }}</p>
                        <p class="min-h-4 text-xs text-slate-500">{{ $employee->grade === $grade ? 'Вы здесь' : ($currentGradeIndex !== false && $index === $currentGradeIndex + 1 ? 'Следующий шаг' : '') }}</p>
                    </li>
                @endforeach
            </ol>
            @if($employee->career_goal)
                <div class="mt-7 border-t border-slate-100 pt-5 text-sm">
                    <span class="text-slate-500">Карьерная цель</span>
                    <span class="ml-2 font-medium text-slate-800">{{ $employee->career_goal['target_role'] ?? $employee->role }} · {{ $employee->career_goal['target_grade'] ?? $employee->grade }}</span>
                </div>
            @endif
        </section>

        <section aria-labelledby="recommendations-title" class="space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Персональный маршрут</p>
                    <h2 id="recommendations-title" class="section-title mt-2">Ваш следующий шаг</h2>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500">Активности с учётом навыков, карьерной цели и опыта участия.</p>
                </div>
                <form method="post" action="{{ route('employees.recommendations', $employee) }}" data-recommendation-form>
                    @csrf
                    <button type="submit" class="button-primary" data-recommendation-button>Получить рекомендацию <span aria-hidden="true">↗</span></button>
                </form>
            </div>
            <p data-recommendation-status role="status" aria-live="polite" class="text-sm text-slate-500"></p>
            <div data-recommendation-loading hidden aria-label="Подбираем активности" class="grid gap-4 lg:grid-cols-3">
                @for($index = 0; $index < 3; $index++)
                    <div class="panel space-y-5 p-6 motion-safe:animate-pulse" aria-hidden="true">
                        <div class="h-4 w-20 rounded bg-slate-100"></div><div class="h-7 w-4/5 rounded bg-slate-100"></div>
                        <div class="space-y-2"><div class="h-3 rounded bg-slate-100"></div><div class="h-3 rounded bg-slate-100"></div><div class="h-3 w-3/4 rounded bg-slate-100"></div></div>
                        <div class="h-10 rounded-lg bg-emerald-50"></div>
                    </div>
                @endfor
            </div>
            <div data-recommendations-list class="grid gap-4 lg:grid-cols-3">
                @forelse($recommendations ?? [] as $recommendation)
                    <article class="panel flex flex-col gap-5 p-6" data-recommendation-card data-event-id="{{ $recommendation->event_id }}">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-emerald-800">{{ $recommendation->rank === 1 ? 'Рекомендуем начать' : 'Вариант '.$recommendation->rank }}</span>
                            <span class="badge">{{ $typeLabels[$recommendation->event?->type] ?? $recommendation->event?->type }}</span>
                        </div>
                        <h3 class="text-lg font-semibold leading-snug text-slate-900">{{ $recommendation->event?->title ?? $recommendation->event_id }}</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($recommendation->factors as $factor)
                                <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800">{{ $factorLabels[$factor] ?? $factor }}</span>
                            @endforeach
                        </div>
                        <p class="flex-1 text-sm leading-relaxed text-slate-600">{{ $recommendation->rationale }}</p>
                        <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            <span>{{ $recommendation->source === 'llm' ? 'AI-обоснование · llm' : 'Правила подбора · fallback' }}</span>
                            <span title="Оценка соответствия активности профилю">Рейтинг {{ number_format($recommendation->score, 1) }}</span>
                        </div>
                        <form method="post" action="{{ route('employees.complete', $employee) }}" data-completion-form>
                            @csrf
                            <input type="hidden" name="event_id" value="{{ $recommendation->event_id }}">
                            <button type="submit" class="button-secondary w-full" data-completion-button>Отметить выполненной</button>
                        </form>
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white/50 px-6 py-9 text-center">
                        <p class="font-medium text-slate-700">Маршрут начинается с одного шага</p>
                        <p class="mt-2 text-sm text-slate-500">Получите рекомендации — мы подберём до трёх подходящих активностей и объясним выбор.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="grid items-start gap-6 lg:grid-cols-[1.15fr_1fr]">
            <section class="panel p-6 lg:p-8" aria-labelledby="skills-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="skills-title" class="section-title">Карта навыков</h2>
                    <span class="text-xs text-slate-400">Шкала 0–5</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Текущий уровень и требования {{ $grade_readiness['grade'] ?? 'следующего грейда' }}. Пунктир — целевой уровень.</p>
                <div class="mt-7 space-y-6">
                    @forelse($gaps as $gap)
                        <div data-skill-id="{{ $gap['skill_id'] }}" data-skill-name="{{ $skills[$gap['skill_id']]->name ?? $gap['skill_id'] }}">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-medium text-slate-800">{{ $skills[$gap['skill_id']]->name ?? $gap['skill_id'] }}</h3>
                                    @if($gap['critical'])<span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">Критичный</span>@endif
                                </div>
                                <span class="text-sm tabular-nums text-slate-500">{{ $gap['current'] }} <span class="text-slate-300">/</span> {{ $gap['required'] }}</span>
                            </div>
                            <div class="relative h-2.5 rounded-full bg-slate-100" role="meter" aria-label="{{ $skills[$gap['skill_id']]->name ?? $gap['skill_id'] }}" aria-valuemin="0" aria-valuemax="5" aria-valuenow="{{ $gap['current'] }}" aria-valuetext="{{ $gap['current'] }} из 5; требуется {{ $gap['required'] }}">
                                <div @class(['h-full rounded-full', 'bg-amber-400' => $gap['gap'] > 0, 'bg-emerald-600' => $gap['gap'] === 0]) style="width: {{ min(100, max(0, $gap['current'] * 20)) }}%"></div>
                                <span class="absolute -top-1 h-4.5 border-l-2 border-dashed border-slate-600" style="left: {{ min(100, max(0, $gap['required'] * 20)) }}%" aria-hidden="true"></span>
                            </div>
                            <p @class(['mt-2 text-xs', 'text-amber-700' => $gap['gap'] > 0, 'text-emerald-700' => $gap['gap'] === 0])>{{ $gap['gap'] > 0 ? 'До цели: +'.$gap['gap'] : 'Требование выполнено' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Требования для следующего грейда пока не заданы. Рекомендации могут учитывать карьерную цель.</p>
                    @endforelse
                </div>
                @if(count($employee->skills ?? []) > 0)
                    <details class="mt-7 border-t border-slate-100 pt-4" @if(count($gaps) === 0) open @endif>
                        <summary class="cursor-pointer text-sm font-medium text-slate-600">Все навыки · {{ count($employee->skills) }}</summary>
                        <dl class="mt-4 space-y-3">
                            @foreach($employee->skills as $skillId => $level)
                                <div class="flex justify-between gap-4 text-sm" data-skill-id="{{ $skillId }}" data-skill-name="{{ $skills[$skillId]->name ?? $skillId }}">
                                    <dt class="text-slate-500">{{ $skills[$skillId]->name ?? $skillId }}</dt><dd class="font-medium tabular-nums text-slate-800">{{ $level }} / 5</dd>
                                </div>
                            @endforeach
                        </dl>
                    </details>
                @endif
            </section>

            <div class="space-y-6">
                <section class="panel p-6" aria-labelledby="complete-title">
                    <h2 id="complete-title" class="section-title">Уже прошли активность?</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">Отметьте завершение, чтобы обновить навыки и увидеть прогресс к следующему грейду.</p>
                    <form method="post" action="{{ route('employees.complete', $employee) }}" data-completion-form class="mt-5 space-y-3">
                        @csrf
                        <label for="event-id" class="text-xs font-medium text-slate-600">Активность</label>
                        <select id="event-id" name="event_id" required class="field w-full" @disabled($events->isEmpty())>
                            <option value="">Выберите завершённую активность</option>
                            @foreach($events as $event)<option value="{{ $event->event_id }}">{{ $event->title }}</option>@endforeach
                        </select>
                        <button type="submit" class="button-secondary w-full" data-completion-button @disabled($events->isEmpty())>Отметить выполненной</button>
                        @if($events->isEmpty())<p class="text-xs text-slate-500">Нет доступных активностей для завершения.</p>@endif
                    </form>
                </section>

                <section class="panel p-6" aria-labelledby="history-title">
                    <div class="flex items-center justify-between gap-3"><h2 id="history-title" class="section-title">История активностей</h2><span class="badge">{{ $history->count() }}</span></div>
                    <ol class="mt-6 space-y-5" data-activity-history>
                        @forelse($history as $record)
                            <li class="relative border-l border-slate-200 pl-5">
                                <span @class(['absolute -left-1 top-1.5 size-2 rounded-full', 'bg-emerald-600' => $record->status === 'completed', 'bg-slate-300' => $record->status !== 'completed']) aria-hidden="true"></span>
                                <div class="flex flex-wrap items-center gap-2 text-xs"><time datetime="{{ $record->date->format('Y-m-d') }}" class="text-slate-400">{{ $record->date->format('d.m.Y') }}</time><span class="rounded-md px-2 py-0.5 {{ $statusStyles[$record->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabels[$record->status] ?? $record->status }}</span></div>
                                <h3 class="mt-2 text-sm font-medium leading-snug text-slate-800">{{ $record->event?->title ?? $record->event_id }}</h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    Прогресс {{ $record->completion_pct }}% · Оценка {{ $record->score ?? '—' }}
                                    @if($record->feedback_rating !== null)
                                        · Отзыв {{ $record->feedback_rating }}/5
                                    @endif
                                </p>
                            </li>
                        @empty
                            <li class="rounded-lg bg-slate-50 p-5 text-sm text-slate-500">История пока пуста. Завершённые активности появятся здесь вместе с результатами.</li>
                        @endforelse
                    </ol>
                </section>
            </div>
        </div>

        <template data-recommendation-template>
            <article class="panel flex flex-col gap-5 p-6" data-recommendation-card>
                <div class="flex items-center justify-between gap-3 text-xs"><span class="font-semibold text-emerald-800" data-card-rank></span><span class="badge" data-card-type></span></div>
                <h3 class="text-lg font-semibold leading-snug text-slate-900" data-card-title></h3>
                <div class="flex flex-wrap gap-1.5" data-card-factors></div>
                <p class="flex-1 text-sm leading-relaxed text-slate-600" data-card-rationale></p>
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500"><span data-card-source></span><span title="Оценка соответствия активности профилю" data-card-score></span></div>
                <form method="post" action="{{ route('employees.complete', $employee) }}" data-completion-form>
                    @csrf
                    <input type="hidden" name="event_id">
                    <button type="submit" class="button-secondary w-full" data-completion-button>Отметить выполненной</button>
                </form>
            </article>
        </template>
        <noscript><p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Включите JavaScript, чтобы получать рекомендации и отмечать активности прямо в профиле.</p></noscript>
    </div>
@endsection
