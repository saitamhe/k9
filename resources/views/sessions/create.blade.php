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
