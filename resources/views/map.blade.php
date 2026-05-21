@extends('layouts.app', ['bodyClass' => 'fixed-viewport'])

@section('title', 'Mapa · Rastreo K9 SAR')

@section('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endsection

@section('layout_styles')
    #map-app {
        display: grid; grid-template-columns: 320px 1fr;
        height: calc(100vh - var(--topbar-h));
        height: calc(100dvh - var(--topbar-h));
    }
    #sidebar {
        background: var(--panel); color: var(--text); padding: 16px;
        overflow-y: auto; border-right: 1px solid var(--border);
    }
    #sidebar h2 {
        font-size: 11px; text-transform: uppercase; color: var(--text-muted);
        margin: 16px 0 8px 0; letter-spacing: 1px;
    }
    #sidebar h2:first-child { margin-top: 0; }
    #map-pane { position: relative; min-width: 0; }
    #map { width: 100%; height: 100%; cursor: crosshair; }
    #map.normal-cursor { cursor: grab; }

    .dog-card {
        background: var(--panel-2); border-left: 4px solid #ef4444; padding: 10px 12px;
        margin-bottom: 8px; border-radius: 4px; cursor: pointer; transition: background 0.15s;
    }
    .dog-card:hover { background: #2f2f2f; }
    .dog-card.no-fix { opacity: 0.55; }
    .dog-card .name { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
    .dog-card .meta { font-size: 11px; color: #aaa; line-height: 1.55; }
    .dog-card .meta b { color: #ddd; }

    .badge {
        display: inline-block; padding: 1px 6px; font-size: 10px; border-radius: 3px;
        margin-right: 4px;
    }
    .badge-fix    { background: #10b981; color: #000; }
    .badge-no-fix { background: #f59e0b; color: #000; }
    .badge-mov    { background: #8b5cf6; color: #fff; }
    .badge-stale  { background: #6b7280; color: #fff; }

    .base-card {
        background: var(--panel-2); border-left: 4px solid #06b6d4; padding: 10px 12px;
        margin-bottom: 12px; border-radius: 4px; font-size: 12px;
    }
    .base-card .name { font-weight: 600; color: #06b6d4; margin-bottom: 6px; }
    .base-card .coords { font-family: monospace; font-size: 11px; color: #999; }

    .btn {
        display: block; width: 100%; padding: 8px 10px; margin-top: 6px;
        background: #333; color: #eee; border: 1px solid #444; border-radius: 3px;
        font-size: 12px; cursor: pointer; text-align: left; font-family: inherit;
    }
    .btn:hover { background: #3d3d3d; }
    .btn.active { background: #06b6d4; color: #000; border-color: #06b6d4; }

    #status-bar {
        position: absolute; bottom: 10px; right: 10px; z-index: 600;
        background: rgba(0,0,0,0.7); color: #fff; padding: 6px 12px;
        font-size: 11px; border-radius: 4px; font-family: monospace;
        pointer-events: none;
    }
    #mode-banner {
        position: absolute; top: 12px; left: 50%; transform: translateX(-50%); z-index: 600;
        background: #06b6d4; color: #000; padding: 8px 16px; font-size: 12px; font-weight: 600;
        border-radius: 4px; display: none; max-width: 90vw; text-align: center;
    }
    .leaflet-popup-content { font-size: 12px; }

    /* Botón flotante para abrir el sidebar en mobile */
    #btn-toggle-sidebar {
        display: none;
        position: absolute; top: 12px; left: 12px; z-index: 700;
        background: rgba(0,0,0,0.82); color: #fff; border: 1px solid #333;
        border-radius: 4px; padding: 9px 14px; font-size: 13px; cursor: pointer;
        font-family: inherit; backdrop-filter: blur(4px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }
    #btn-toggle-sidebar:hover { background: rgba(0,0,0,0.95); }

    #sidebar-backdrop {
        position: fixed; inset: var(--topbar-h) 0 0 0;
        background: rgba(0,0,0,0.5); z-index: 1100; display: none;
    }
    #sidebar-backdrop.open { display: block; }

    @media (max-width: 820px) {
        #map-app { grid-template-columns: 1fr; }
        #btn-toggle-sidebar { display: inline-flex; align-items: center; gap: 6px; }
        #sidebar {
            position: fixed; top: var(--topbar-h); bottom: 0; left: 0;
            width: 88%; max-width: 380px; z-index: 1200;
            transform: translateX(-110%); transition: transform 0.25s ease;
            box-shadow: 4px 0 16px rgba(0,0,0,0.6);
        }
        #sidebar.open { transform: translateX(0); }
        #status-bar {
            bottom: auto; top: 12px; right: 12px;
            font-size: 10px; padding: 5px 9px;
        }
    }

    @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.4); } }
@endsection

@section('content')
<div id="map-app">
    <aside id="sidebar">
        @if (session('flash'))
            <div class="flash">{{ session('flash') }}</div>
        @endif

        <h2>Operativo actual</h2>
        @if ($activeSession)
            <div style="background:#262626;border-left:4px solid #f59e0b;padding:10px 12px;margin-bottom:12px;border-radius:4px;">
                <div style="font-weight:600;color:#fff;font-size:13px;margin-bottom:4px;">{{ $activeSession->name }}</div>
                <div style="font-size:11px;color:#aaa;">
                    Inició {{ $activeSession->started_at->format('d M H:i') }}
                    @if ($activeSession->creator) · por {{ $activeSession->creator->name }} @endif
                </div>
                @if ($activeSession->description)
                    <div style="font-size:11px;color:#ccc;margin-top:6px;line-height:1.4;">{{ $activeSession->description }}</div>
                @endif

                <div style="margin-top:10px;display:flex;gap:6px;">
                    <a class="btn" style="flex:1;text-align:center;text-decoration:none;" href="{{ route('sessions.show', $activeSession) }}">Ver detalle</a>
                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('sessions.close', $activeSession) }}" style="flex:1;margin:0;" onsubmit="return confirm('¿Cerrar operativo {{ $activeSession->name }}?');">
                            @csrf
                            <button class="btn" style="width:100%;background:#7f1d1d;border-color:#991b1b;color:#fff;">Cerrar</button>
                        </form>
                    @endif
                </div>

                <details style="margin-top:10px;font-size:11px;">
                    <summary style="cursor:pointer;color:#06b6d4;">Notas ({{ $activeSession->notes->count() }})</summary>
                    <div style="max-height:160px;overflow-y:auto;margin-top:6px;">
                        @forelse ($activeSession->notes as $n)
                            <div style="padding:6px 8px;background:#1a1a1a;border-radius:3px;margin-bottom:4px;">
                                <div style="color:#ddd;line-height:1.4;">{{ $n->body }}</div>
                                <div style="color:#666;font-size:10px;margin-top:3px;">
                                    {{ $n->author?->name ?? 'sistema' }} · {{ $n->created_at->format('d M H:i') }}
                                </div>
                            </div>
                        @empty
                            <em style="color:#666;">Sin notas todavía.</em>
                        @endforelse
                    </div>
                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('sessions.notes.add', $activeSession) }}" style="margin-top:6px;">
                            @csrf
                            <textarea name="body" rows="2" required placeholder="Nueva nota..." style="width:100%;background:#1a1a1a;color:#eee;border:1px solid #333;border-radius:3px;padding:6px;font-size:11px;font-family:inherit;resize:vertical;"></textarea>
                            <button type="submit" class="btn" style="margin-top:4px;">Añadir nota</button>
                        </form>
                    @endif
                </details>
            </div>
        @else
            <div style="background:#262626;padding:10px 12px;margin-bottom:12px;border-radius:4px;font-size:12px;color:#888;">
                Sin operativo activo. Las posiciones se siguen guardando pero no quedan asociadas a un operativo.
                @if (auth()->user()->isAdmin())
                    <a class="btn" style="margin-top:8px;text-align:center;text-decoration:none;" href="{{ route('sessions.create') }}">Iniciar operativo nuevo</a>
                @endif
            </div>
        @endif
        <a class="btn" style="text-align:center;text-decoration:none;margin-bottom:14px;" href="{{ route('sessions.index') }}">Histórico de operativos</a>

        <h2>Base de operaciones</h2>
        <div class="base-card">
            <div class="name" id="base-name">{{ $base['name'] }}</div>
            <div class="coords" id="base-coords">{{ number_format($base['lat'], 6) }}, {{ number_format($base['lon'], 6) }}</div>
            <button class="btn" id="btn-use-my-location">📍 Usar mi ubicación (GPS del dispositivo)</button>
            <button class="btn" id="btn-pick-on-map">🎯 Elegir base en el mapa (click)</button>
            <button class="btn" id="btn-reset-base">↺ Volver al default del .env</button>
        </div>

        <h2>Perros activos</h2>
        <div id="dog-list"><em style="color:#666;font-size:12px;">Esperando datos...</em></div>

        <h2>Leyenda</h2>
        <div style="font-size:11px;color:#aaa;line-height:1.7;">
            <span class="badge badge-fix">FIX</span> GPS con fix válido<br>
            <span class="badge badge-no-fix">NO FIX</span> sin posición<br>
            <span class="badge badge-mov">MOV</span> perro en movimiento<br>
            <span class="badge badge-stale">STALE</span> sin datos hace &gt; 30s
        </div>
    </aside>

    <div id="sidebar-backdrop"></div>

    <div id="map-pane">
        <button id="btn-toggle-sidebar" type="button" aria-label="Mostrar panel">📊 Operativo</button>
        <div id="map"></div>
        <div id="status-bar">— sin conexión —</div>
        <div id="mode-banner">Toca el mapa para fijar la base. ESC para cancelar.</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const POLL_MS = 1000;
