<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operativos · Rastreo K9 SAR</title>
    <style>
        * { box-sizing:border-box; }
        html, body { margin:0; padding:0; min-height:100%; background:#0a0a0a; color:#eee; font-family:-apple-system,Segoe UI,Roboto,sans-serif; }
        .top { background:#1a1a1a; padding:10px 16px; border-bottom:1px solid #2a2a2a; font-size:12px; display:flex; justify-content:space-between; align-items:center; }
        .top a { color:#06b6d4; text-decoration:none; }
        .wrap { max-width:1000px; margin:24px auto; padding:0 16px; }
        h1 { font-size:20px; margin:0 0 16px 0; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .btn {
            padding:8px 16px; background:#06b6d4; color:#000; border:none; border-radius:4px;
            cursor:pointer; font-size:13px; font-weight:600; text-decoration:none; display:inline-block;
        }
        .btn:hover { background:#22d3ee; }
        .card {
            background:#1a1a1a; border:1px solid #2a2a2a; border-left:4px solid #6b7280;
            border-radius:6px; padding:14px 16px; margin-bottom:10px; display:flex; justify-content:space-between; gap:16px;
        }
        .card.active { border-left-color:#f59e0b; }
        .card .info { flex:1; }
        .card .name { font-size:15px; font-weight:600; color:#fff; }
        .card .meta { font-size:11px; color:#888; margin-top:4px; }
        .card .stats { font-size:11px; color:#aaa; margin-top:8px; }
        .card .stats b { color:#eee; }
        .card .actions a { color:#06b6d4; text-decoration:none; font-size:12px; margin-left:12px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .badge-active { background:#f59e0b; color:#000; }
        .badge-closed { background:#374151; color:#9ca3af; }
        .flash { background:#1f3a1f; border-left:3px solid #10b981; padding:10px 14px; margin-bottom:16px; border-radius:4px; color:#a7f3d0; font-size:13px; }
        .empty { background:#1a1a1a; border:1px dashed #2a2a2a; padding:40px; text-align:center; border-radius:6px; color:#666; }
    </style>
</head>
<body>
    <div class="top">
        <a href="{{ route('map') }}">&larr; Volver al mapa</a>
        <span style="color:#888;">{{ auth()->user()->name }} · {{ auth()->user()->role }}</span>
    </div>
    <div class="wrap">
        <h1>Operativos de búsqueda</h1>

        @if (session('flash'))
            <div class="flash">{{ session('flash') }}</div>
        @endif

        <div class="toolbar">
            <div style="font-size:12px;color:#888;">{{ $sessions->total() }} en total</div>
            @if (auth()->user()->isAdmin())
                <a class="btn" href="{{ route('sessions.create') }}">+ Nuevo operativo</a>
            @endif
        </div>

        @forelse ($sessions as $s)
            <div class="card {{ $s->status === 'active' ? 'active' : '' }}">
                <div class="info">
                    <div class="name">
                        <a href="{{ route('sessions.show', $s) }}" style="color:#fff;text-decoration:none;">{{ $s->name }}</a>
                        @if ($s->status === 'active')
                            <span class="badge badge-active">activo</span>
                        @else
                            <span class="badge badge-closed">cerrado</span>
                        @endif
                    </div>
                    <div class="meta">
                        Inició {{ $s->started_at->format('d M Y H:i') }}
                        @if ($s->ended_at) · cerró {{ $s->ended_at->format('d M Y H:i') }} @endif
                        @if ($s->creator) · por {{ $s->creator->name }} @endif
                    </div>
                    <div class="stats">
                        <b>{{ $s->positions_count }}</b> posiciones ·
                        <b>{{ $s->waypoints_count }}</b> waypoints ·
                        <b>{{ $s->notes_count }}</b> notas
                    </div>
                </div>
                <div class="actions" style="display:flex;align-items:center;">
                    <a href="{{ route('sessions.show', $s) }}">Detalle</a>
                </div>
            </div>
        @empty
            <div class="empty">
                Sin operativos todavía.
                @if (auth()->user()->isAdmin())
                    <br><a href="{{ route('sessions.create') }}" style="color:#06b6d4;">Crea el primero</a>.
                @endif
            </div>
        @endforelse

        <div style="margin-top:16px;">{{ $sessions->links() }}</div>
    </div>
</body>
</html>
