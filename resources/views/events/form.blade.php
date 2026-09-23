@extends('layout')

@section('title', $event->exists ? 'Редактирование активности' : 'Новая активность')

@section('content')
    @php
        $textValue = fn (mixed $value): string => is_scalar($value) ? (string) $value : '';
        $hasOldInput = session()->hasOldInput();
        $selectedRoles = (array) old('target_roles', $hasOldInput ? [] : ($event->target_roles ?? []));
        $selectedGrades = (array) old('target_grades', $hasOldInput ? [] : ($event->target_grades ?? []));
        $developmentRows = $hasHistory ? ($event->develops_skills ?? []) : (array) old('develops_skills', $hasOldInput ? [] : ($event->develops_skills ?? []));
        $prerequisiteRows = (array) old('prerequisite_skills', $hasOldInput ? [] : collect($event->prerequisites ?? [])->map(fn ($level, $skillId) => ['skill_id' => $skillId, 'min_level' => $level])->values()->all());
        $sessionRows = (array) old('upcoming_sessions', $hasOldInput ? [] : ($event->upcoming_sessions ?? []));
        $selectedFormat = old('format', $event->format ?? 'online');
    @endphp

    <div class="flex flex-col gap-3">
        <a href="{{ route('hr.events.index') }}" class="w-fit text-sm font-medium text-emerald-800"><span aria-hidden="true">←</span> Все активности</a>
        <h1 class="page-title">{{ $event->exists ? 'Редактирование активности' : 'Новая активность' }}</h1>
        <p class="muted">{{ $event->exists ? $event->title : 'Добавьте обучение в каталог и укажите, кому оно подходит.' }}</p>
    </div>

    <form method="post" action="{{ $event->exists ? route('hr.events.update', $event) : route('hr.events.store') }}" data-event-form class="flex flex-col gap-6">
        @csrf
        @if($event->exists) @method('PUT') @endif
        <noscript><p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Включите JavaScript, чтобы добавлять навыки, даты и переключать формат активности.</p></noscript>

        <section class="panel flex flex-col gap-6 p-6 lg:p-8" aria-labelledby="event-basics-heading">
            <h2 id="event-basics-heading" class="section-title">Об активности</h2>
            <div>
                <label for="title" class="mb-2 block text-sm font-semibold text-slate-700">Название <span class="text-red-700" aria-hidden="true">*</span></label>
                <input id="title" name="title" value="{{ $textValue(old('title', $event->title)) }}" required maxlength="255" class="field" @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
                @error('title')<p id="title-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">Описание <span class="text-red-700" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" required rows="4" maxlength="5000" class="field" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ $textValue(old('description', $event->description)) }}</textarea>
                @error('description')<p id="description-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">Тип <span class="text-red-700" aria-hidden="true">*</span></label>
                    <select id="type" name="type" required class="field" @error('type') aria-invalid="true" aria-describedby="type-error" @enderror>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $event->type ?? 'course') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p id="type-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="format" class="mb-2 block text-sm font-semibold text-slate-700">Формат <span class="text-red-700" aria-hidden="true">*</span></label>
                    <select id="format" name="format" required class="field" @error('format') aria-invalid="true" aria-describedby="format-error" @enderror>
                        @foreach($formats as $value => $label)
                            <option value="{{ $value }}" @selected($selectedFormat === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('format')<p id="format-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="duration_hours" class="mb-2 block text-sm font-semibold text-slate-700">Длительность, часы <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="duration_hours" name="duration_hours" type="number" required min="0.01" max="999999.99" step="0.01" value="{{ $textValue(old('duration_hours', $event->duration_hours)) }}" class="field" @error('duration_hours') aria-invalid="true" aria-describedby="duration-error" @enderror>
                    @error('duration_hours')<p id="duration-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <input type="hidden" name="mandatory" value="0">
                <label class="flex items-start gap-3 text-sm font-medium text-slate-700"><input type="checkbox" name="mandatory" value="1" @checked(old('mandatory', $event->mandatory ?? false)) class="mt-0.5 size-4 accent-emerald-800"><span>Обязательная активность<span class="mt-1 block text-xs font-normal text-slate-500">Не участвует в подборе персональных рекомендаций.</span></span></label>
                @error('mandatory')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="panel flex flex-col gap-6 p-6 lg:p-8" aria-labelledby="event-audience-heading">
            <div class="flex flex-col gap-2"><h2 id="event-audience-heading" class="section-title">Кому подходит</h2><p class="muted">Выберите хотя бы одну роль и один грейд.</p></div>
            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-slate-700">Роли</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach($roles as $role)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"><input type="checkbox" name="target_roles[]" value="{{ $role }}" @checked(in_array($role, $selectedRoles, true)) class="size-4 accent-emerald-800">{{ $role }}</label>
                    @endforeach
                </div>
                @foreach($errors->get('target_roles') as $message)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach
                @foreach($errors->get('target_roles.*') as $messages) @foreach($messages as $message)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach @endforeach
            </fieldset>
            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-slate-700">Грейды</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach($grades as $grade)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"><input type="checkbox" name="target_grades[]" value="{{ $grade }}" @checked(in_array($grade, $selectedGrades, true)) class="size-4 accent-emerald-800">{{ $grade }}</label>
                    @endforeach
                </div>
                @foreach($errors->get('target_grades') as $message)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach
                @foreach($errors->get('target_grades.*') as $messages) @foreach($messages as $message)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach @endforeach
            </fieldset>
        </section>

        <section class="panel flex flex-col gap-5 p-6 lg:p-8" aria-labelledby="event-skills-heading">
            <div class="flex flex-col gap-2"><h2 id="event-skills-heading" class="section-title">Развиваемые навыки</h2><p class="muted">Укажите прирост и максимальный уровень после завершения. Можно оставить список пустым.</p></div>
            @if($hasHistory)
                <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-relaxed text-amber-900">У активности есть история участия. Развиваемые навыки нельзя менять: это сохраняет корректность уже рассчитанного прогресса сотрудников.</p>
            @endif
            @error('develops_skills')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <fieldset data-row-group="develops_skills" @disabled($hasHistory) class="flex min-w-0 flex-col gap-4 disabled:opacity-70">
                <legend class="sr-only">Список развиваемых навыков</legend>
                <div data-row-container class="flex flex-col gap-3">
                    @foreach($developmentRows as $index => $row)
                        @include('events._skill-row', ['kind' => 'develops_skills', 'index' => $index, 'row' => $row, 'locked' => $hasHistory])
                    @endforeach
                </div>
                <template>@include('events._skill-row', ['kind' => 'develops_skills', 'index' => '__INDEX__', 'row' => [], 'locked' => $hasHistory])</template>
                <button type="button" data-add-row @disabled($hasHistory) class="button-secondary w-fit disabled:cursor-not-allowed">+ Добавить навык</button>
            </fieldset>
        </section>

        <section class="panel flex flex-col gap-5 p-6 lg:p-8" aria-labelledby="event-prerequisites-heading">
            <div class="flex flex-col gap-2"><h2 id="event-prerequisites-heading" class="section-title">Требования к участникам</h2><p class="muted">Какие навыки уже нужны для участия. Если требований нет, оставьте список пустым.</p></div>
            @error('prerequisite_skills')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <div data-row-group="prerequisite_skills" class="flex flex-col gap-4">
                <div data-row-container class="flex flex-col gap-3">
                    @foreach($prerequisiteRows as $index => $row)
                        @include('events._skill-row', ['kind' => 'prerequisite_skills', 'index' => $index, 'row' => $row, 'locked' => false])
                    @endforeach
                </div>
                <template>@include('events._skill-row', ['kind' => 'prerequisite_skills', 'index' => '__INDEX__', 'row' => [], 'locked' => false])</template>
                <button type="button" data-add-row class="button-secondary w-fit">+ Добавить требование</button>
            </div>
        </section>

        <section class="panel flex flex-col gap-5 p-6 lg:p-8" aria-labelledby="event-schedule-heading">
            <h2 id="event-schedule-heading" class="section-title">Расписание</h2>
            <p data-self-paced-hint @if($selectedFormat !== 'self_paced') hidden @endif class="muted">Самостоятельную активность можно пройти в любое время. Даты сессий не требуются и не сохраняются.</p>
            <fieldset data-schedule data-row-group="upcoming_sessions" @disabled($selectedFormat === 'self_paced') @if($selectedFormat === 'self_paced') hidden @endif class="flex min-w-0 flex-col gap-4">
                <legend class="sr-only">Даты сессий</legend>
                <p class="muted">Добавьте хотя бы одну дату для онлайн- или очной активности. Можно указать несколько сессий.</p>
                @error('upcoming_sessions')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <div data-row-container class="flex max-w-xl flex-col gap-3">
                    @foreach($sessionRows as $index => $date)
                        @include('events._session-row', ['index' => $index, 'date' => $date])
                    @endforeach
                </div>
                <template>@include('events._session-row', ['index' => '__INDEX__', 'date' => ''])</template>
                <button type="button" data-add-row class="button-secondary w-fit">+ Добавить дату</button>
                <p class="text-xs leading-relaxed text-slate-500">В демо будущие сессии считаются от 01.10.2026. Если все даты прошли, активность не попадает в рекомендации.</p>
            </fieldset>
        </section>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="button-primary">{{ $event->exists ? 'Сохранить изменения' : 'Создать активность' }}</button>
            <a href="{{ route('hr.events.index') }}" class="button-secondary">Отмена</a>
        </div>
    </form>

    @if($event->exists)
        <section class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6" aria-labelledby="delete-heading">
            <div class="flex flex-col gap-2"><h2 id="delete-heading" class="text-sm font-semibold text-slate-700">Удаление активности</h2><p class="muted">{{ $hasHistory ? 'Удаление недоступно: у активности есть история участия сотрудников.' : 'Активность будет удалена из каталога и сохранённых рекомендаций.' }}</p></div>
            @unless($hasHistory)
                <form method="post" action="{{ route('hr.events.destroy', $event) }}" data-event-delete>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-semibold text-red-700 hover:bg-red-50">Удалить активность</button>
                </form>
            @endunless
        </section>
    @endif
@endsection
