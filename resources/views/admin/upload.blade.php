@extends('layout')

@section('title', 'Импорт данных')
@section('eyebrow', 'Администрирование')
@section('page_title', 'Импорт данных')

@section('content')
    @php
        $imported = collect(session('imported', []));
        $importLabels = [
            'employees' => 'Сотрудники',
            'activity_records' => 'Записи активности',
            'skills' => 'Навыки',
            'role_profiles' => 'Профили ролей',
            'events' => 'События',
        ];
    @endphp

    <div class="mx-auto flex max-w-5xl flex-col gap-7">
        <header class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-8 py-9 text-white shadow-xl shadow-slate-950/10 sm:px-10">
            <div class="absolute -right-16 -top-20 size-64 rounded-full bg-emerald-400/20 blur-3xl" aria-hidden="true"></div>
            <div class="relative max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">
                    <span class="size-1.5 rounded-full bg-emerald-400"></span>
                    Управление данными
                </div>
                <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Импорт данных жюри</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">
                    Добавьте один или оба файла. Записи с существующими идентификаторами будут обновлены, новые — добавлены.
                </p>
            </div>
        </header>

        @if ($imported->isNotEmpty())
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950" role="status" aria-labelledby="upload-success-title">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20">
                        <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="upload-success-title" class="font-black">Импорт завершён</h2>
                        <p class="mt-1 text-sm leading-6 text-emerald-800">Данные проверены и объединены с текущим набором.</p>
                        <dl class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($imported as $entity => $count)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-white/70 px-3 py-2.5">
                                    <dt class="text-xs font-bold text-emerald-800">{{ $importLabels[$entity] ?? $entity }}</dt>
                                    <dd class="rounded-lg bg-emerald-100 px-2 py-1 text-sm font-black tabular-nums text-emerald-900">{{ $count }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        @endif

        <form method="post" enctype="multipart/form-data" action="{{ route('admin.upload.store') }}" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 sm:p-8" data-upload-form>
            @csrf

            <div class="flex flex-col gap-1">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Файлы проверки</p>
                <h2 class="text-xl font-black tracking-tight text-slate-950">Выберите наборы для объединения</h2>
                <p id="upload-help" class="text-sm leading-6 text-slate-500">Можно загрузить один файл или оба сразу. Максимальный размер каждого файла — 10 МБ.</p>
            </div>

            <fieldset class="mt-7 grid gap-5 lg:grid-cols-2" aria-describedby="upload-help">
                <legend class="sr-only">Файлы для импорта</legend>

                <div class="flex flex-col gap-2">
                    <div class="relative flex min-h-64 flex-col items-center justify-center overflow-hidden rounded-3xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-500/10 hover:border-emerald-400 hover:bg-emerald-50/50 data-[dragging=true]:scale-[1.01] data-[dragging=true]:border-emerald-500 data-[dragging=true]:bg-emerald-50 data-[has-file=true]:border-emerald-300 data-[has-file=true]:bg-emerald-50/70" data-upload-field data-upload-dropzone data-file-dropzone>
                        <input
                            id="employees-file"
                            type="file"
                            name="employees"
                            accept=".json,application/json"
                            class="absolute inset-0 z-10 size-full cursor-pointer opacity-0"
                            aria-describedby="employees-hint employees-filename @error('employees') employees-error @enderror"
                            @error('employees') aria-invalid="true" @enderror
                            data-upload-input
                            data-file-input
                            data-filename-target="#employees-filename"
                        >
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-emerald-700 shadow-sm ring-1 ring-slate-200" aria-hidden="true">
                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 15h8m-8 3h5" /></svg>
                        </span>
                        <label for="employees-file" class="mt-5 text-base font-black text-slate-900">employees.json</label>
                        <p id="employees-hint" class="mt-2 max-w-xs text-sm leading-6 text-slate-500">Перетащите JSON сюда или <span class="font-bold text-emerald-700">выберите файл</span></p>
                        <p id="employees-filename" class="mt-3 min-h-5 max-w-full truncate rounded-full bg-slate-200/70 px-3 py-1 text-xs font-bold text-slate-600" data-upload-filename data-file-name aria-live="polite">Файл не выбран</p>
                    </div>
                    @error('employees')
                        <p id="employees-error" class="text-sm font-semibold text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <div class="relative flex min-h-64 flex-col items-center justify-center overflow-hidden rounded-3xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-500/10 hover:border-emerald-400 hover:bg-emerald-50/50 data-[dragging=true]:scale-[1.01] data-[dragging=true]:border-emerald-500 data-[dragging=true]:bg-emerald-50 data-[has-file=true]:border-emerald-300 data-[has-file=true]:bg-emerald-50/70" data-upload-field data-upload-dropzone data-file-dropzone>
                        <input
                            id="activity-history-file"
                            type="file"
                            name="activity_history"
                            accept=".csv,text/csv"
                            class="absolute inset-0 z-10 size-full cursor-pointer opacity-0"
                            aria-describedby="activity-history-hint activity-history-filename @error('activity_history') activity-history-error @enderror"
                            @error('activity_history') aria-invalid="true" @enderror
                            data-upload-input
                            data-file-input
                            data-filename-target="#activity-history-filename"
                        >
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-sky-700 shadow-sm ring-1 ring-slate-200" aria-hidden="true">
                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Z" /><path stroke-linecap="round" d="M4 9h16M9 9v12m6-12v12M4 15h16" /></svg>
                        </span>
                        <label for="activity-history-file" class="mt-5 text-base font-black text-slate-900">activity_history.csv</label>
                        <p id="activity-history-hint" class="mt-2 max-w-xs text-sm leading-6 text-slate-500">Перетащите CSV сюда или <span class="font-bold text-sky-700">выберите файл</span></p>
                        <p id="activity-history-filename" class="mt-3 min-h-5 max-w-full truncate rounded-full bg-slate-200/70 px-3 py-1 text-xs font-bold text-slate-600" data-upload-filename data-file-name aria-live="polite">Файл не выбран</p>
                    </div>
                    @error('activity_history')
                        <p id="activity-history-error" class="text-sm font-semibold text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
            </fieldset>

            @error('files')
                <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" role="alert">{{ $message }}</p>
            @enderror

            <div class="mt-7 flex flex-col gap-4 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-2 text-sm leading-6 text-slate-500">
                    <svg class="mt-1 size-4 shrink-0 text-slate-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" /></svg>
                    <p>Импорт выполняется транзакционно: при ошибке исходные данные сохранятся.</p>
                </div>
                <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70" data-upload-submit>
                    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" /></svg>
                    <span>Проверить и импортировать</span>
                </button>
            </div>
        </form>
    </div>
@endsection
