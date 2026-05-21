<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreo K9 SAR</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; font-family: -apple-system, Segoe UI, Roboto, sans-serif; }
        #app { display: grid; grid-template-columns: 300px 1fr; height: 100vh; }
        #sidebar {
            background: #1a1a1a; color: #eee; padding: 16px; overflow-y: auto;
            border-right: 1px solid #333;
        }
        #sidebar h1 { font-size: 16px; margin: 0 0 16px 0; color: #fff; letter-spacing: 0.5px; }
        #sidebar h2 { font-size: 11px; text-transform: uppercase; color: #888; margin: 16px 0 8px 0; letter-spacing: 1px; }
        #map { width: 100%; height: 100%; cursor: crosshair; }
        #map.normal-cursor { cursor: grab; }
        .dog-card {
            background: #262626; border-left: 4px solid #ef4444; padding: 10px 12px;
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
            background: #262626; border-left: 4px solid #06b6d4; padding: 10px 12px;
            margin-bottom: 12px; border-radius: 4px; font-size: 12px;
        }
        .base-card .name { font-weight: 600; color: #06b6d4; margin-bottom: 6px; }
        .base-card .coords { font-family: monospace; font-size: 11px; color: #999; }
        .btn {
            display: block; width: 100%; padding: 6px 8px; margin-top: 6px;
            background: #333; color: #eee; border: 1px solid #444; border-radius: 3px;
            font-size: 11px; cursor: pointer; text-align: left;
        }
        .btn:hover { background: #3d3d3d; }
        .btn.active { background: #06b6d4; color: #000; border-color: #06b6d4; }
        #status-bar {
            position: absolute; bottom: 8px; right: 8px; z-index: 1000;
            background: rgba(0,0,0,0.7); color: #fff; padding: 4px 10px;
            font-size: 11px; border-radius: 3px; font-family: monospace;
        }
        #mode-banner {
            position: absolute; top: 8px; left: 50%; transform: translateX(-50%); z-index: 1000;
            background: #06b6d4; color: #000; padding: 6px 14px; font-size: 12px; font-weight: 600;
            border-radius: 3px; display: none;
        }
        .leaflet-popup-content { font-size: 12px; }
    </style>
</head>
<body>
    <div id="app">
        <aside id="sidebar">
            <h1>RASTREO K9 SAR</h1>

            @auth
                <div style="background:#262626;border-left:4px solid #06b6d4;padding:8px 10px;border-radius:4px;margin-bottom:12px;font-size:11px;display:flex;justify-content:space-between;align-items:center;">
                    <span>
                        <b style="color:#fff;">{{ auth()->user()->name }}</b>
                        <span style="color:#888;">· {{ auth()->user()->role }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" style="background:none;border:none;color:#06b6d4;cursor:pointer;font-size:11px;padding:0;">salir</button>
                    </form>
                </div>
            @endauth

            <h2>Base de operaciones</h2>
            <div class="base-card">
                <div class="name" id="base-name">{{ $base['name'] }}</div>
                <div class="coords" id="base-coords">{{ number_format($base['lat'], 6) }}, {{ number_format($base['lon'], 6) }}</div>
                <button class="btn" id="btn-use-my-location">📍 Usar mi ubicacion (GPS del laptop)</button>
                <button class="btn" id="btn-pick-on-map">🎯 Elegir base en el mapa (click)</button>
                <button class="btn" id="btn-reset-base">↺ Volver al default del .env</button>
            </div>

            <h2>Perros activos</h2>
            <div id="dog-list"><em style="color:#666;font-size:12px;">Esperando datos...</em></div>

            <h2>Leyenda</h2>
            <div style="font-size:11px;color:#aaa;line-height:1.7;">
                <span class="badge badge-fix">FIX</span> GPS con fix valido<br>
                <span class="badge badge-no-fix">NO FIX</span> sin posicion<br>
                <span class="badge badge-mov">MOV</span> perro en movimiento<br>
                <span class="badge badge-stale">STALE</span> sin datos hace &gt; 30s
            </div>
        </aside>

        <div style="position: relative;">
            <div id="map"></div>
            <div id="status-bar">— sin conexion —</div>
            <div id="mode-banner">Hace click en el mapa para fijar la base. ESC para cancelar.</div>
        </div>
    </div>

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
        alert('Tu navegador no soporta geolocalizacion');
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
        (err) => alert('No se pudo obtener ubicacion: ' + err.message),
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

// Haversine: distancia en metros entre 2 puntos lat/lon
function distMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = (d) => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
    return Math.round(2 * R * Math.asin(Math.sqrt(a)));
}

// Rumbo (bearing) de p1 a p2, en grados [0..360)
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
        container.innerHTML = '<em style="color:#666;font-size:12px;">Sin perros aun. Cuando llegue un paquete por LoRa aparecera aqui automaticamente.</em>';
        return;
    }
    container.innerHTML = payload.dogs.map(d => {
        const p = d.position;
        if (!p) return `<div class="dog-card no-fix"><div class="name">${d.name}</div><div class="meta">sin posicion</div></div>`;

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
            if (lat !== 0 || lon !== 0) map.setView([lat, lon], 16);
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
        document.getElementById('status-bar').textContent = '— sin conexion al backend —';
    }
}

setInterval(poll, POLL_MS);
poll();
</script>
<style>
    @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.4); } }
</style>
</body>
</html>
