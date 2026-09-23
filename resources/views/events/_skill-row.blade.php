@php
    $isDevelopment = $kind === 'develops_skills';
    $row = is_array($row) ? $row : [];
    $skillField = $kind.'.'.$index.'.skill_id';
    $levelField = $kind.'.'.$index.'.'.($isDevelopment ? 'max_level' : 'min_level');
@endphp
<div data-row-index="{{ $index }}" class="grid items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
    <div @class(['sm:col-span-2 lg:col-span-1' => $isDevelopment, 'sm:col-span-2' => ! $isDevelopment])>
        <label for="{{ $kind }}-{{ $index }}-skill" class="mb-2 block text-xs font-semibold text-slate-600">Навык</label>
        <select id="{{ $kind }}-{{ $index }}-skill" name="{{ $kind }}[{{ $index }}][skill_id]" class="field" @error($skillField) aria-invalid="true" aria-describedby="{{ $kind }}-{{ $index }}-skill-error" @enderror>
            <option value="">Выберите навык</option>
            @foreach($skills as $skill)
                <option value="{{ $skill->skill_id }}" @selected(($row['skill_id'] ?? '') === $skill->skill_id)>{{ $skill->name }}</option>
            @endforeach
        </select>
        @error($skillField)<p id="{{ $kind }}-{{ $index }}-skill-error" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    @if($isDevelopment)
        <div>
            <label for="{{ $kind }}-{{ $index }}-gain" class="mb-2 block text-xs font-semibold text-slate-600">Прирост уровня</label>
            <input id="{{ $kind }}-{{ $index }}-gain" type="number" name="{{ $kind }}[{{ $index }}][gain]" value="{{ $textValue($row['gain'] ?? '') }}" min="1" max="5" step="1" placeholder="1–5" class="field" @error($kind.'.'.$index.'.gain') aria-invalid="true" aria-describedby="{{ $kind }}-{{ $index }}-gain-error" @enderror>
            @error($kind.'.'.$index.'.gain')<p id="{{ $kind }}-{{ $index }}-gain-error" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
    @endif
    <div>
        <label for="{{ $kind }}-{{ $index }}-level" class="mb-2 block text-xs font-semibold text-slate-600">{{ $isDevelopment ? 'Максимум уровня' : 'Минимум уровня' }}</label>
        <input id="{{ $kind }}-{{ $index }}-level" type="number" name="{{ $kind }}[{{ $index }}][{{ $isDevelopment ? 'max_level' : 'min_level' }}]" value="{{ $textValue($row[$isDevelopment ? 'max_level' : 'min_level'] ?? '') }}" min="{{ $isDevelopment ? 1 : 0 }}" max="5" step="1" placeholder="{{ $isDevelopment ? '1–5' : '0–5' }}" class="field" @error($levelField) aria-invalid="true" aria-describedby="{{ $kind }}-{{ $index }}-level-error" @enderror>
        @error($levelField)<p id="{{ $kind }}-{{ $index }}-level-error" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    <button type="button" data-remove-row class="self-start py-3 text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 lg:mt-6" @disabled($locked)>Убрать<span class="sr-only"> навык из списка</span></button>
</div>
