@extends('layout')

@section('title', session('role', 'employee') === 'hr' ? 'Сотрудники' : 'Моё развитие')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-3">
            <p class="eyebrow">{{ session('role', 'employee') === 'hr' ? 'Команда и развитие' : 'Ваш следующий шаг' }}</p>
            <h1 class="page-title">{{ session('role', 'employee') === 'hr' ? 'Сотрудники' : 'Моё развитие' }}</h1>
            <p class="muted">Откройте профиль, чтобы увидеть навыки, карьерную траекторию и рекомендации.</p>
        </div>
        @if(session('role', 'employee') === 'hr')
            <a class="button-secondary" href="{{ route('hr.index') }}">К аналитике команды <span aria-hidden="true">↗</span></a>
        @endif
    </div>

    @if(session('role', 'employee') !== 'hr')
        <div class="flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2"/><path d="M7 8V5a3 3 0 0 1 6 0v3"/></svg>
            <p>В режиме сотрудника доступен только ваш профиль. Навыки и история других сотрудников скрыты.</p>
        </div>
    @endif

    <section class="panel overflow-hidden" aria-label="Список сотрудников">
        <form method="get" action="{{ route('employees.index') }}" class="flex flex-wrap items-end gap-4 border-b border-slate-200 p-6">
            <div class="min-w-48 flex-1">
                <label for="employee-search" class="mb-2 block text-xs font-semibold text-slate-600">Поиск</label>
                <input id="employee-search" class="field" name="search" type="search" maxlength="200" value="{{ request('search') }}" placeholder="Имя, роль или грейд">
            </div>
            <div class="w-48">
                <label for="employee-role" class="mb-2 block text-xs font-semibold text-slate-600">Роль</label>
                <select id="employee-role" name="role" class="field">
                    <option value="">Все роли</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label for="employee-grade" class="mb-2 block text-xs font-semibold text-slate-600">Грейд</label>
                <select id="employee-grade" name="grade" class="field">
                    <option value="">Все грейды</option>
                    @foreach($grades as $grade)
                        <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button-primary">Найти</button>
            @if(request()->filled('search') || request()->filled('role') || request()->filled('grade'))
                <a href="{{ route('employees.index') }}" class="py-3 text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-emerald-800">Сбросить</a>
            @endif
        </form>

        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-slate-700">{{ session('role', 'employee') === 'hr' ? 'Профили сотрудников' : 'Мой профиль' }}</h2>
            <span class="text-xs text-slate-500">Найдено: {{ $employees->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs text-slate-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">Сотрудник</th>
                        <th scope="col" class="px-6 py-3 font-medium">Роль и подразделение</th>
                        <th scope="col" class="px-6 py-3 font-medium">Грейд</th>
                        <th scope="col" class="px-6 py-3 font-medium"><span class="sr-only">Профиль</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($employees as $employee)
                        <tr class="transition hover:bg-emerald-50/30">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-semibold text-emerald-800" aria-hidden="true">{{ mb_substr($employee->full_name, 0, 1) }}</span>
                                    <div class="flex flex-col gap-1">
                                        <a href="{{ route('employees.show', $employee) }}" class="font-semibold text-slate-900 hover:text-emerald-700">{{ $employee->full_name }}</a>
                                        <span class="text-xs text-slate-400">{{ $employee->employee_id }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5"><div class="font-medium">{{ $employee->role }}</div><div class="mt-1 text-xs text-slate-500">{{ $employee->department }}</div></td>
                            <td class="px-6 py-5"><span class="badge">{{ $employee->grade }}</span></td>
                            <td class="px-6 py-5 text-right"><a href="{{ route('employees.show', $employee) }}" class="whitespace-nowrap text-sm font-semibold text-emerald-800">Открыть профиль <span aria-hidden="true">→</span><span class="sr-only"> {{ $employee->full_name }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-16 text-center"><p class="font-semibold text-slate-700">Сотрудники не найдены</p><p class="mt-2 text-sm text-slate-500">Попробуйте изменить поисковый запрос или сбросить фильтры.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($employees->hasPages())
            <div class="border-t border-slate-200 px-6 py-5">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection
