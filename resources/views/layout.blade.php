<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Career Quest</title></head>
<body>
<nav><a href="{{ route('employees.index') }}">Сотрудники</a>
@if(session('role', 'employee') === 'hr') <a href="{{ route('hr.index') }}">HR</a> <a href="{{ route('admin.upload') }}">Загрузка</a> @endif
<form method="post" action="{{ route('session.role') }}">@csrf
<select name="role"><option value="employee" @selected(session('role', 'employee') === 'employee')>Сотрудник</option><option value="hr" @selected(session('role') === 'hr')>HR</option></select><button>Переключить роль</button></form></nav>
@if($errors->any()) <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul> @endif
@yield('content')
</body></html>
