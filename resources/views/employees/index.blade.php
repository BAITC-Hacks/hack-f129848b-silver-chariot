@extends('layout')

@section('title', session('role', 'employee') === 'hr' ? 'Сотрудники' : 'Моё развитие')
@section('eyebrow', session('role', 'employee') === 'hr' ? 'Команда' : 'Личный кабинет')
@section('page_title', session('role', 'employee') === 'hr' ? 'Каталог сотрудников' : 'Моя траектория развития')

@section('content')
@php
    $isHr = session('role', 'employee') === 'hr';
    $pageEmployees = $employees->getCollection();
    $roleOptions = $pageEmployees->pluck('role')->filter()->unique()->sort()->values();
    $gradeOrder = collect(['Junior', 'Middle', 'Senior', 'Lead']);
    $gradeOptions = $gradeOrder->filter(fn (string $grade): bool => $pageEmployees->contains('grade', $grade));
@endphp

<div data-employee-directory>
    <section class="relative overflow-hidden rounded-[28px] bg-[#0b3529] p-7 text-white shadow-[0_24px_60px_rgba(8,44,34,0.14)] xl:p-8">
        <div class="pointer-events-none absolute -right-14 -top-24 size-72 rounded-full border-[48px] border-white/5"></div>
        <div class="pointer-events-none absolute right-28 top-10 size-32 rounded-full bg-[#d8ff62]/10 blur-3xl"></div>

        <div class="relative flex items-end justify-between gap-10">
            <div class="max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-medium text-emerald-50/80">
                    <span class="size-1.5 rounded-full bg-[#d8ff62]"></span>
                    {{ $isHr ? 'Единый обзор талантов' : 'Персональный маршрут' }}
                </div>
                <h2 class="text-3xl font-semibold leading-tight tracking-[-0.035em] xl:text-[34px]">
                    {{ $isHr ? 'Помогайте команде расти точечно' : 'Следующий карьерный шаг начинается здесь' }}
                </h2>
                <p class="mt-3 max-w-xl text-sm leading-6 text-emerald-50/65">
                    {{ $isHr
                        ? 'Находите сотрудников, проверяйте готовность к следующему грейду и переходите к персональному плану развития.'
                        : 'Откройте профиль, чтобы увидеть разрывы навыков и получить рекомендации под вашу карьерную цель.' }}
                </p>
            </div>

            <div class="grid shrink-0 grid-cols-2 gap-3">
                <div class="min-w-28 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 backdrop-blur-sm">
                    <p class="text-2xl font-semibold tracking-tight">{{ number_format($employees->total(), 0, ',', ' ') }}</p>
                    <p class="mt-0.5 text-[11px] text-emerald-100/55">{{ $isHr ? 'сотрудников' : 'профиль' }}</p>
                </div>
                <div class="min-w-28 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 backdrop-blur-sm">
                    <p class="text-2xl font-semibold tracking-tight">4</p>
                    <p class="mt-0.5 text-[11px] text-emerald-100/55">карьерных грейда</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_16px_40px_rgba(15,23,42,0.05)]">
        <div class="flex items-center justify-between gap-6 border-b border-slate-100 px-6 py-5">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900">{{ $isHr ? 'Сотрудники' : 'Ваш профиль' }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <span data-visible-count>{{ $pageEmployees->count() }}</span> из {{ $employees->total() }} на этой странице
                </p>
            </div>

            <form method="get" action="{{ route('employees.index') }}" class="flex flex-1 items-center justify-end gap-3" data-employee-filters>
                <label class="relative w-full max-w-sm">
                    <span class="sr-only">Поиск сотрудника</span>
                    <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-3.5 top-1/2 size-4.5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/>
                    </svg>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Имя, роль или грейд"
                           autocomplete="off" data-employee-search
                           class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10">
                </label>

                @if($isHr)
                    <label class="relative">
                        <span class="sr-only">Фильтр по роли</span>
                        <select data-employee-role-filter class="h-11 min-w-44 appearance-none rounded-xl border border-slate-200 bg-white pl-3.5 pr-9 text-sm font-medium text-slate-700 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                            <option value="">Все роли</option>
                            @foreach($roleOptions as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 24 24" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 10 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </label>

                    <label class="relative">
                        <span class="sr-only">Фильтр по грейду</span>
                        <select data-employee-grade-filter class="h-11 min-w-36 appearance-none rounded-xl border border-slate-200 bg-white pl-3.5 pr-9 text-sm font-medium text-slate-700 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                            <option value="">Все грейды</option>
                            @foreach($gradeOptions as $grade)
                                <option value="{{ $grade }}">{{ $grade }}</option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 24 24" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 10 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </label>
                @endif

                <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-700 text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-500/20" title="Искать по всей базе">
                    <svg viewBox="0 0 24 24" class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="sr-only">Применить поиск</span>
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left">
                <thead>
                <tr class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    <th class="px-6 py-3.5">Сотрудник</th>
                    <th class="px-5 py-3.5">Направление</th>
                    <th class="px-5 py-3.5">Грейд</th>
                    <th class="px-5 py-3.5">Карьерная цель</th>
                    <th class="px-6 py-3.5 text-right">Профиль</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" data-employee-rows>
                @forelse($employees as $employee)
                    @php
                        $nameParts = preg_split('/\s+/u', trim($employee->full_name));
                        $initials = collect($nameParts)->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                        $targetGrade = data_get($employee->career_goal, 'target_grade', 'Не указана');
                        $targetRole = data_get($employee->career_goal, 'target_role', $employee->role);
                    @endphp
                    <tr class="group transition hover:bg-emerald-50/35"
                        data-employee-row
                        data-name="{{ Illuminate\Support\Str::lower($employee->full_name) }}"
                        data-role="{{ $employee->role }}"
                        data-grade="{{ $employee->grade }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3.5">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#e7f4ee] text-xs font-bold text-emerald-800 ring-1 ring-emerald-700/5 transition group-hover:bg-emerald-700 group-hover:text-white">{{ $initials }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('employees.show', $employee) }}" class="font-semibold text-slate-900 transition hover:text-emerald-700">{{ $employee->full_name }}</a>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $employee->employee_id }} · {{ $employee->work_format === 'remote' ? 'Удалённо' : ($employee->work_format === 'hybrid' ? 'Гибрид' : 'Офис') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-sm font-medium text-slate-700">{{ $employee->role }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $employee->department }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                                'bg-sky-50 text-sky-700 ring-sky-200' => $employee->grade === 'Junior',
                                'bg-violet-50 text-violet-700 ring-violet-200' => $employee->grade === 'Middle',
                                'bg-amber-50 text-amber-700 ring-amber-200' => $employee->grade === 'Senior',
                                'bg-emerald-50 text-emerald-700 ring-emerald-200' => $employee->grade === 'Lead',
                            ])>{{ $employee->grade }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-lime-50 text-emerald-700">
                                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 19 19 5M10 5h9v9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">{{ $targetGrade }}</p>
                                    <p class="max-w-48 truncate text-xs text-slate-400">{{ $targetRole }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('employees.show', $employee) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 hover:shadow">
                                Открыть
                                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <p class="font-semibold text-slate-700">Сотрудники не найдены</p>
                            <p class="mt-1 text-sm text-slate-400">Попробуйте изменить поисковый запрос</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="hidden px-6 py-14 text-center" data-employee-empty>
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
            </span>
            <p class="mt-3 font-semibold text-slate-700">Ничего не найдено</p>
            <p class="mt-1 text-sm text-slate-400">Сбросьте фильтры или измените запрос</p>
        </div>

        @if($employees->hasPages())
            <div class="border-t border-slate-100 px-6 py-4">
                {{ $employees->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