const TRACK_LIMIT = 500;
const STALE_THRESHOLD_S = 30;

// Base: .env default + override de localStorage si existe
const DEFAULT_BASE = {
    name: @json($base['name']),
    lat: {{ $base['lat'] }},
    lon: {{ $base['lon'] }},
};
let base = loadBase();

const map = L.map('map').setView([base.lat, base.lon], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
}).addTo(map);
document.getElementById('map').classList.add('normal-cursor');

// ----- Toggle sidebar en mobile -----
const sidebarEl  = document.getElementById('sidebar');
const sidebarBd  = document.getElementById('sidebar-backdrop');
const sidebarBtn = document.getElementById('btn-toggle-sidebar');
function openSidebar() {
    sidebarEl.classList.add('open');
    sidebarBd.classList.add('open');
}
function closeSidebar() {
    sidebarEl.classList.remove('open');
    sidebarBd.classList.remove('open');
}
sidebarBtn.addEventListener('click', () => {
    sidebarEl.classList.contains('open') ? closeSidebar() : openSidebar();
});
sidebarBd.addEventListener('click', closeSidebar);

// Recalcular tamaño del mapa cuando la ventana cambia (rotación, etc.)
window.addEventListener('resize', () => map.invalidateSize());

// ----- Base marker -----
function baseIcon() {
    const html = `<div style="
        width: 0; height: 0; border-left: 12px solid transparent; border-right: 12px solid transparent;
        border-bottom: 20px solid #06b6d4;
        filter: drop-shadow(0 0 3px rgba(0,0,0,0.6));
    "></div>`;
    return L.divIcon({ html, className: '', iconSize: [24, 20], iconAnchor: [12, 20] });
}
const baseMarker = L.marker([base.lat, base.lon], { icon: baseIcon(), zIndexOffset: -1000 }).addTo(map);
baseMarker.bindTooltip(base.name, { permanent: false, direction: 'top' });

