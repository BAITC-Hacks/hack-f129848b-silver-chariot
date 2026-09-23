@extends('layout')
@section('content')
<h1>{{ $employee->full_name }}</h1>
<p>{{ $employee->department }} · {{ $employee->role }} · {{ $employee->grade }}</p>
<p>Следующий грейд: {{ $grade_readiness['grade'] ?? 'Достигнут Lead' }}; покрыто {{ $grade_readiness['covered'] }}/{{ $grade_readiness['total'] }}</p>
<h2>Профиль</h2><pre>{{ json_encode($employee, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
<h2>Разрывы навыков</h2><ul>@foreach($gaps as $gap)<li>{{ $skills[$gap['skill_id']]->name ?? $gap['skill_id'] }}: {{ $gap['current'] }} / {{ $gap['required'] }}; разрыв {{ $gap['gap'] }} {{ $gap['critical'] ? '(критический)' : '' }}</li>@endforeach</ul>
<form method="post" action="{{ route('employees.recommendations', $employee) }}">@csrf<button>Получить рекомендации (движок ещё не подключён)</button></form>
<form method="post" action="{{ route('employees.complete', $employee) }}">@csrf<select name="event_id">@foreach($events as $event)<option value="{{ $event->event_id }}">{{ $event->event_id }} — {{ $event->title }}</option>@endforeach</select><button>Отметить выполненной</button></form>
<h2>История</h2><ul>@foreach($history as $record)<li>{{ $record->date->format('Y-m-d') }} — {{ $record->event->title }} — {{ $record->status }} ({{ $record->completion_pct }}%), оценка {{ $record->score ?? '—' }}</li>@endforeach</ul>
@endsection
