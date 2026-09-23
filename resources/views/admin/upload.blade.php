@extends('layout')

@section('title', 'Импорт данных')

@section('content')
    <div class="flex flex-col gap-3">
        <p class="eyebrow">Управление данными</p>
        <h1 class="page-title">Импорт данных</h1>
        <p class="muted">Добавьте проверочные профили и историю активностей для расчёта рекомендаций.</p>
    </div>

    @if(session()->has('imported'))
        <div role="status" class="flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900">
            <h2 class="font-semibold">Импорт завершён</h2>
            <p class="text-sm">Данные сохранены. Профили и аналитика используют обновлённую информацию.</p>
            <dl class="flex flex-wrap gap-6 text-sm">
                @if(array_key_exists('employees', session('imported')))
                    <div class="flex items-baseline gap-2"><dt>Обработано сотрудников:</dt><dd class="font-bold">{{ session('imported.employees') }}</dd></div>
                @endif
                @if(array_key_exists('activity_records', session('imported')))
                    <div class="flex items-baseline gap-2"><dt>Записей истории:</dt><dd class="font-bold">{{ session('imported.activity_records') }}</dd></div>
                @endif
            </dl>
            <a href="{{ route('employees.index') }}" class="w-fit text-sm font-semibold underline underline-offset-4">Открыть сотрудников →</a>
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <form method="post" enctype="multipart/form-data" action="{{ route('admin.upload.store') }}" class="panel flex flex-col gap-6 p-6 lg:p-8">
            @csrf
            <div class="flex flex-col gap-2">
                <h2 class="section-title">Загрузить файлы</h2>
                <p class="muted">Выберите один или оба файла в формате стартового набора. Максимум 10 МБ на файл.</p>
            </div>

            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-5">
                <label for="employees-file" class="block text-sm font-semibold text-slate-800">Профили сотрудников</label>
                <p id="employees-hint" class="mt-1 text-xs leading-relaxed text-slate-500">employees.json · Роли, грейды, навыки и карьерные цели</p>
                <input id="employees-file" type="file" name="employees" accept=".json,application/json" aria-describedby="employees-hint @error('employees') employees-error @enderror" @error('employees') aria-invalid="true" @enderror class="mt-4 block w-full text-sm text-slate-500 file:mr-4 file:cursor-pointer file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-emerald-50">
                @error('employees')<p id="employees-error" class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-5">
                <label for="activity-file" class="block text-sm font-semibold text-slate-800">История активностей</label>
                <p id="activity-hint" class="mt-1 text-xs leading-relaxed text-slate-500">activity_history.csv · Участие, статусы и результаты обучения</p>
                <input id="activity-file" type="file" name="activity_history" accept=".csv,text/csv" aria-describedby="activity-hint @error('activity_history') activity-error @enderror" @error('activity_history') aria-invalid="true" @enderror class="mt-4 block w-full text-sm text-slate-500 file:mr-4 file:cursor-pointer file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-emerald-50">
                @error('activity_history')<p id="activity-error" class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6">
                <p class="text-xs text-slate-500">Существующие записи обновляются по ID.</p>
                <button type="submit" class="button-primary">Импортировать данные <span aria-hidden="true">↑</span></button>
            </div>
        </form>

        <aside class="panel flex flex-col gap-5 p-6">
            <h2 class="section-title">Как работает импорт</h2>
            <ol class="flex flex-col gap-5 text-sm leading-relaxed text-slate-600">
                <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-800">1</span><span>Новые сотрудники и записи истории добавляются в базу.</span></li>
                <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-800">2</span><span>Записи с совпадающими идентификаторами обновляются, без создания дубликатов.</span></li>
                <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-800">3</span><span>После загрузки откройте профиль и получите рекомендации на новых данных.</span></li>
            </ol>
            <p class="border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">Если загружаете историю для новых сотрудников, добавьте оба файла одновременно.</p>
        </aside>
    </div>
@endsection
