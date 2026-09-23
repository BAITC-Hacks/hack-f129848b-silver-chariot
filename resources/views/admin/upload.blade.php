@extends('layout')
@section('content')
<h1>Merge данных жюри</h1><p>Загрузите один или оба файла. Существующие записи обновляются по ID.</p>
@if(session('imported'))<pre>{{ json_encode(session('imported'), JSON_PRETTY_PRINT) }}</pre>@endif
<form method="post" enctype="multipart/form-data" action="{{ route('admin.upload.store') }}">@csrf
<label>employees.json <input type="file" name="employees" accept=".json"></label>
<label>activity_history.csv <input type="file" name="activity_history" accept=".csv"></label><button>Импортировать</button></form>
@endsection
