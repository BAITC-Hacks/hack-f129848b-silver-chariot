<div data-row-index="{{ $index }}" class="flex flex-wrap items-start gap-3">
    <div class="min-w-48 flex-1">
        <label for="session-{{ $index }}" class="mb-2 block text-xs font-semibold text-slate-600">Дата сессии</label>
        <input id="session-{{ $index }}" name="upcoming_sessions[{{ $index }}]" type="date" value="{{ $textValue($date) }}" class="field" @error('upcoming_sessions.'.$index) aria-invalid="true" aria-describedby="session-{{ $index }}-error" @enderror>
        @error('upcoming_sessions.'.$index)<p id="session-{{ $index }}-error" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    <button type="button" data-remove-row class="mt-6 py-3 text-sm font-medium text-slate-500 underline underline-offset-4 hover:text-red-700">Убрать<span class="sr-only"> дату сессии</span></button>
</div>