function refreshBaseUI() {
    document.getElementById('base-coords').textContent = `${base.lat.toFixed(6)}, ${base.lon.toFixed(6)}`;
    baseMarker.setLatLng([base.lat, base.lon]);
    baseMarker.setTooltipContent(base.name);
}

function saveBase()   { localStorage.setItem('rastreo.base', JSON.stringify(base)); }
function loadBase()   { try { return JSON.parse(localStorage.getItem('rastreo.base')) || DEFAULT_BASE; } catch { return DEFAULT_BASE; } }
function resetBase()  { localStorage.removeItem('rastreo.base'); base = { ...DEFAULT_BASE }; refreshBaseUI(); }

document.getElementById('btn-reset-base').addEventListener('click', resetBase);

document.getElementById('btn-use-my-location').addEventListener('click', () => {
    if (!navigator.geolocation) {
        alert('Tu navegador no soporta geolocalización');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            base.lat = pos.coords.latitude;
            base.lon = pos.coords.longitude;
            base.name = base.name || 'Base';
            saveBase();
            refreshBaseUI();
            map.setView([base.lat, base.lon], 16);
        },
        (err) => alert('No se pudo obtener ubicación: ' + err.message),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
});

// Modo "elegir base con click"
let pickingBase = false;
const banner = document.getElementById('mode-banner');
const btnPick = document.getElementById('btn-pick-on-map');
btnPick.addEventListener('click', () => {
    pickingBase = !pickingBase;
    btnPick.classList.toggle('active', pickingBase);
    banner.style.display = pickingBase ? 'block' : 'none';
    document.getElementById('map').classList.toggle('normal-cursor', !pickingBase);
    if (pickingBase) closeSidebar(); // dejar el mapa libre para tocar
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && pickingBase) btnPick.click();
});
map.on('click', (e) => {
    if (!pickingBase) return;
    base.lat = e.latlng.lat;
    base.lon = e.latlng.lng;
    saveBase();
    refreshBaseUI();
    btnPick.click(); // sale del modo
});

