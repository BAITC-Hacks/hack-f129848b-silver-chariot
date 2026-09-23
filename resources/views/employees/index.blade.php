@extends('layout')
@section('content')
<h1>Сотрудники</h1>
<form method="get"><input name="search" value="{{ request('search') }}" placeholder="Имя, роль, грейд"><button>Поиск</button></form>
<ul>@forelse($employees as $employee)<li><a href="{{ route('employees.show', $employee) }}">{{ $employee->full_name }}</a> — {{ $employee->role }} / {{ $employee->grade }}</li>@empty<li>Нет сотрудников</li>@endforelse</ul>
{{ $employees->links() }}
@endsection
