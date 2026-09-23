@extends('layout')

@section('title', 'Вход')

@section('content')
    <section class="panel mx-auto flex w-full max-w-md flex-col gap-6 p-6 lg:p-8" aria-labelledby="login-title">
        <div class="flex flex-col gap-3">
            <p class="eyebrow">Личный кабинет</p>
            <h1 id="login-title" class="page-title">Вход</h1>
            <p class="muted">Войдите в учётную запись, чтобы продолжить работу с карьерным развитием.</p>
        </div>

        <form method="post" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf
            <div class="flex flex-col gap-2">
                <label for="login-email" class="text-sm font-semibold text-slate-700">Электронная почта</label>
                <input id="login-email" class="field" type="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror>
                @error('email')<p id="login-email-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="login-password" class="text-sm font-semibold text-slate-700">Пароль</label>
                <input id="login-password" class="field" type="password" name="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror>
                @error('password')<p id="login-password-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="button-primary">Войти</button>
        </form>

        <p class="border-t border-slate-100 pt-5 text-sm leading-relaxed text-slate-500">Для получения учётной записи или восстановления доступа обратитесь к администратору.</p>
    </section>
@endsection
