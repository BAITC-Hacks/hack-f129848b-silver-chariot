<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#065f46">
    <title>@yield('title', 'Развитие сотрудников') · Career Quest</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-slate-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3">Перейти к содержимому</a>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-6 py-5 lg:px-8">
            <a href="{{ route('employees.index') }}" class="flex items-center gap-3" aria-label="Career Quest — главная">
                <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-white" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="1.8"><path d="M5 17h4v-5h5V7h5M14 7h5v5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="flex flex-col"><span class="text-lg font-bold tracking-tight">Career Quest<span class="text-emerald-700">.</span></span><span class="text-[11px] tracking-wide text-slate-500">Навигатор карьерного развития</span></span>
            </a>

            <nav class="flex flex-wrap items-center gap-2 text-sm font-medium" aria-label="Основная навигация">
                <a href="{{ route('employees.index') }}" @if(request()->routeIs('employees.*')) aria-current="page" @endif @class(['nav-link', 'nav-link-active' => request()->routeIs('employees.*')])>{{ session('role', 'employee') === 'hr' ? 'Сотрудники' : 'Моё развитие' }}</a>
                @if(session('role', 'employee') === 'hr')
                    <a href="{{ route('hr.index') }}" @if(request()->routeIs('hr.index')) aria-current="page" @endif @class(['nav-link', 'nav-link-active' => request()->routeIs('hr.index')])>HR-аналитика</a>
                    <a href="{{ route('hr.events.index') }}" @if(request()->routeIs('hr.events.*')) aria-current="page" @endif @class(['nav-link', 'nav-link-active' => request()->routeIs('hr.events.*')])>Активности</a>
                    <a href="{{ route('admin.upload') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.*')])>Импорт данных</a>
                @endif
            </nav>

            <form method="post" action="{{ route('session.role') }}" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-1.5">
                @csrf
                <label for="session-role" class="sr-only">Роль в демо</label>
                <select id="session-role" name="role" class="rounded-lg border-0 bg-transparent py-2 pl-2 pr-6 text-sm font-medium focus:outline-2 focus:outline-emerald-700">
                    <option value="employee" @selected(session('role', 'employee') === 'employee')>Сотрудник</option>
                    <option value="hr" @selected(session('role') === 'hr')>HR</option>
                </select>
                <button type="submit" class="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-emerald-50 hover:text-emerald-800">Переключить роль</button>
            </form>
        </div>
    </header>

    <main id="main-content" class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-10 lg:px-8">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">
                <p class="font-semibold">Не удалось выполнить действие. Проверьте данные:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(session('status'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-6 py-8 text-xs text-slate-500 lg:px-8">
        <p>Career Quest · От навыков к следующему шагу</p>
        <p>Демо HackAlem · {{ session('role', 'employee') === 'hr' ? 'Режим HR' : 'Личный кабинет сотрудника' }}</p>
    </footer>
    @stack('scripts')
</body>
</html>
