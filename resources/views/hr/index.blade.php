@extends('layout')
@section('content')
<h1>HR-аналитика</h1><h2>Проседающие навыки</h2><ul>@foreach($skill_gaps as $gap)<li>{{ $gap['name'] }}: {{ $gap['gap'] }}</li>@endforeach</ul>
<h2>Без рекомендованного шага</h2><p>Временный расчёт по жёстким фильтрам, до интеграции движка.</p><ul>@forelse($employees_without_next_step as $employee)<li><a href="{{ route('employees.show', $employee) }}">{{ $employee->full_name }}</a></li>@empty<li>Нет</li>@endforelse</ul>
<h2>Участие</h2><ul>@foreach($participation as $event)<li>{{ $event['title'] }} — всего {{ $event['total'] }}<ul>@foreach($event['statuses'] as $status => $count)<li>{{ $status }}: {{ $count }}</li>@endforeach</ul></li>@endforeach</ul>
@endsection
