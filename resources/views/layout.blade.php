<!doctype html>
<html lang="ru" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#082c22">

    <title>@yield('title', 'Career Quest') · AI-навигатор развития</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[#f3f6f4] text-slate-900 antialiased">
@php
    $isHr = session('role', 'employee') === 'hr';
@endphp

<div class="flex min-h-screen min-w-[1024px]">
    <aside class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col overflow-hidden bg-[#082c22] text-white shadow-[20px_0_60px_rgba(8,44,34,0.08)]">
        <div class="pointer-events-none absolute -right-24 -top-20 size-64 rounded-full bg-emerald-400/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-24 size-72 rounded-full bg-lime-300/5 blur-3xl"></div>

        <a href="{{ route('employees.index') }}" class="relative flex items-center gap-3 border-b border-white/10 px-6 py-6" aria-label="Career Quest — главная">
            <span class="grid size-10 place-items-center rounded-xl bg-[#d8ff62] text-[#082c22] shadow-[0_8px_30px_rgba(216,255,98,0.16)]">
                <svg viewBox="0 0 24 24" class="size-6" fill="none" aria-hidden="true">
                    <path d="M6.5 17.5 10 14l2.5 2.5L18 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18 15V11h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M20 7.5A9 9 0 1 0 21 12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
            </span>
            <span>
                <span class="block text-lg font-semibold leading-none tracking-tight">Career Quest</span>
                <span class="mt-1.5 block text-[10px] font-semibold uppercase tracking-[0.19em] text-emerald-100/55">Growth navigator</span>
            </span>
        </a>

        <nav class="relative flex flex-1 flex-col gap-1.5 px-3 py-6" aria-label="Основная навигация">
            <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-100/40">Рабочее пространство</p>

            <a href="{{ route('employees.index') }}"
               @class([
                   'group flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition',
                   'bg-white text-[#082c22] shadow-sm' => request()->routeIs('employees.*'),
                   'text-emerald-50/70 hover:bg-white/8 hover:text-white' => ! request()->routeIs('employees.*'),
               ])>
                <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>{{ $isHr ? 'Сотрудники' : 'Моё развитие' }}</span>
                @if(request()->routeIs('employees.*'))
                    <span class="ml-auto size-1.5 rounded-full bg-emerald-600"></span>
                @endif
            </a>

            @if($isHr)
                <a href="{{ route('hr.index') }}"
                   @class([
                       'group flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition',
                       'bg-white text-[#082c22] shadow-sm' => request()->routeIs('hr.*'),
                       'text-emerald-50/70 hover:bg-white/8 hover:text-white' => ! request()->routeIs('hr.*'),
                   ])>
                    <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V9M10 19V5M16 19v-7M22 19V2" stroke-linecap="round"/>
                        <path d="M2 19h20" stroke-linecap="round"/>
                    </svg>
                    <span>HR-аналитика</span>
                    @if(request()->routeIs('hr.*'))
                        <span class="ml-auto size-1.5 rounded-full bg-emerald-600"></span>
                    @endif
                </a>

                <a href="{{ route('admin.upload') }}"
                   @class([
                       'group flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition',
                       'bg-white text-[#082c22] shadow-sm' => request()->routeIs('admin.*'),
                       'text-emerald-50/70 hover:bg-white/8 hover:text-white' => ! request()->routeIs('admin.*'),
                   ])>
                    <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M5 13v6a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-6" stroke-linecap="round"/>
                    </svg>
                    <span>Импорт данных</span>
                    @if(request()->routeIs('admin.*'))
                        <span class="ml-auto size-1.5 rounded-full bg-emerald-600"></span>
                    @endif
                </a>
            @endif
        </nav>

        <div class="relative m-4 rounded-2xl border border-white/10 bg-white/6 p-4">
            <div class="flex items-center gap-2 text-xs font-medium text-emerald-100/70">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-[#d8ff62] opacity-50"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-[#d8ff62]"></span>
                </span>
                Данные актуальны
            </div>
            <p class="mt-2 text-sm font-semibold text-white">Снимок на 1 октября</p>
            <p class="mt-1 text-xs leading-5 text-emerald-100/45">Навыки и события синхронизированы</p>
        </div>
    </aside>

    <div class="ml-64 flex min-h-screen min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-slate-200/75 bg-[#f3f6f4]/90 backdrop-blur-xl">
            <div class="flex h-[76px] items-center justify-between gap-8 px-8 xl:px-10">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.17em] text-emerald-700">@yield('eyebrow', 'Career Quest')</p>
                    <h1 class="mt-1 truncate text-lg font-semibold tracking-tight text-slate-900">@yield('page_title', 'Навигатор развития')</h1>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden items-center gap-2 text-xs text-slate-500 xl:flex">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M12 8v4l2.5 1.5M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Обновлено сегодня
                    </div>

                    <span class="h-8 w-px bg-slate-200" aria-hidden="true"></span>

                    <form method="post" action="{{ route('session.role') }}" class="flex items-center gap-3" data-role-switch-form>
                        @csrf
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Режим просмотра</p>
                            <p class="text-sm font-semibold text-slate-800">{{ $isHr ? 'HR-партнёр' : 'Сотрудник' }}</p>
                        </div>
                        <label class="relative">
                            <span class="sr-only">Переключить роль</span>
                            <select name="role" data-role-switch
                                    class="h-10 cursor-pointer appearance-none rounded-xl border border-slate-200 bg-white py-0 pl-10 pr-9 text-sm font-semibold text-slate-700 shadow-sm outline-none transition hover:border-emerald-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                                <option value="employee" @selected(! $isHr)>Сотрудник</option>
                                <option value="hr" @selected($isHr)>HR</option>
                            </select>
                            <span class="pointer-events-none absolute left-3 top-1/2 grid size-5 -translate-y-1/2 place-items-center rounded-md bg-emerald-50 text-emerald-700">
                                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <svg viewBox="0 0 24 24" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m8 10 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </label>
                        <noscript><button class="rounded-xl bg-emerald-700 px-3 py-2 text-sm font-medium text-white">Применить</button></noscript>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 px-8 py-8 xl:px-10 xl:py-10">
            @if($errors->any())
                <div class="mb-6 flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900 shadow-sm" role="alert">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-700">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 8v4m0 4h.01M10.3 3.6 2.6 17a2 2 0 0 0 1.73 3h15.34a2 2 0 0 0 1.73-3L13.7 3.6a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div>
                        <p class="font-semibold">Проверьте данные формы</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5 text-sm text-rose-700">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="flex items-center justify-between border-t border-slate-200/75 px-8 py-5 text-xs text-slate-400 xl:px-10">
            <p>Career Quest · AI-навигатор профессионального развития</p>
            <p>HackAlem AI · Halyk Bank</p>
        </footer>
    </div>
</div>

<div class="pointer-events-none fixed bottom-6 right-6 z-50 flex w-96 flex-col gap-3" data-toast-region aria-live="polite"></div>
</body>
</html>
