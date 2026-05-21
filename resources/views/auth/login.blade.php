<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso · Rastreo K9 SAR</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #0a0a0a; color: #eee; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 32px; width: 100%; max-width: 380px; }
        h1 { margin: 0 0 4px 0; font-size: 18px; letter-spacing: 0.5px; }
        .sub { margin: 0 0 24px 0; font-size: 12px; color: #888; }
        label { display: block; font-size: 11px; text-transform: uppercase; color: #888; margin: 14px 0 4px 0; letter-spacing: 1px; }
        input[type="email"], input[type="password"] {
            width: 100%; padding: 10px 12px; background: #262626; color: #eee;
            border: 1px solid #333; border-radius: 4px; font-size: 14px;
        }
        input:focus { outline: none; border-color: #06b6d4; }
        .row { display: flex; align-items: center; margin-top: 16px; font-size: 12px; color: #aaa; }
        .row input { margin-right: 6px; }
        .btn-submit {
            width: 100%; margin-top: 20px; padding: 11px; background: #06b6d4; color: #000;
            border: none; border-radius: 4px; font-weight: 600; font-size: 14px; cursor: pointer;
        }
        .btn-submit:hover { background: #22d3ee; }
        .error { background: #3f1f1f; border-left: 3px solid #ef4444; padding: 8px 12px; margin-bottom: 16px; font-size: 12px; border-radius: 3px; color: #fca5a5; }
    </style>
</head>
<body>
    <div class="wrap">
        <form class="card" method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <h1>Rastreo K9 SAR</h1>
            <p class="sub">Iniciar sesión para continuar</p>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <div class="row">
                <input id="remember" type="checkbox" name="remember" value="1">
                <label for="remember" style="margin: 0; text-transform: none; letter-spacing: 0; color: #aaa; font-size: 12px;">Recordarme</label>
            </div>

            <button type="submit" class="btn-submit">Entrar</button>
        </form>
    </div>
</body>
</html>
