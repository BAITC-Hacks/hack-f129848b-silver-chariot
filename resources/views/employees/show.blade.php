@extends('layout')

@section('title', $employee->full_name . ' — Career Quest')
@section('eyebrow', 'Профиль сотрудника')
@section('page_title', $employee->full_name)

@section('content')
    @php
        $gradeSteps = ['Junior', 'Middle', 'Senior', 'Lead'];
        $currentGradeIndex = array_search($employee->grade, $gradeSteps, true);
        $currentGradeIndex = $currentGradeIndex === false ? 0 : $currentGradeIndex;
        $nextGrade = $grade_readiness['grade'] ?? null;
        $readinessCovered = (int) ($grade_readiness['covered'] ?? 0);
        $readinessTotal = (int) ($grade_readiness['total'] ?? 0);
        $readinessPercent = $readinessTotal > 0
            ? (int) round(($readinessCovered / $readinessTotal) * 100)
            : ($employee->grade === 'Lead' ? 100 : 0);
        $careerGoal = is_array($employee->career_goal) ? $employee->career_goal : [];
        $gapCollection = collect($gaps ?? []);
        $historyCollection = collect($history ?? []);
        $eventCollection = collect($events ?? []);
        $gapCount = $gapCollection->where('gap', '>', 0)->count();
        $criticalGapCount = $gapCollection
            ->filter(fn (array $gap): bool => ($gap['critical'] ?? false) && ($gap['gap'] ?? 0) > 0)
            ->count();
        $completedEventIds = $historyCollection
            ->where('status', 'completed')
            ->pluck('event_id')
            ->all();
        $availableEvents = $eventCollection->filter(function ($event) use ($completedEventIds, $employee): bool {
            $matchesRole = empty($event->target_roles) || in_array($employee->role, $event->target_roles, true);
            $matchesGrade = empty($event->target_grades) || in_array($employee->grade, $event->target_grades, true);
            $canRepeat = $event->event_id === 'EV_036';

            return ! $event->mandatory
                && $matchesRole
                && $matchesGrade
                && ($canRepeat || ! in_array($event->event_id, $completedEventIds, true));
        });
        $gappedSkillIds = $gapCollection->where('gap', '>', 0)->pluck('skill_id')->all();
        $mockCandidates = $availableEvents
            ->sortByDesc(function ($event) use ($gappedSkillIds): int {
                return collect($event->develops_skills ?? [])->whereIn('skill_id', $gappedSkillIds)->count();
            })
            ->take(3)
            ->values();
        $mockRecommendations = $mockCandidates->map(function ($event, int $index) use ($gapCollection, $skills, $nextGrade): array {
            $matchedGap = $gapCollection->first(function (array $gap) use ($event): bool {
                return ($gap['gap'] ?? 0) > 0
                    && collect($event->develops_skills ?? [])->contains('skill_id', $gap['skill_id']);
            });
            $factors = ['career_goal'];

            if ($matchedGap) {
                $factors[] = 'skill_gap';
                $factors[] = ($matchedGap['critical'] ?? false) ? 'critical_skill' : 'history_clean';
                $skillName = $skills[$matchedGap['skill_id']]->name ?? $matchedGap['skill_id'];
                $rationale = sprintf(
                    '%s — %d при требуемых %d для %s. Активность напрямую сокращает разрыв и поддерживает карьерную цель.',
                    $skillName,
                    $matchedGap['current'],
                    $matchedGap['required'],
                    $nextGrade ?: 'следующего грейда',
                );
            } else {
                $factors[] = 'history_clean';
                $factors[] = $event->format === 'self_paced' ? 'self_paced' : 'upcoming_session';
                $rationale = 'Активность соответствует роли и текущему грейду, поддерживает карьерную цель и доступна в подходящем формате.';
            }

            return [
                'event_id' => $event->event_id,
                'rank' => $index + 1,
                'score' => round(8.6 - ($index * 0.7), 1),
                'title' => $event->title,
                'type' => $event->type,
                'rationale' => $rationale,
                'factors' => array_values(array_unique($factors)),
                'source' => 'fallback',
            ];
        })->all();
        $nameParts = preg_split('/\s+/u', trim($employee->full_name)) ?: [];
        $initials = collect($nameParts)
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $workFormatLabels = [
            'office' => 'Офис',
            'remote' => 'Удалённо',
            'hybrid' => 'Гибрид',
        ];
        $languageLabels = [
            'ru' => 'Русский',
            'kk' => 'Қазақша',
            'en' => 'English',
        ];
        $statusLabels = [
            'completed' => 'Завершено',
            'in_progress' => 'В процессе',
            'assigned' => 'Назначено',
            'registered' => 'Запланировано',
            'enrolled' => 'Запланировано',
            'no_show' => 'Неявка',
            'declined' => 'Отклонено',
            'dropped' => 'Прервано',
        ];
        $statusClasses = [
            'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'in_progress' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            'assigned' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
            'registered' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            'enrolled' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            'no_show' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            'declined' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            'dropped' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        ];
        $typeLabels = [
            'course' => 'Курс',
            'workshop' => 'Воркшоп',
            'meetup' => 'Встреча',
            'mentoring' => 'Менторинг',
            'certification' => 'Сертификация',
        ];
        $tenureMonths = (int) ($employee->tenure_months ?? 0);
        $tenureYears = intdiv($tenureMonths, 12);
        $tenureRemainder = $tenureMonths % 12;
        $tenureLabel = $tenureYears > 0
            ? $tenureYears . ' г. ' . ($tenureRemainder > 0 ? $tenureRemainder . ' мес.' : '')
            : $tenureMonths . ' мес.';
        $lastReviewDate = $employee->last_review_date instanceof \DateTimeInterface
            ? $employee->last_review_date->format('d.m.Y')
            : ($employee->last_review_date ?: '—');
    @endphp

    <div class="text-slate-950">
        <div class="mx-auto flex max-w-[1440px] flex-col gap-6">
            <div class="flex items-center justify-between gap-6">
                <a
                    href="{{ route('employees.index') }}"
                    class="group inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-600"
                >
                    <svg class="size-4 transition-transform group-hover:-translate-x-0.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M15.833 10H4.167m0 0 4.375 4.375M4.167 10l4.375-4.375" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Все сотрудники
                </a>
                <p class="text-xs font-medium tracking-wide text-slate-400">Профиль обновлён {{ $lastReviewDate }}</p>
            </div>

            <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#063b32] via-[#075c4b] to-[#0e7669] px-8 py-8 text-white shadow-[0_24px_60px_-28px_rgba(6,59,50,0.7)] lg:px-10">
                <div class="pointer-events-none absolute -right-20 -top-28 size-80 rounded-full border border-white/10 bg-white/5"></div>
                <div class="pointer-events-none absolute -bottom-28 right-44 size-64 rounded-full border border-white/10"></div>

                <div class="relative grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_440px] xl:items-end">
                    <div class="flex min-w-0 items-center gap-6">
                        <div class="grid size-24 shrink-0 place-items-center rounded-3xl border border-white/20 bg-white/12 text-3xl font-bold tracking-tight shadow-inner shadow-white/10 backdrop-blur-sm">
                            {{ $initials ?: 'CQ' }}
                        </div>
                        <div class="min-w-0">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-white/12 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-50 ring-1 ring-inset ring-white/20">
                                    {{ $employee->employee_id }}
                                </span>
                                <span class="rounded-full bg-emerald-300/15 px-3 py-1 text-xs font-semibold text-emerald-100 ring-1 ring-inset ring-emerald-200/25">
                                    {{ $employee->grade }}
                                </span>
                            </div>
                            <h1 class="text-3xl font-bold tracking-tight lg:text-[2.35rem] lg:leading-tight">
                                {{ $employee->full_name }}
                            </h1>
                            <p class="mt-2 text-base font-medium text-emerald-50/90">
                                {{ $employee->role }} <span class="px-1.5 text-white/35">·</span> {{ $employee->department }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/15 bg-black/10 p-5 backdrop-blur-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-100/75">Готовность к следующему грейду</p>
                                <p class="mt-2 text-lg font-semibold">
                                    @if ($nextGrade)
                                        {{ $employee->grade }} → {{ $nextGrade }}
                                    @else
                                        Максимальный грейд достигнут
                                    @endif
                                </p>
                            </div>
                            <span class="text-2xl font-bold tabular-nums" data-readiness-percent>{{ $readinessPercent }}%</span>
                        </div>
                        <div class="mt-5 h-2 overflow-hidden rounded-full bg-black/20" aria-hidden="true">
                            <div class="h-full rounded-full bg-gradient-to-r from-lime-300 to-emerald-300 transition-all duration-500" style="width: {{ $readinessPercent }}%" data-readiness-percent data-progress-bar></div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs font-medium text-emerald-50/75">
                            @if ($readinessTotal > 0)
                                <span><span data-readiness-covered>{{ $readinessCovered }}</span> из <span data-readiness-total>{{ $readinessTotal }}</span> требований закрыто</span>
                                <span>{{ $gapCount }} {{ $gapCount === 1 ? 'разрыв' : 'разрывов' }}</span>
                            @else
                                <span>Траектория до Lead завершена</span>
                                <span>Профиль готов</span>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white px-8 py-7 shadow-sm shadow-slate-200/60" aria-labelledby="career-path-heading">
                <div class="flex items-center justify-between gap-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Карьерная траектория</p>
                        <h2 id="career-path-heading" class="mt-1.5 text-xl font-bold tracking-tight text-slate-950">Путь развития в роли {{ $employee->role }}</h2>
                    </div>
                    @if (! empty($careerGoal))
                        <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-right ring-1 ring-inset ring-emerald-100">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-700">Карьерная цель</p>
                            <p class="mt-1 text-sm font-semibold text-emerald-950">
                                {{ $careerGoal['target_role'] ?? $employee->role }} · {{ $careerGoal['target_grade'] ?? 'Следующий уровень' }}
                            </p>
                        </div>
                    @endif
                </div>

                <ol class="mt-8 grid grid-cols-4" aria-label="Грейды от Junior до Lead">
                    @foreach ($gradeSteps as $gradeIndex => $grade)
                        @php
                            $isCurrentGrade = $gradeIndex === $currentGradeIndex;
                            $isCompletedGrade = $gradeIndex < $currentGradeIndex;
                            $isFutureGrade = $gradeIndex > $currentGradeIndex;
                        @endphp
                        <li class="relative min-w-0 {{ ! $loop->last ? 'after:absolute after:left-[calc(50%+1.5rem)] after:right-[calc(-50%+1.5rem)] after:top-5 after:h-0.5 after:bg-slate-200' : '' }}">
                            @if (! $loop->last && $isCompletedGrade)
                                <span class="absolute left-[calc(50%+1.5rem)] right-[calc(-50%+1.5rem)] top-5 z-10 h-0.5 bg-emerald-500" aria-hidden="true"></span>
                            @endif
                            <div class="relative z-20 flex flex-col items-center gap-3 text-center">
                                <span class="grid size-10 place-items-center rounded-full border-2 text-sm font-bold shadow-sm
                                    {{ $isCurrentGrade ? 'border-emerald-500 bg-emerald-600 text-white ring-4 ring-emerald-100' : '' }}
                                    {{ $isCompletedGrade ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : '' }}
                                    {{ $isFutureGrade ? 'border-slate-200 bg-white text-slate-400' : '' }}"
                                >
                                    @if ($isCompletedGrade)
                                        <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="m5.5 10 3 3 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @else
                                        {{ $gradeIndex + 1 }}
                                    @endif
                                </span>
                                <div>
                                    <p class="text-sm font-bold {{ $isCurrentGrade ? 'text-emerald-800' : ($isCompletedGrade ? 'text-slate-700' : 'text-slate-400') }}">{{ $grade }}</p>
                                    <p class="mt-0.5 text-[11px] font-medium {{ $isCurrentGrade ? 'text-emerald-600' : 'text-slate-400' }}">
                                        {{ $isCurrentGrade ? 'Текущий уровень' : ($isCompletedGrade ? 'Пройдено' : 'Впереди') }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
                <div class="flex flex-col gap-6 xl:col-span-8">
                    <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/60" aria-labelledby="skills-heading">
                        <div class="flex items-start justify-between gap-6 border-b border-slate-100 px-7 py-6">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="grid size-9 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                                        <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M4 15.5V11m6 4.5v-11m6 11V8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <h2 id="skills-heading" class="text-xl font-bold tracking-tight text-slate-950">Навыки и требования</h2>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-500">
                                    @if ($nextGrade)
                                        Сравнение текущего профиля с требованиями грейда {{ $nextGrade }}.
                                    @else
                                        Текущий профиль соответствует верхней ступени карьерной траектории.
                                    @endif
                                </p>
                            </div>
                            @if ($criticalGapCount > 0)
                                <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 ring-1 ring-inset ring-rose-100">
                                    <span class="size-1.5 rounded-full bg-rose-500"></span>
                                    {{ $criticalGapCount }} критич.
                                </span>
                            @endif
                        </div>

                        <div class="divide-y divide-slate-100">
                            @forelse ($gapCollection as $gap)
                                @php
                                    $skillId = $gap['skill_id'];
                                    $currentLevel = (int) ($gap['current'] ?? 0);
                                    $requiredLevel = (int) ($gap['required'] ?? 0);
                                    $gapValue = (int) ($gap['gap'] ?? 0);
                                    $isCritical = (bool) ($gap['critical'] ?? false);
                                    $currentPercent = min(100, max(0, $currentLevel * 20));
                                    $requiredPercent = min(100, max(0, $requiredLevel * 20));
                                    $skillName = $skills[$skillId]->name ?? $skillId;
                                    $skillCategory = $skills[$skillId]->category ?? null;
                                @endphp
                                <article class="grid grid-cols-[minmax(0,1fr)_minmax(210px,0.85fr)_96px] items-center gap-5 px-7 py-5 transition hover:bg-slate-50/70" data-skill-id="{{ $skillId }}" data-current="{{ $currentLevel }}">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="truncate text-sm font-bold text-slate-900">{{ $skillName }}</h3>
                                            @if ($isCritical)
                                                <span class="shrink-0 rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700 ring-1 ring-inset ring-rose-100">Критический</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 truncate text-xs font-medium text-slate-400">{{ $skillCategory ?: $skillId }}</p>
                                    </div>

                                    <div>
                                        <div class="mb-2 flex items-center justify-between text-xs font-semibold">
                                            <span class="text-slate-500">Текущий: <strong class="text-slate-800" data-skill-current>{{ $currentLevel }}</strong></span>
                                            <span class="text-slate-500">Нужно: <strong class="text-slate-800">{{ $requiredLevel }}</strong></span>
                                        </div>
                                        <div class="relative h-2.5 overflow-visible rounded-full bg-slate-100">
                                            <div class="h-full rounded-full {{ $gapValue > 0 ? ($isCritical ? 'bg-rose-500' : 'bg-amber-400') : 'bg-emerald-500' }} transition-all duration-500" style="width: {{ $currentPercent }}%" data-skill-progress></div>
                                            <span class="absolute -top-1 h-[18px] w-0.5 rounded-full bg-slate-700" style="left: calc({{ $requiredPercent }}% - 1px)" aria-hidden="true"></span>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        @if ($gapValue > 0)
                                            <span class="inline-flex min-w-20 justify-center rounded-xl px-3 py-2 text-xs font-bold {{ $isCritical ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">
                                                Разрыв {{ $gapValue }}
                                            </span>
                                        @else
                                            <span class="inline-flex min-w-20 items-center justify-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">
                                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                                    <path d="m4 8 2.5 2.5L12 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                Закрыт
                                            </span>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="px-7 py-12 text-center">
                                    <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                                        <svg class="size-6" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="m5 10 3.2 3.2L15 6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <h3 class="mt-4 text-sm font-bold text-slate-900">Разрывов по навыкам нет</h3>
                                    <p class="mt-1 text-sm text-slate-500">Все требования текущей карьерной ступени закрыты.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200/80 bg-white px-7 py-6 shadow-sm shadow-slate-200/60" aria-labelledby="history-heading">
                        <div class="flex items-center justify-between gap-5">
                            <div class="flex items-center gap-2.5">
                                <span class="grid size-9 place-items-center rounded-xl bg-sky-50 text-sky-700">
                                    <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M10 5.5V10l3 1.75M17 10a7 7 0 1 1-2.05-4.95" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <div>
                                    <h2 id="history-heading" class="text-xl font-bold tracking-tight text-slate-950">История развития</h2>
                                    <p class="mt-0.5 text-xs font-medium text-slate-400">{{ $historyCollection->count() }} активностей в профиле</p>
                                </div>
                            </div>
                        </div>

                        <ol class="mt-7">
                            @forelse ($historyCollection as $record)
                                @php
                                    $recordStatus = $record->status ?? 'assigned';
                                    $recordDate = $record->date instanceof \DateTimeInterface
                                        ? $record->date->format('d.m.Y')
                                        : ($record->date ?: '—');
                                    $eventTitle = $record->event?->title ?? $record->event_id;
                                    $eventType = $record->event?->type;
                                @endphp
                                <li class="relative grid grid-cols-[42px_minmax(0,1fr)] gap-4 pb-7 last:pb-0">
                                    @if (! $loop->last)
                                        <span class="absolute bottom-0 left-5 top-10 w-px bg-slate-200" aria-hidden="true"></span>
                                    @endif
                                    <span class="relative z-10 grid size-10 place-items-center rounded-full border-4 border-white {{ $recordStatus === 'completed' ? 'bg-emerald-500 text-white' : ($recordStatus === 'no_show' ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500') }}">
                                        @if ($recordStatus === 'completed')
                                            <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                                <path d="m4 8 2.5 2.5L12 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        @else
                                            <span class="size-2 rounded-full bg-current"></span>
                                        @endif
                                    </span>

                                    <article class="rounded-2xl border border-slate-100 bg-slate-50/60 px-5 py-4">
                                        <div class="flex items-start justify-between gap-5">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-sm font-bold text-slate-900">{{ $eventTitle }}</h3>
                                                    @if ($eventType)
                                                        <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 ring-1 ring-inset ring-slate-200">
                                                            {{ $typeLabels[$eventType] ?? $eventType }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="mt-1.5 text-xs font-medium text-slate-400">{{ $recordDate }} · {{ $record->event_id }}</p>
                                            </div>
                                            <span class="shrink-0 rounded-full px-3 py-1 text-[11px] font-bold ring-1 ring-inset {{ $statusClasses[$recordStatus] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                                {{ $statusLabels[$recordStatus] ?? $recordStatus }}
                                            </span>
                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold text-slate-500">
                                            <span>Прогресс <strong class="text-slate-800">{{ (int) ($record->completion_pct ?? 0) }}%</strong></span>
                                            @if ($record->score !== null)
                                                <span>Оценка <strong class="text-slate-800">{{ $record->score }}</strong></span>
                                            @endif
                                            @if ($record->feedback_rating !== null)
                                                <span class="inline-flex items-center gap-1">
                                                    Отзыв <strong class="text-slate-800">{{ $record->feedback_rating }}/5</strong>
                                                    <svg class="size-3.5 text-amber-400" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                        <path d="m8 1.5 1.85 3.75 4.14.6-3 2.93.71 4.13L8 10.96l-3.7 1.95.71-4.13-3-2.93 4.14-.6L8 1.5Z"/>
                                                    </svg>
                                                </span>
                                            @endif
                                        </div>
                                    </article>
                                </li>
                            @empty
                                <li class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center">
                                    <p class="text-sm font-bold text-slate-700">История пока пуста</p>
                                    <p class="mt-1 text-sm text-slate-500">Завершённые и назначенные активности появятся здесь.</p>
                                </li>
                            @endforelse
                        </ol>
                    </section>
                </div>

                <aside class="flex flex-col gap-6 xl:col-span-4">
                    <section class="overflow-hidden rounded-3xl border border-emerald-200/70 bg-white shadow-[0_18px_45px_-30px_rgba(5,150,105,0.55)]" aria-labelledby="recommendations-heading" data-recommendations data-employee-id="{{ $employee->employee_id }}" data-url="{{ route('employees.recommendations', $employee) }}">
                        <script type="application/json" data-recommendation-mock>@json($mockRecommendations)</script>
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50/50 px-6 py-6">
                            <div class="flex items-start gap-4">
                                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20">
                                    <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M10 2.75 11.35 7l4.4 1.25-4.4 1.4L10 14l-1.35-4.35-4.4-1.4L8.65 7 10 2.75Zm5.25 9.5.65 2.1 2.1.65-2.1.65-.65 2.1-.65-2.1-2.1-.65 2.1-.65.65-2.1Z" fill="currentColor"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-700">AI-навигатор</p>
                                    <h2 id="recommendations-heading" class="mt-1 text-xl font-bold tracking-tight text-slate-950">Следующий лучший шаг</h2>
                                    <p class="mt-2 text-sm leading-5 text-slate-600">Подберём 1–3 активности по разрывам, карьерной цели и вашей истории.</p>
                                </div>
                            </div>

                            <form method="post" action="{{ route('employees.recommendations', $employee) }}" class="mt-5" data-recommendations-form data-recommendation-form>
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-2.5 rounded-2xl bg-emerald-700 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-700/20 transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-70"
                                    data-recommendations-trigger
                                    data-recommendation-submit
                                >
                                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m10 2.5 1.2 4.1 4.3 1.2-4.3 1.3L10 13.2 8.8 9.1 4.5 7.8l4.3-1.2L10 2.5Z" fill="currentColor"/>
                                    </svg>
                                    Получить рекомендацию
                                </button>
                            </form>
                            <p class="mt-3 text-center text-xs font-medium text-slate-500" role="status" aria-live="polite" data-recommendations-status>
                                Анализ обычно занимает несколько секунд
                            </p>
                        </div>

                        <div class="px-6 py-6">
                            <div hidden data-recommendations-skeleton data-recommendation-skeleton aria-hidden="true">
                                <div class="flex flex-col gap-4">
                                    @foreach (range(1, 2) as $skeletonIndex)
                                        <div class="animate-pulse rounded-2xl border border-slate-100 p-4">
                                            <div class="flex items-center justify-between gap-4">
                                                <div class="h-3 w-20 rounded-full bg-slate-200"></div>
                                                <div class="h-5 w-14 rounded-full bg-slate-100"></div>
                                            </div>
                                            <div class="mt-4 h-4 w-4/5 rounded-full bg-slate-200"></div>
                                            <div class="mt-3 h-3 w-full rounded-full bg-slate-100"></div>
                                            <div class="mt-2 h-3 w-2/3 rounded-full bg-slate-100"></div>
                                            <div class="mt-4 flex gap-2">
                                                <div class="h-6 w-20 rounded-full bg-slate-100"></div>
                                                <div class="h-6 w-24 rounded-full bg-slate-100"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-7 text-center" data-recommendations-empty data-recommendation-empty>
                                <div class="mx-auto grid size-10 place-items-center rounded-xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-100">
                                    <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M5 5.5h10M5 9.5h7M5 13.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-bold text-slate-700">Рекомендации ещё не рассчитаны</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Запустите анализ, чтобы увидеть приоритетные активности и факторы выбора.</p>
                            </div>
                            <div hidden class="flex flex-col gap-4" data-recommendations-list data-recommendation-results></div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200/80 bg-white px-6 py-6 shadow-sm shadow-slate-200/60" aria-labelledby="complete-heading">
                        <div class="flex items-start gap-3.5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-700">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div>
                                <h2 id="complete-heading" class="text-lg font-bold tracking-tight text-slate-950">Завершить активность</h2>
                                <p class="mt-1 text-sm leading-5 text-slate-500">Обновим уровни навыков и готовность к грейду.</p>
                            </div>
                        </div>

                        <form
                            method="post"
                            action="{{ route('employees.complete', $employee) }}"
                            class="mt-5 flex flex-col gap-3"
                            data-complete-form
                            data-url="{{ route('employees.complete', $employee) }}"
                        >
                            @csrf
                            <label for="event_id" class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Активность</label>
                            <div class="relative">
                                <select
                                    id="event_id"
                                    name="event_id"
                                    class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-3 pl-4 pr-10 text-sm font-semibold text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                                    data-complete-event-select
                                    data-event-select
                                    @disabled($availableEvents->isEmpty())
                                    required
                                >
                                    @forelse ($availableEvents as $event)
                                        <option value="{{ $event->event_id }}">
                                            {{ $event->event_id }} · {{ $event->title }}{{ $event->duration_hours ? ' · ' . rtrim(rtrim(number_format($event->duration_hours, 1, '.', ''), '0'), '.') . ' ч' : '' }}
                                        </option>
                                    @empty
                                        <option value="">Все доступные активности завершены</option>
                                    @endforelse
                                </select>
                                <svg class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3.5 text-sm font-bold text-white transition hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                data-complete-trigger
                                data-complete-submit
                                @disabled($availableEvents->isEmpty())
                            >
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m5 10 3.1 3.1L15 6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Отметить выполненной
                            </button>
                        </form>

                        <p hidden class="mt-3 text-sm font-medium text-slate-500" role="status" aria-live="polite" data-complete-status></p>
                        <div hidden class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 p-4" data-complete-result aria-live="polite"></div>
                    </section>

                    <section class="rounded-3xl border border-slate-200/80 bg-white px-6 py-6 shadow-sm shadow-slate-200/60" aria-labelledby="profile-heading">
                        <h2 id="profile-heading" class="text-base font-bold tracking-tight text-slate-950">О профиле</h2>
                        <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-5">
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Стаж</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ trim($tenureLabel) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Формат</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $workFormatLabels[$employee->work_format] ?? ($employee->work_format ?: '—') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Язык</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $languageLabels[$employee->preferred_language] ?? ($employee->preferred_language ?: '—') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Менеджер</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $employee->manager_id ?: 'Не указан' }}</dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>
    </div>
@endsection