// ----- Perros -----
const dogs = {};
let firstCenterDone = false;

function dogIcon(color, isMoving) {
    const html = `<div style="
        width: 16px; height: 16px; border-radius: 50%;
        background: ${color}; border: 3px solid white;
        box-shadow: 0 0 0 1px rgba(0,0,0,0.5);
        ${isMoving ? 'animation: pulse 1s infinite;' : ''}
    "></div>`;
    return L.divIcon({ html, className: '', iconSize: [22, 22], iconAnchor: [11, 11] });
}

function distMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = (d) => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
    return Math.round(2 * R * Math.asin(Math.sqrt(a)));
}

function bearing(lat1, lon1, lat2, lon2) {
    const toRad = (d) => d * Math.PI / 180;
    const toDeg = (r) => r * 180 / Math.PI;
    const dLon = toRad(lon2 - lon1);
    const y = Math.sin(dLon) * Math.cos(toRad(lat2));
    const x = Math.cos(toRad(lat1))*Math.sin(toRad(lat2)) - Math.sin(toRad(lat1))*Math.cos(toRad(lat2))*Math.cos(dLon);
    return (toDeg(Math.atan2(y, x)) + 360) % 360;
}

function bearingToCardinal(b) {
    const dirs = ['N','NE','E','SE','S','SO','O','NO'];
    return dirs[Math.round(b / 45) % 8];
}

async function loadTrack(dog) {
    try {
        const r = await fetch(`/api/dogs/${dog.id}/track?limit=${TRACK_LIMIT}`);
        const data = await r.json();
        const latlngs = data.points.filter(p => p.lat !== 0 || p.lon !== 0).map(p => [p.lat, p.lon]);
        if (dogs[dog.id].polyline) map.removeLayer(dogs[dog.id].polyline);
        dogs[dog.id].polyline = L.polyline(latlngs, { color: dog.color, weight: 3, opacity: 0.7 }).addTo(map);
    } catch (e) { console.warn('track load fail', e); }
}

