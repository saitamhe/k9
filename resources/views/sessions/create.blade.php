<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo operativo · Rastreo K9 SAR</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; min-height:100%; background:#0a0a0a; color:#eee; font-family:-apple-system,Segoe UI,Roboto,sans-serif; }
        .top { background:#1a1a1a; padding:10px 16px; border-bottom:1px solid #2a2a2a; font-size:12px; }
        .top a { color:#06b6d4; text-decoration:none; }
        .wrap { max-width:560px; margin:30px auto; padding:24px; background:#1a1a1a; border:1px solid #2a2a2a; border-radius:8px; }
        h1 { margin:0 0 20px 0; font-size:18px; }
        label { display:block; font-size:11px; text-transform:uppercase; color:#888; margin:14px 0 4px 0; letter-spacing:1px; }
        input, textarea {
            width:100%; padding:10px 12px; background:#262626; color:#eee;
            border:1px solid #333; border-radius:4px; font-size:14px; font-family:inherit;
        }
        textarea { resize:vertical; min-height:80px; }
        input:focus, textarea:focus { outline:none; border-color:#06b6d4; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .actions { margin-top:24px; display:flex; gap:10px; justify-content:flex-end; }
        .btn {
            padding:10px 18px; border-radius:4px; border:1px solid #333; background:#262626;
            color:#eee; cursor:pointer; font-size:13px; text-decoration:none; display:inline-block;
        }
        .btn-primary { background:#06b6d4; color:#000; border-color:#06b6d4; font-weight:600; }
        .error { background:#3f1f1f; border-left:3px solid #ef4444; padding:8px 12px; margin-bottom:16px; font-size:12px; border-radius:3px; color:#fca5a5; }
        .hint { font-size:11px; color:#888; margin-top:4px; }
    </style>
</head>
<body>
    <div class="top"><a href="{{ route('map') }}">&larr; Volver al mapa</a></div>
    <div class="wrap">
        <h1>Iniciar operativo nuevo</h1>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('sessions.store') }}">
            @csrf

            <label for="name">Nombre *</label>
            <input id="name" type="text" name="name" required maxlength="160"
                   value="{{ old('name', 'Operativo ' . now()->format('d M Y H:i')) }}">
            <div class="hint">Ej: "Búsqueda Lago Llanquihue 21-05"</div>

            <label for="description">Descripción</label>
            <textarea id="description" name="description" placeholder="Persona buscada, sector, condiciones...">{{ old('description') }}</textarea>

            <label for="base_name">Base de operaciones</label>
            <input id="base_name" type="text" name="base_name" maxlength="160" value="{{ old('base_name') }}">

            <div class="row">
                <div>
                    <label for="base_lat">Lat</label>
                    <input id="base_lat" type="number" step="any" name="base_lat" value="{{ old('base_lat') }}">
                </div>
                <div>
                    <label for="base_lon">Lon</label>
                    <input id="base_lon" type="number" step="any" name="base_lon" value="{{ old('base_lon') }}">
                </div>
            </div>

            <div class="actions">
                <a class="btn" href="{{ route('map') }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Iniciar operativo</button>
            </div>
        </form>
    </div>
</body>
</html>
