@extends('layouts.app')

@section('title', 'Nuevo operativo · Rastreo K9 SAR')

@section('layout_styles')
    .wrap {
        max-width: 560px; margin: 30px auto; padding: 24px;
        background: var(--panel); border: 1px solid var(--border); border-radius: 8px;
    }
    h1 { margin: 0 0 20px 0; font-size: 18px; }
    label {
        display: block; font-size: 11px; text-transform: uppercase;
        color: var(--text-muted); margin: 14px 0 4px 0; letter-spacing: 1px;
    }
    input, textarea {
        width: 100%; padding: 12px; background: #262626; color: var(--text);
        border: 1px solid #333; border-radius: 4px; font-size: 15px; font-family: inherit;
    }
    textarea { resize: vertical; min-height: 90px; }
    input:focus, textarea:focus { outline: none; border-color: var(--accent); }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .actions { margin-top: 24px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .btn {
        padding: 12px 20px; border-radius: 4px; border: 1px solid #333;
        background: #262626; color: var(--text); cursor: pointer; font-size: 14px;
        text-decoration: none; display: inline-block; font-family: inherit;
    }
    .btn-primary { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); font-weight: 600; }
    .error {
        background: #3f1f1f; border-left: 3px solid #ef4444; padding: 10px 14px;
        margin-bottom: 16px; font-size: 13px; border-radius: 3px; color: #fca5a5;
    }
    .hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
    .loc-actions {
        display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;
    }
    .btn-loc {
        flex: 1; min-width: 0; padding: 10px 12px;
        background: #1f2937; border: 1px solid #374151;
        color: var(--text); font-size: 13px; cursor: pointer;
        border-radius: 4px; font-family: inherit; text-align: center;
    }
    .btn-loc:hover { background: #273548; }
    .btn-loc[disabled] { opacity: 0.5; cursor: wait; }

    @media (max-width: 600px) {
        .wrap { margin: 16px 12px; padding: 16px; }
        .row { grid-template-columns: 1fr; }
        .actions { flex-direction: column-reverse; }
        .actions .btn { width: 100%; text-align: center; }
    }
@endsection

@section('content')
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

        <div class="loc-actions">
            <button type="button" class="btn btn-loc" id="btn-geo">📍 Usar mi ubicación actual</button>
            <button type="button" class="btn btn-loc" id="btn-from-map">🗺 Usar la base del mapa</button>
        </div>
        <div id="geo-msg" class="hint" style="margin-top:6px;"></div>

        <div class="row">
            <div>
                <label for="base_lat">Lat</label>
                <input id="base_lat" type="number" step="any" inputmode="decimal" name="base_lat" value="{{ old('base_lat') }}">
            </div>
            <div>
                <label for="base_lon">Lon</label>
                <input id="base_lon" type="number" step="any" inputmode="decimal" name="base_lon" value="{{ old('base_lon') }}">
            </div>
        </div>

        <div class="actions">
            <a class="btn" href="{{ route('sessions.index') }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">Iniciar operativo</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const $lat  = document.getElementById('base_lat');
    const $lon  = document.getElementById('base_lon');
    const $name = document.getElementById('base_name');
    const $msg  = document.getElementById('geo-msg');
    const $geo  = document.getElementById('btn-geo');
    const $map  = document.getElementById('btn-from-map');

    function setMsg(text, color) {
        $msg.style.color = color || 'var(--text-muted)';
        $msg.textContent = text || '';
    }

    $geo.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setMsg('Tu navegador no soporta geolocalización.', '#fca5a5');
            return;
        }
        $geo.disabled = true;
        setMsg('Obteniendo ubicación del GPS...');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                $lat.value = pos.coords.latitude.toFixed(6);
                $lon.value = pos.coords.longitude.toFixed(6);
                if (!$name.value) $name.value = 'Base (mi ubicación)';
                setMsg('Ubicación fijada: ±' + Math.round(pos.coords.accuracy) + ' m de precisión.', '#a7f3d0');
                $geo.disabled = false;
            },
            (err) => {
                setMsg('No se pudo obtener: ' + err.message, '#fca5a5');
                $geo.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
        );
    });

    $map.addEventListener('click', () => {
        try {
            const saved = JSON.parse(localStorage.getItem('rastreo.base'));
            if (!saved || saved.lat == null) {
                setMsg('No hay una base guardada en el mapa todavía.', '#fca5a5');
                return;
            }
            $lat.value = (+saved.lat).toFixed(6);
            $lon.value = (+saved.lon).toFixed(6);
            if (!$name.value && saved.name) $name.value = saved.name;
            setMsg('Tomada de la base actual del mapa.', '#a7f3d0');
        } catch (e) {
            setMsg('No se pudo leer la base del mapa.', '#fca5a5');
        }
    });
})();
</script>
@endsection
