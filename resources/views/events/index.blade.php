@extends('layout')

@section('title', 'Активности')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-3">
            <p class="eyebrow">Каталог развития</p>
            <h1 class="page-title">Активности</h1>
            <p class="muted">Управляйте обучением, расписанием и навыками, которые развивают сотрудники.</p>
        </div>
        <a href="{{ route('hr.events.create') }}" class="button-primary"><span aria-hidden="true">+</span> Добавить активность</a>
    </div>

    <section class="panel overflow-hidden" aria-label="Каталог активностей">
        <form method="get" action="{{ route('hr.events.index') }}" class="flex flex-wrap items-end gap-4 border-b border-slate-200 p-6">
            <div class="min-w-48 flex-1">
                <label for="event-search" class="mb-2 block text-xs font-semibold text-slate-600">Поиск</label>
                <input id="event-search" name="search" type="search" class="field" maxlength="200" value="{{ request('search') }}" placeholder="Название или ID">
            </div>
            <div class="w-full sm:w-52">
                <label for="event-format" class="mb-2 block text-xs font-semibold text-slate-600">Формат</label>
                <select id="event-format" name="format" class="field">
                    <option value="">Все форматы</option>
                    @foreach($formats as $value => $label)
                        <option value="{{ $value }}" @selected(request('format') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-48">
                <label for="event-type" class="mb-2 block text-xs font-semibold text-slate-600">Тип</label>
                <select id="event-type" name="type" class="field">
                    <option value="">Все типы</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button-primary">Найти</button>
            @if(request()->filled('search') || request()->filled('format') || request()->filled('type'))
                <a href="{{ route('hr.events.index') }}" class="py-3 text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-emerald-800">Сбросить</a>
            @endif
        </form>

        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Каталог обучения</h2>
            <span class="text-xs text-slate-500">Найдено: {{ $events->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs text-slate-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">Активность</th>
                        <th scope="col" class="px-6 py-3 font-medium">Формат и длительность</th>
                        <th scope="col" class="px-6 py-3 font-medium">Расписание</th>
                        <th scope="col" class="px-6 py-3 font-medium">Записей участия</th>
                        <th scope="col" class="px-6 py-3 font-medium"><span class="sr-only">Действия</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($events as $event)
                        @php
                            $futureSessions = collect($event->upcoming_sessions ?? [])->filter(fn ($date) => $date >= $asOf)->sort()->values();
                        @endphp
                        <tr class="transition hover:bg-emerald-50/30">
                            <td class="min-w-64 px-6 py-5">
                                <div class="flex flex-col gap-2">
                                    <a href="{{ route('hr.events.edit', $event) }}" class="font-semibold text-slate-900 hover:text-emerald-700">{{ $event->title }}</a>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs text-slate-500">{{ $types[$event->type] ?? $event->type }}</span>
                                        @if($event->mandatory)<span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Обязательная</span>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5"><div class="whitespace-nowrap">{{ $formats[$event->format] ?? $event->format }}</div><div class="mt-1 text-xs text-slate-500">{{ $event->duration_hours }} ч.</div></td>
                            <td class="px-6 py-5">
                                @if($event->format === 'self_paced')
                                    <span class="badge whitespace-nowrap">В любое время</span>
                                @elseif($futureSessions->isNotEmpty())
                                    <div class="whitespace-nowrap font-medium">{{ \Carbon\Carbon::parse($futureSessions->first())->format('d.m.Y') }}</div>
                                    <div class="mt-1 whitespace-nowrap text-xs text-slate-500">Предстоящих сессий: {{ $futureSessions->count() }}</div>
                                @else
                                    <span class="whitespace-nowrap text-amber-800">Нет будущих дат</span>
                                    <p class="mt-1 text-xs text-slate-500">Не попадает в рекомендации</p>
                                @endif
                            </td>
                            <td class="px-6 py-5 text-slate-600">{{ $event->activity_records_count }}</td>
                            <td class="px-6 py-5 text-right"><a href="{{ route('hr.events.edit', $event) }}" class="whitespace-nowrap font-semibold text-emerald-800">Изменить <span aria-hidden="true">→</span><span class="sr-only"> {{ $event->title }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-16 text-center"><p class="font-semibold text-slate-700">Активности не найдены</p><p class="mt-2 text-sm text-slate-500">Измените фильтры или добавьте новую активность.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events->hasPages())
            <div class="border-t border-slate-200 px-6 py-5">{{ $events->links() }}</div>
        @endif
    </section>
    <p class="text-xs leading-relaxed text-slate-500">В демо будущие даты считаются от {{ \Carbon\Carbon::parse($asOf)->format('d.m.Y') }}. Самостоятельные активности доступны в любое время.</p>
@endsection
