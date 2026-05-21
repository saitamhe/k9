@extends('layouts.app', ['hideTopbar' => true])

@section('title', 'Acceso · Rastreo K9 SAR')

@section('layout_styles')
    .login-wrap {
        min-height: 100vh; min-height: 100dvh;
        display: grid; place-items: center; padding: 24px;
        padding-top: max(24px, env(safe-area-inset-top));
        padding-bottom: max(24px, env(safe-area-inset-bottom));
    }
    .login-card {
        background: var(--panel); border: 1px solid var(--border); border-radius: 10px;
        padding: 32px; width: 100%; max-width: 380px;
    }
    .login-card .brand {
        display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
    }
    .login-card .brand .logo { width: 32px; height: 32px; }
    .login-card h1 {
        margin: 0; font-size: 18px; letter-spacing: 0.5px;
        color: var(--accent);
    }
    .login-card .sub { margin: 0 0 24px 0; font-size: 12px; color: var(--text-muted); }
    .login-card label {
        display: block; font-size: 11px; text-transform: uppercase;
        color: var(--text-muted); margin: 14px 0 4px 0; letter-spacing: 1px;
    }
    .login-card input[type="email"], .login-card input[type="password"] {
        width: 100%; padding: 12px; background: #262626; color: var(--text);
        border: 1px solid #333; border-radius: 4px; font-size: 15px;
    }
    .login-card input:focus { outline: none; border-color: var(--accent); }
    .login-card .row { display: flex; align-items: center; margin-top: 16px; font-size: 13px; color: #aaa; }
    .login-card .row input { margin-right: 8px; }
    .btn-submit {
        width: 100%; margin-top: 22px; padding: 13px; background: var(--accent);
        color: var(--accent-fg); border: none; border-radius: 4px;
        font-weight: 600; font-size: 15px; cursor: pointer; font-family: inherit;
    }
    .btn-submit:hover { background: #22d3ee; }
    .error {
        background: #3f1f1f; border-left: 3px solid #ef4444; padding: 10px 14px;
        margin-bottom: 16px; font-size: 13px; border-radius: 3px; color: #fca5a5;
    }
@endsection

@section('content')
<div class="login-wrap">
    <form class="login-card" method="POST" action="{{ route('login.attempt') }}">
        @csrf

        <div class="brand">
            <svg class="logo" viewBox="0 0 512 512" aria-hidden="true">
                <rect width="512" height="512" rx="96" fill="#0f172a"/>
                <circle cx="256" cy="256" r="180" fill="none" stroke="#2563eb" stroke-width="14" opacity="0.6"/>
                <circle cx="256" cy="256" r="110" fill="none" stroke="#2563eb" stroke-width="14" opacity="0.85"/>
                <circle cx="256" cy="256" r="40" fill="#ef4444"/>
            </svg>
            <h1>Rastreo K9 SAR</h1>
        </div>
        <p class="sub">Iniciar sesión para continuar</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}"
               required autofocus autocomplete="username" inputmode="email">

        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <div class="row">
            <input id="remember" type="checkbox" name="remember" value="1">
            <label for="remember" style="margin:0;text-transform:none;letter-spacing:0;color:#aaa;font-size:13px;">Recordarme</label>
        </div>

        <button type="submit" class="btn-submit">Entrar</button>
    </form>
</div>
@endsection