function updateSidebar(payload) {
    const container = document.getElementById('dog-list');
    if (!payload.dogs.length) {
        container.innerHTML = '<em style="color:#666;font-size:12px;">Sin perros aún. Cuando llegue un paquete por LoRa aparecerá aquí automáticamente.</em>';
        return;
    }
    container.innerHTML = payload.dogs.map(d => {
        const p = d.position;
        if (!p) return `<div class="dog-card no-fix"><div class="name">${d.name}</div><div class="meta">sin posición</div></div>`;

        const ageBadge = p.age_s > STALE_THRESHOLD_S ? '<span class="badge badge-stale">STALE</span>' : '';
        const fixBadge = p.has_fix   ? '<span class="badge badge-fix">FIX</span>' : '<span class="badge badge-no-fix">NO FIX</span>';
        const movBadge = p.is_moving ? '<span class="badge badge-mov">MOV</span>' : '';
        const cardClass = p.has_fix ? 'dog-card' : 'dog-card no-fix';

        let distInfo = '';
        if (p.has_fix) {
            const d_m = distMeters(base.lat, base.lon, p.lat, p.lon);
            const b_deg = bearing(base.lat, base.lon, p.lat, p.lon);
            const distStr = d_m < 1000 ? `${d_m} m` : `${(d_m/1000).toFixed(2)} km`;
            distInfo = `<b>${distStr}</b> a ${b_deg.toFixed(0)}° (${bearingToCardinal(b_deg)}) desde base<br>`;
        }

        return `
            <div class="${cardClass}" style="border-left-color:${d.color};" data-dog-id="${d.id}" data-lat="${p.lat}" data-lon="${p.lon}">
                <div class="name">${d.name}${d.handler ? ' · ' + d.handler : ''}</div>
                <div class="meta">
                    ${fixBadge}${movBadge}${ageBadge}<br>
                    ${distInfo}
                    ${p.speed_mps.toFixed(1)} m/s · hdg ${p.heading_deg}°<br>
                    RSSI ${p.rssi} dBm · SNR ${p.snr.toFixed(1)} dB<br>
                    hace ${p.age_s}s
                </div>
            </div>`;
    }).join('');

    container.querySelectorAll('.dog-card[data-lat]').forEach(el => {
        el.addEventListener('click', () => {
            const lat = parseFloat(el.dataset.lat), lon = parseFloat(el.dataset.lon);
            if (lat !== 0 || lon !== 0) {
                map.setView([lat, lon], 16);
                closeSidebar();
            }
        });
    });
}

async function poll() {
    try {
        const r = await fetch('/api/positions/latest');
        const payload = await r.json();
        document.getElementById('status-bar').textContent =
            `${payload.dogs.length} perros · ${new Date(payload.server_ts).toLocaleTimeString()}`;

        for (const d of payload.dogs) {
            const p = d.position;
            if (!p || !p.has_fix) continue;
            const latlng = [p.lat, p.lon];

            if (!dogs[d.id]) {
                dogs[d.id] = {
                    id: d.id, color: d.color, name: d.name,
                    marker: L.marker(latlng, { icon: dogIcon(d.color, p.is_moving) }).addTo(map),
                };
                dogs[d.id].marker.bindPopup(`<b>${d.name}</b><br>${d.handler || ''}`);
                loadTrack(d);
                if (!firstCenterDone) {
                    map.setView(latlng, 16);
                    firstCenterDone = true;
                }
            } else {
                dogs[d.id].marker.setLatLng(latlng);
                dogs[d.id].marker.setIcon(dogIcon(d.color, p.is_moving));
                if (dogs[d.id].polyline) dogs[d.id].polyline.addLatLng(latlng);
            }
            dogs[d.id].lastReceived = p.received_at;
        }
        updateSidebar(payload);
    } catch (e) {
        document.getElementById('status-bar').textContent = '— sin conexión al backend —';
    }
}

setInterval(poll, POLL_MS);
poll();
</script>
@endsection
