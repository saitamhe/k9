<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $session->name }} · Rastreo K9 SAR</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        * { box-sizing:border-box; }
        html, body { margin:0; padding:0; height:100%; background:#0a0a0a; color:#eee; font-family:-apple-system,Segoe UI,Roboto,sans-serif; }
        #app { display:grid; grid-template-columns:340px 1fr; height:100vh; }
        #sidebar { background:#1a1a1a; padding:14px 16px; overflow-y:auto; border-right:1px solid #2a2a2a; }
        #map { width:100%; height:100%; }
        a { color:#06b6d4; text-decoration:none; }
        .back { font-size:12px; }
        h1 { font-size:16px; margin:10px 0 4px 0; color:#fff; }
        .meta { font-size:11px; color:#888; line-height:1.6; }
        .desc { font-size:12px; color:#ccc; margin-top:10px; line-height:1.5; background:#262626; padding:8px 10px; border-radius:4px; }
        h2 { font-size:11px; text-transform:uppercase; color:#888; margin:18px 0 6px 0; letter-spacing:1px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .badge-active { background:#f59e0b; color:#000; }
        .badge-closed { background:#374151; color:#9ca3af; }
        .track-card, .wp-card, .note-card {
            background:#262626; padding:8px 10px; border-radius:4px; margin-bottom:6px; font-size:12px;
        }
        .track-card { border-left:4px solid #ef4444; }
        .wp-card { border-left:4px solid #8b5cf6; }
        .note-card { border-left:4px solid #06b6d4; }
        .small { font-size:10px; color:#888; margin-top:3px; }
        .btn {
            display:inline-block; padding:6px 10px; background:#333; color:#eee; border:1px solid #444;
            border-radius:3px; font-size:11px; cursor:pointer; text-decoration:none; margin-right:4px;
        }
        .btn:hover { background:#3d3d3d; }
        .btn-primary { background:#06b6d4; color:#000; border-color:#06b6d4; }
        .btn-danger { background:#7f1d1d; color:#fff; border-color:#991b1b; }
        .flash { background:#1f3a1f; border-left:3px solid #10b981; padding:8px 10px; border-radius:3px; font-size:11px; color:#a7f3d0; margin-bottom:12px; }
        textarea { width:100%; background:#1a1a1a; color:#eee; border:1px solid #333; border-radius:3px; padding:6px; font-size:12px; font-family:inherit; resize:vertical; min-height:60px; }
    </style>
</head>
<body>
    <div id="app">
        <aside id="sidebar">
            <a class="back" href="{{ route('sessions.index') }}">&larr; Histórico</a>

            <h1>
                {{ $session->name }}
                @if ($session->isActive())
                    <span class="badge badge-active">activo</span>
                @else
                    <span class="badge badge-closed">cerrado</span>
                @endif
            </h1>
            <div class="meta">
                Inició {{ $session->started_at->format('d M Y H:i') }}<br>
                @if ($session->ended_at)
                    Cerró {{ $session->ended_at->format('d M Y H:i') }} · Duración {{ $session->started_at->diffForHumans($session->ended_at, ['parts' => 2, 'short' => true]) }}<br>
                @endif
                @if ($session->creator) Por {{ $session->creator->name }} @endif
            </div>

            @if ($session->description)
                <div class="desc">{{ $session->description }}</div>
            @endif

            @if (session('flash'))
                <div class="flash" style="margin-top:12px;">{{ session('flash') }}</div>
            @endif

            <div style="margin-top:12px;">
                <a class="btn btn-primary" href="{{ route('sessions.gpx', $session) }}">Exportar GPX</a>
                @if ($session->isActive() && auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('sessions.close', $session) }}" style="display:inline;margin-left:4px;" onsubmit="return confirm('¿Cerrar operativo?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">Cerrar operativo</button>
                    </form>
                @endif
            </div>

            <h2>Tracks ({{ $tracks->count() }} perros)</h2>
            @forelse ($tracks as $t)
                <div class="track-card" style="border-left-color: {{ $t['dog']->color ?? '#ef4444' }};">
                    <b>{{ $t['dog']->name }}</b> (nodo {{ $t['dog']->node_id }})
                    <div class="small">{{ $t['points']->count() }} puntos</div>
                </div>
            @empty
                <em style="color:#666;font-size:12px;">Sin telemetría aún.</em>
            @endforelse

            <h2>Waypoints ({{ $waypoints->count() }})</h2>
            @forelse ($waypoints as $wp)
                <div class="wp-card">
                    <b>{{ $wp->type }}</b>
                    @if ($wp->note) — {{ $wp->note }} @endif
                    <div class="small">{{ $wp->recorded_at->format('d M H:i') }} · {{ number_format($wp->lat, 5) }}, {{ number_format($wp->lon, 5) }}</div>
                </div>
            @empty
                <em style="color:#666;font-size:12px;">Sin waypoints.</em>
            @endforelse

            <h2>Notas ({{ $session->notes->count() }})</h2>
            @forelse ($session->notes as $n)
                <div class="note-card">
                    {{ $n->body }}
                    <div class="small">{{ $n->author?->name ?? 'sistema' }} · {{ $n->created_at->format('d M H:i') }}</div>
                </div>
            @empty
                <em style="color:#666;font-size:12px;">Sin notas.</em>
            @endforelse

            @if ($session->isActive() && auth()->user()->isAdmin())
                <form method="POST" action="{{ route('sessions.notes.add', $session) }}" style="margin-top:10px;">
                    @csrf
                    <textarea name="body" required placeholder="Nueva nota..."></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top:4px;">Añadir nota</button>
                </form>
            @endif
        </aside>

        <div id="map"></div>
    </div>

<script>
const SESSION = {
    base: { lat: {{ $session->base_lat ?? -33.45 }}, lon: {{ $session->base_lon ?? -70.65 }}, name: @json($session->base_name ?? 'Base') },
    tracks: @json($tracks),
    waypoints: @json($waypoints),
    notes: @json($session->notes->map(fn($n) => ['body'=>$n->body,'lat'=>$n->lat,'lon'=>$n->lon])->values()),
};

const map = L.map('map').setView([SESSION.base.lat, SESSION.base.lon], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap', maxZoom: 19,
}).addTo(map);

// Base
if (SESSION.base.lat && SESSION.base.lon) {
    L.marker([SESSION.base.lat, SESSION.base.lon])
        .addTo(map).bindTooltip(SESSION.base.name + ' (base)');
}

// Tracks por perro
const allBounds = [];
SESSION.tracks.forEach(t => {
    const color = t.dog.color || '#ef4444';
    const latlngs = t.points.map(p => [p.lat, p.lon]);
    if (latlngs.length === 0) return;
    L.polyline(latlngs, { color, weight: 3, opacity: 0.85 }).addTo(map)
        .bindTooltip(t.dog.name + ' (' + latlngs.length + ' pts)');
    // Marcador inicio + fin
    L.circleMarker(latlngs[0], { radius:4, color, fillColor:'#fff', fillOpacity:1 }).addTo(map).bindTooltip('Inicio ' + t.dog.name);
    L.circleMarker(latlngs[latlngs.length-1], { radius:5, color, fillColor:color, fillOpacity:1 }).addTo(map).bindTooltip('Último ' + t.dog.name);
    latlngs.forEach(p => allBounds.push(p));
});

// Waypoints
SESSION.waypoints.forEach(wp => {
    L.marker([wp.lat, wp.lon], {
        icon: L.divIcon({
            html: '<div style="background:#8b5cf6;color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:700;">' + wp.type + '</div>',
            className: '', iconSize: [60, 16], iconAnchor: [30, 16],
        })
    }).addTo(map).bindPopup('<b>' + wp.type + '</b><br>' + (wp.note || '') + '<br><small>' + new Date(wp.recorded_at).toLocaleString() + '</small>');
    allBounds.push([wp.lat, wp.lon]);
});

// Notas con pin
SESSION.notes.filter(n => n.lat && n.lon).forEach(n => {
    L.marker([n.lat, n.lon], {
        icon: L.divIcon({
            html: '<div style="background:#06b6d4;color:#000;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:700;">NOTA</div>',
            className: '', iconSize: [50, 16], iconAnchor: [25, 16],
        })
    }).addTo(map).bindPopup(n.body);
    allBounds.push([n.lat, n.lon]);
});

if (allBounds.length > 0) {
    map.fitBounds(allBounds, { padding: [40, 40], maxZoom: 17 });
}
</script>
</body>
</html>
