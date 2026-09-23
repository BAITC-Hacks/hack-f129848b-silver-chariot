@extends('layout')

@section('title', 'HR-аналитика')
@section('eyebrow', 'HR-пространство')
@section('page_title', 'Аналитика развития')

@section('content')
    @php
        $skillGaps = collect($skill_gaps ?? []);
        $employeesWithoutNextStep = collect($employees_without_next_step ?? []);
        $participationRows = collect($participation ?? []);
        $totalGap = (int) $skillGaps->sum(fn (array $skill): int => (int) ($skill['gap'] ?? 0));
        $largestGap = max(1, (int) $skillGaps->max('gap'));
        $totalActivities = (int) $participationRows->sum('total');
        $completedActivities = (int) $participationRows->sum(
            fn (array $event): int => (int) data_get($event, 'statuses.completed', 0),
        );
        $completionRate = $totalActivities > 0 ? (int) round($completedActivities / $totalActivities * 100) : 0;
    @endphp

    <div class="flex flex-col gap-8">
        <header class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-8 py-9 text-white shadow-xl shadow-slate-950/10 sm:px-10">
            <div class="absolute -right-20 -top-24 size-72 rounded-full bg-emerald-400/20 blur-3xl" aria-hidden="true"></div>
            <div class="absolute -bottom-28 right-36 size-64 rounded-full bg-amber-300/10 blur-3xl" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">
                        <span class="size-1.5 rounded-full bg-emerald-400"></span>
                        Срез развития команды
                    </div>
                    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">HR-аналитика</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">
                        Разрывы компетенций, сотрудники без следующего шага и участие в программах развития — в одном срезе.
                    </p>
                </div>

                <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur-sm">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-400/15 text-emerald-300">
                        <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3m8-3v3M3.5 9.5h17M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                            <path stroke-linecap="round" d="M8 14h3m2 0h3m-8 3h3" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Дата среза</p>
                        <p class="mt-0.5 text-sm font-bold text-white">1 октября 2026</p>
                    </div>
                </div>
            </div>
        </header>

        <section aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="sr-only">Ключевые показатели</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Общий разрыв</p>
                            <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ $totalGap }}</p>
                            <p class="mt-1 text-sm text-slate-500">уровней компетенций</p>
                        </div>
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                            <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18V9m6 9V5m6 13v-7m4 7H2" /></svg>
                        </span>
                    </div>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Зона внимания</p>
                            <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ $employeesWithoutNextStep->count() }}</p>
                            <p class="mt-1 text-sm text-slate-500">без доступного шага</p>
                        </div>
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                            <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10-3v6m3-3h-6" /></svg>
                        </span>
                    </div>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Активности</p>
                            <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ $totalActivities }}</p>
                            <p class="mt-1 text-sm text-slate-500">записей участия</p>
                        </div>
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                            <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V9m5 10V5m5 14v-7m5 7V3" /></svg>
                        </span>
                    </div>
                </article>

                <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm shadow-emerald-950/5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Завершение</p>
                            <p class="mt-3 text-3xl font-black tracking-tight text-emerald-950">{{ $completionRate }}%</p>
                            <p class="mt-1 text-sm text-emerald-700/80">{{ $completedActivities }} завершено</p>
                        </div>
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20">
                            <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 sm:p-7" aria-labelledby="skills-heading">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Фокус развития</p>
                        <h2 id="skills-heading" class="mt-2 text-xl font-black tracking-tight text-slate-950">Проседающие навыки</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Суммарный разрыв до требований следующего грейда.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Топ {{ min(8, $skillGaps->count()) }}</span>
                </div>

                <div class="mt-7 flex flex-col gap-5">
                    @forelse ($skillGaps->take(8) as $index => $skill)
                        @php
                            $gapValue = (int) ($skill['gap'] ?? 0);
                            $barWidth = max(4, (int) round($gapValue / $largestGap * 100));
                        @endphp
                        <div class="grid grid-cols-[2rem_minmax(0,1fr)_3rem] items-center gap-3">
                            <span class="text-center text-xs font-black tabular-nums text-slate-400">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="min-w-0">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ $skill['name'] ?? $skill['skill_id'] ?? 'Навык' }}</p>
                                    <span class="text-xs font-semibold text-slate-500">разрыв {{ $gapValue }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="{{ $skill['name'] ?? 'Навык' }}: разрыв {{ $gapValue }}" aria-valuemin="0" aria-valuemax="{{ $largestGap }}" aria-valuenow="{{ $gapValue }}">
                                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400" style="width: {{ $barWidth }}%"></div>
                                </div>
                            </div>
                            <span class="rounded-xl bg-slate-950 px-2 py-1.5 text-center text-sm font-black tabular-nums text-white">{{ $gapValue }}</span>
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/60 px-6 py-10 text-center">
                            <span class="flex size-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg></span>
                            <div><p class="font-bold text-emerald-950">Разрывов не найдено</p><p class="mt-1 text-sm text-emerald-700">Команда соответствует требованиям следующего грейда.</p></div>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 sm:p-7" aria-labelledby="attention-heading">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-rose-600">Требует решения</p>
                    <h2 id="attention-heading" class="mt-2 text-xl font-black tracking-tight text-slate-950">Без следующего шага</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Нет подходящих событий после проверки роли, грейда и пререквизитов.</p>
                </div>

                <div class="mt-6 flex max-h-[30rem] flex-col gap-3 overflow-y-auto pr-1">
                    @forelse ($employeesWithoutNextStep as $employee)
                        <a href="{{ route('employees.show', $employee) }}" class="group flex items-center gap-3 rounded-2xl border border-slate-200 p-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-black text-slate-700 transition group-hover:bg-emerald-100 group-hover:text-emerald-800" aria-hidden="true">{{ mb_strtoupper(mb_substr($employee->full_name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-slate-900">{{ $employee->full_name }}</span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $employee->role }} · {{ $employee->grade }}</span>
                            </span>
                            <svg class="size-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                        </a>
                    @empty
                        <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/60 px-5 py-10 text-center">
                            <span class="flex size-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg></span>
                            <div><p class="font-bold text-emerald-950">Маршрут есть у каждого</p><p class="mt-1 text-sm text-emerald-700">Для всех сотрудников найден следующий шаг.</p></div>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm shadow-slate-950/5" aria-labelledby="participation-heading">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-6 py-6 sm:flex-row sm:items-end sm:justify-between sm:px-7">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-700">Программы развития</p>
                    <h2 id="participation-heading" class="mt-2 text-xl font-black tracking-tight text-slate-950">Участие по событиям</h2>
                    <p id="participation-description" class="mt-1 text-sm text-slate-500">Количество и доля каждого статуса среди записей события.</p>
                </div>
                <p class="text-sm font-semibold text-slate-500">{{ $participationRows->count() }} событий</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left" aria-describedby="participation-description">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-7 py-4">Событие</th><th scope="col" class="px-4 py-4 text-center">Всего</th><th scope="col" class="px-4 py-4">Завершено</th><th scope="col" class="px-4 py-4 text-center">В процессе</th><th scope="col" class="px-4 py-4 text-center">Неявка</th><th scope="col" class="px-4 py-4 text-center">Прервано</th><th scope="col" class="px-4 py-4 text-center">Отказ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($participationRows as $event)
                            @php
                                $eventTotal = (int) ($event['total'] ?? 0);
                                $statuses = $event['statuses'] ?? [];
                                $statusCount = static fn (string $status): int => (int) ($statuses[$status] ?? 0);
                                $statusRate = static fn (string $status): int => $eventTotal > 0 ? (int) round($statusCount($status) / $eventTotal * 100) : 0;
                            @endphp
                            <tr class="transition hover:bg-slate-50/80">
                                <th scope="row" class="px-7 py-4"><div class="max-w-sm"><p class="font-bold text-slate-900">{{ $event['title'] ?? 'Событие' }}</p><p class="mt-1 font-mono text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $event['event_id'] ?? '—' }}</p></div></th>
                                <td class="px-4 py-4 text-center text-sm font-black tabular-nums text-slate-900">{{ $eventTotal }}</td>
                                <td class="px-4 py-4"><div class="flex min-w-32 items-center gap-3"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $statusRate('completed') }}%"></div></div><span class="w-10 text-right text-sm font-bold tabular-nums text-emerald-700" title="{{ $statusCount('completed') }} завершено">{{ $statusRate('completed') }}%</span></div></td>
                                <td class="px-4 py-4 text-center"><span class="inline-flex min-w-16 justify-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold tabular-nums text-sky-700" title="{{ $statusCount('in_progress') }} в процессе">{{ $statusRate('in_progress') }}%</span></td>
                                <td class="px-4 py-4 text-center"><span class="inline-flex min-w-16 justify-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold tabular-nums text-amber-700" title="{{ $statusCount('no_show') }} неявок">{{ $statusRate('no_show') }}%</span></td>
                                <td class="px-4 py-4 text-center"><span class="inline-flex min-w-16 justify-center rounded-full bg-orange-50 px-2.5 py-1 text-xs font-bold tabular-nums text-orange-700" title="{{ $statusCount('dropped') }} прервано">{{ $statusRate('dropped') }}%</span></td>
                                <td class="px-4 py-4 text-center"><span class="inline-flex min-w-16 justify-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold tabular-nums text-rose-700" title="{{ $statusCount('declined') }} отказов">{{ $statusRate('declined') }}%</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-7 py-14 text-center"><p class="font-bold text-slate-800">Данных об участии пока нет</p><p class="mt-1 text-sm text-slate-500">Статистика появится после импорта истории активностей.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
