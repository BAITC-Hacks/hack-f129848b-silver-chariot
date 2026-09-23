@extends('layout')

@section('title', 'HR-аналитика')

@section('content')
    @php
        $totalParticipations = array_sum(array_column($participation, 'total'));
        $completedParticipations = array_sum(array_map(fn (array $event): int => $event['statuses']['completed'] ?? 0, $participation));
        $completionRate = $totalParticipations > 0 ? round($completedParticipations / $totalParticipations * 100) : 0;
        $largestGap = max(array_column($skill_gaps, 'gap') ?: [1]);
        $statusLabels = ['completed' => 'Завершено', 'in_progress' => 'В процессе', 'no_show' => 'Неявка', 'dropped' => 'Прервано', 'declined' => 'Отказ', 'overdue' => 'Просрочено'];
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-3">
            <p class="eyebrow">Развитие команды</p>
            <h1 class="page-title">HR-аналитика</h1>
            <p class="muted">Дефицит навыков, доступность следующего шага и участие в обучении.</p>
        </div>
        <a class="button-secondary" href="{{ route('admin.upload') }}">Импортировать данные <span aria-hidden="true">↗</span></a>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div class="panel flex flex-col gap-3 p-6">
            <p class="text-sm text-slate-500">Навыков с разрывом</p>
            <p class="text-4xl font-semibold tracking-tight text-slate-900">{{ count($skill_gaps) }}</p>
            <p class="text-xs text-slate-500">Относительно следующего грейда</p>
        </div>
        <div class="panel flex flex-col gap-3 p-6">
            <p class="text-sm text-slate-500">Без рекомендованного шага</p>
            <p class="text-4xl font-semibold tracking-tight text-amber-700">{{ $employees_without_next_step->count() }}</p>
            <p class="text-xs text-slate-500">Сотрудники без подходящих активностей</p>
        </div>
        <div class="panel flex flex-col gap-3 p-6">
            <p class="text-sm text-slate-500">Завершение активностей</p>
            <p class="text-4xl font-semibold tracking-tight text-emerald-800">{{ $completionRate }}<span class="text-2xl">%</span></p>
            <p class="text-xs text-slate-500">{{ $completedParticipations }} из {{ $totalParticipations }} записей участия</p>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="panel" aria-labelledby="skill-gaps-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-6">
                <h2 id="skill-gaps-heading" class="section-title">Проседающие навыки</h2>
                <p class="muted">Суммарный разрыв в уровнях по команде. Чем больше значение, тем выше потребность в развитии.</p>
            </div>
            <ol class="max-h-[30rem] space-y-5 overflow-y-auto p-6">
                @forelse($skill_gaps as $gap)
                    <li>
                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                            <span class="font-medium text-slate-700">{{ $gap['name'] }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-slate-900">{{ $gap['gap'] }} <span class="font-normal text-slate-400">ур.</span></span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-emerald-600" style="width: {{ round($gap['gap'] / $largestGap * 100, 1) }}%"></div></div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-slate-500">Разрывы до следующего грейда не обнаружены.</li>
                @endforelse
            </ol>
        </section>

        <section class="panel" aria-labelledby="no-next-step-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-6">
                <h2 id="no-next-step-heading" class="section-title">Без рекомендованного шага</h2>
                <p class="muted">Движок не нашёл доступных активностей с вкладом в развитие. Откройте профиль, чтобы оценить траекторию.</p>
            </div>
            <ul class="max-h-[30rem] divide-y divide-slate-100 overflow-y-auto">
                @forelse($employees_without_next_step as $employee)
                    <li>
                        <a href="{{ route('employees.show', $employee) }}" class="flex items-center justify-between gap-4 px-6 py-4 transition hover:bg-slate-50">
                            <span class="flex flex-col gap-1"><span class="text-sm font-semibold text-slate-800">{{ $employee->full_name }}</span><span class="text-xs text-slate-500">{{ $employee->role }} · {{ $employee->grade }}</span></span>
                            <span class="text-emerald-700" aria-hidden="true">→</span>
                        </a>
                    </li>
                @empty
                    <li class="flex flex-col items-center gap-3 px-6 py-12 text-center">
                        <span class="flex size-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-700" aria-hidden="true">✓</span>
                        <p class="text-sm font-semibold text-slate-700">У каждого есть следующий шаг</p>
                        <p class="text-xs text-slate-500">Для всех сотрудников найдены подходящие активности.</p>
                    </li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="panel overflow-hidden" aria-labelledby="participation-heading">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-6">
            <h2 id="participation-heading" class="section-title">Участие по активностям</h2>
            <p class="muted">В каждой ячейке — доля статуса и число записей участия. Проценты рассчитаны от общего числа записей активности.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th scope="col" class="min-w-64 px-6 py-4 font-medium">Активность</th>
                        <th scope="col" class="px-4 py-4 text-right font-medium">Всего</th>
                        @foreach($statusLabels as $status => $label)
                            <th scope="col" class="whitespace-nowrap px-4 py-4 text-right font-medium">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($participation as $event)
                        <tr class="hover:bg-slate-50/60">
                            <th scope="row" class="px-6 py-4 text-left font-medium text-slate-700"><span class="block">{{ $event['title'] }}</span><span class="mt-1 block text-xs font-normal text-slate-400">{{ $event['event_id'] }}</span></th>
                            <td class="px-4 py-4 text-right font-semibold tabular-nums">{{ $event['total'] }}</td>
                            @foreach($statusLabels as $status => $label)
                                @php($statusCount = $event['statuses'][$status] ?? 0)
                                <td class="px-4 py-4 text-right tabular-nums">
                                    <span @class(['font-medium', 'text-emerald-700' => $status === 'completed' && $statusCount > 0, 'text-slate-600' => $status !== 'completed' || $statusCount === 0])>{{ $event['total'] > 0 ? round($statusCount / $event['total'] * 100) . '%' : '—' }}</span>
                                    <span class="mt-1 block text-xs text-slate-400">{{ $statusCount }}</span>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-12 text-center text-slate-500">Активности ещё не загружены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
