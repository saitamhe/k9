@extends('layouts.app')

@section('title', 'Operativos · Rastreo K9 SAR')

@section('layout_styles')
    .wrap { max-width: 1000px; margin: 24px auto; padding: 0 16px; }
    h1 { font-size: 20px; margin: 0 0 16px 0; }
    .toolbar {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 16px; gap: 12px; flex-wrap: wrap;
    }
    .btn {
        padding: 10px 16px; background: var(--accent); color: var(--accent-fg);
        border: none; border-radius: 4px; cursor: pointer; font-size: 13px;
        font-weight: 600; text-decoration: none; display: inline-block;
    }
    .btn:hover { background: #22d3ee; }
    .card {
        background: var(--panel); border: 1px solid var(--border);
        border-left: 4px solid #6b7280; border-radius: 6px;
        padding: 14px 16px; margin-bottom: 10px;
        display: flex; justify-content: space-between; gap: 16px;
    }
    .card.active { border-left-color: #f59e0b; }
    .card .info { flex: 1; min-width: 0; }
    .card .name { font-size: 15px; font-weight: 600; color: #fff; word-break: break-word; }
    .card .meta { font-size: 11px; color: #888; margin-top: 4px; line-height: 1.5; }
    .card .stats { font-size: 11px; color: #aaa; margin-top: 8px; }
    .card .stats b { color: #eee; }
    .card .actions {
        display: flex; align-items: center; gap: 6px; flex-shrink: 0;
    }
    .card .actions a {
        color: var(--accent); text-decoration: none; font-size: 12px;
        padding: 6px 10px; border: 1px solid #2a2a2a; border-radius: 4px;
        white-space: nowrap;
    }
    .card .actions a:hover { background: #222; }
    .badge {
        display: inline-block; padding: 2px 8px; border-radius: 3px;
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        margin-left: 6px;
    }
    .badge-active { background: #f59e0b; color: #000; }
    .badge-closed { background: #374151; color: #9ca3af; }
    .empty {
        background: var(--panel); border: 1px dashed var(--border);
        padding: 40px 20px; text-align: center; border-radius: 6px; color: #666;
    }

    @media (max-width: 600px) {
        .wrap { margin: 16px auto; }
        h1 { font-size: 18px; }
        .card { flex-direction: column; gap: 10px; padding: 12px 14px; }
        .card .actions { width: 100%; }
        .card .actions a { flex: 1; text-align: center; }
    }
@endsection

@section('content')
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
            <div class="actions">
                <a href="{{ route('sessions.show', $s) }}">Detalle</a>
                <a href="{{ route('sessions.gpx', $s) }}">GPX</a>
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
@endsection
