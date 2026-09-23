@extends('layout')

@section('title', 'Главная')
@section('eyebrow', 'AI-навигатор развития')
@section('page_title', 'Career Quest')

@section('content')
<section class="relative grid min-h-[calc(100vh-230px)] place-items-center overflow-hidden rounded-[32px] bg-[#0b3529] px-12 py-14 text-white shadow-[0_24px_60px_rgba(8,44,34,0.14)]">
    <div class="pointer-events-none absolute -right-36 -top-36 size-[32rem] rounded-full border-[80px] border-white/5"></div>
    <div class="pointer-events-none absolute -bottom-44 -left-40 size-[34rem] rounded-full border-[72px] border-[#d8ff62]/5"></div>
    <div class="pointer-events-none absolute left-1/2 top-1/2 size-80 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-300/8 blur-3xl"></div>

    <div class="relative mx-auto max-w-3xl text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-[#d8ff62] text-[#082c22] shadow-[0_16px_50px_rgba(216,255,98,0.18)]">
            <svg viewBox="0 0 24 24" class="size-9" fill="none" aria-hidden="true">
                <path d="M6.5 17.5 10 14l2.5 2.5L18 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 15V11h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M20 7.5A9 9 0 1 0 21 12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
        </span>

        <p class="mt-7 text-xs font-semibold uppercase tracking-[0.22em] text-[#d8ff62]">Career Quest</p>
        <h2 class="mt-4 text-5xl font-semibold leading-[1.08] tracking-[-0.05em]">Точный следующий шаг<br>для роста каждого сотрудника</h2>
        <p class="mx-auto mt-6 max-w-2xl text-base leading-7 text-emerald-50/65">Сервис сопоставляет навыки, карьерные цели и историю обучения, чтобы предложить объяснимый маршрут к следующему грейду.</p>

        <div class="mt-9 flex items-center justify-center gap-3">
            <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-2 rounded-2xl bg-[#d8ff62] px-6 py-3.5 text-sm font-semibold text-[#082c22] shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-[#e4ff86]">
                Открыть навигатор
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            @if(session('role', 'employee') === 'hr')
                <a href="{{ route('hr.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/8 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/12">HR-аналитика</a>
            @endif
        </div>
    </div>
</section>
@endsection
