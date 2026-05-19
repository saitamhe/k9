/* K9 Field — PWA del guia de perro
 * ================================
 * Arquitectura:
 *   - DataSource: abstrae la fuente de paquetes (T3 real por WS, o mock JS)
 *   - MapView:    Leaflet + markers de perro y guia + trail
 *   - Geo:        navigator.geolocation del telefono del guia
 *   - UI:         top bar (chips), bottom bar (acciones), settings sheet, banner
 *
 * Protocolo del paquete (mismo formato que tu emitJson() en el T3):
 *   { v, id, seq, lat, lon, alt, spd, hdg, ts, flags, rssi, snr }
 *
 * Flags (bitmask) — debe coincidir con shared/protocol.h:
 *   0x01 MOVING, 0x02 NO_FIX, 0x04 SOS, 0x08 LOW_BAT
 */

(() => {
'use strict';

// ===== Config =====
const CONFIG_KEY = 'k9.field.config';

const DEFAULT_CONFIG = {
    host: (window.K9_CONFIG && window.K9_CONFIG.t3Host) || '192.168.4.1',
    mock: false,
    vpsUrl: '',     // URL base del VPS (sin slash final). Vacio = sync desactivada.
    syncAuto: true, // intentar sync cada 60s cuando online
};

function loadConfig() {
    try {
        const saved = JSON.parse(localStorage.getItem(CONFIG_KEY)) || {};
        return { ...DEFAULT_CONFIG, ...saved };
    } catch {
        return { ...DEFAULT_CONFIG };
    }
}
function saveConfig(cfg) {
    localStorage.setItem(CONFIG_KEY, JSON.stringify(cfg));
}

// El query param ?mock=1 fuerza modo mock (util para QR-share / dev).
const urlParams = new URLSearchParams(location.search);
let cfg = loadConfig();
if (urlParams.get('mock') === '1') cfg.mock = true;

// ===== UUID =====
// crypto.randomUUID solo existe en contextos seguros (HTTPS/localhost). En el
// T3 (HTTP no-localhost) usamos un fallback decente (no criptografico, ok para
// claves de waypoint donde no necesitamos imprevisibilidad).
function uuid() {
    if (window.crypto && typeof crypto.randomUUID === 'function') {
        try { return crypto.randomUUID(); } catch {}
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

// ===== Dexie (IndexedDB) =====
// Schema v1: positions, waypoints, sessions.
// Tiles offline se manejan en una DB aparte (leaflet.offline) para no acoplarlas.
const db = new Dexie('K9Field');
db.version(1).stores({
    // Buffer de paquetes del perro. Clave compuesta (node_id, seq): dedup natural
    // del firmware. synced=0 hasta que /api/sync/batch confirme.
    positions: '[node_id+seq], received_at, synced',
    // PoIs del guia. UUID generado client-side para upsert remoto idempotente.
    waypoints: 'uuid, type, recorded_at, synced',
    // Sesion = una "salida de campo". Por ahora 1 sesion por carga de pagina.
    // Mapeo futuro al modelo formal `deployments` del backend.
    sessions: 'uuid, started_at',
});

// Sesion activa: genero o continuo la mas reciente abierta. Sin "cerrar sesion"
// explicito todavia — eso vendra con el modal de cierre/POD del Sprint final.
let sessionId = null;
async function initSession() {
    const open = await db.sessions.orderBy('started_at').reverse().first();
    if (open) {
        sessionId = open.uuid;
    } else {
        sessionId = uuid();
        await db.sessions.add({
            uuid: sessionId,
            started_at: new Date().toISOString(),
        });
    }
    console.log('[K9] sesion activa:', sessionId);
}

// Helpers de storage
async function persistPosition(pkt) {
    try {
        await db.positions.put({
            node_id: pkt.id,
            seq: pkt.seq,
            session_id: sessionId,
            v: pkt.v,
            lat: pkt.lat,
            lon: pkt.lon,
            alt: pkt.alt,
            spd: pkt.spd,
            hdg: pkt.hdg,
            ts: pkt.ts,
            flags: pkt.flags,
            rssi: pkt.rssi,
            snr: pkt.snr,
            received_at: new Date().toISOString(),
            synced: 0,
        });
    } catch (e) { console.warn('[DB] persist position fail', e); }
}

const WAYPOINT_TYPES = {
    article:       { icon: '🦴', color: '#f59e0b', label: 'Articulo' },
    k9_alert:      { icon: '🐕', color: '#ef4444', label: 'Alerta K9' },
    k9_interest:   { icon: '👃', color: '#f97316', label: 'Interes K9' },
    contamination: { icon: '🚫', color: '#6b7280', label: 'Contaminacion' },
    rest:          { icon: '💧', color: '#06b6d4', label: 'Descanso' },
    other:         { icon: '📝', color: '#8b5cf6', label: 'Otro' },
};

async function persistWaypoint(wp) {
    await db.waypoints.put(wp);
}
async function deleteWaypoint(wpUuid) {
    await db.waypoints.delete(wpUuid);
}
async function loadAllWaypoints() {
    return db.waypoints.toArray();
}

async function countPending() {
    const pos = await db.positions.where('synced').equals(0).count();
    const wp  = await db.waypoints.where('synced').equals(0).count();
    return { pos, wp, total: pos + wp };
}

// ===== SyncQueue =====
// Drena registros con synced=0 hacia /api/sync/batch del VPS y sube fotos
// de waypoints aparte via /api/waypoints/{uuid}/photo. Idempotente: el upsert
// del backend tolera reenvios. No bloquea la UI; se ejecuta en background.
class SyncQueue {
    constructor() {
        this.busy = false;
        this.lastSync = null;
        this.lastError = null;
        this.onState = () => {};
    }

    _vpsUrl() {
        return (cfg.vpsUrl || '').replace(/\/+$/, '');
    }

    async run() {
        const base = this._vpsUrl();
        if (this.busy) return { skipped: 'busy' };
        if (!base) return { skipped: 'no_vps' };
        if (!navigator.onLine) return { skipped: 'offline' };

        this.busy = true;
        this.lastError = null;
        this.onState({ syncing: true });

        try {
            const POS_BATCH = 500;
            const WP_BATCH  = 50;

            const positions = await db.positions.where('synced').equals(0).limit(POS_BATCH).toArray();
            const waypoints = await db.waypoints.where('synced').equals(0).limit(WP_BATCH).toArray();

            if (positions.length === 0 && waypoints.length === 0) {
                // Igual vemos si quedan fotos por subir
                await this._uploadPendingPhotos(base);
                this.lastSync = new Date();
                this.onState({ syncing: false, ok: true, empty: true, lastSync: this.lastSync });
                return { ok: true, empty: true };
            }

            const payload = {
                positions: positions.map((p) => ({
                    node_id: p.node_id, seq: p.seq,
                    lat: p.lat, lon: p.lon, alt: p.alt, spd: p.spd, hdg: p.hdg,
                    ts: p.ts, flags: p.flags, rssi: p.rssi, snr: p.snr,
                    received_at: p.received_at,
                })),
                waypoints: waypoints.map((w) => ({
                    uuid: w.uuid, session_id: w.session_id, type: w.type,
                    lat: w.lat, lon: w.lon, note: w.note, recorded_at: w.recorded_at,
                })),
            };

            const res = await fetch(`${base}/api/sync/batch`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const txt = await res.text().catch(() => '');
                throw new Error(`HTTP ${res.status}: ${txt.slice(0, 200)}`);
            }

            // Marca como sincronizados los confirmados por el backend
            const body = await res.json();
            const okPos = new Set((body.positions_synced || []).map((p) => `${p.node_id}:${p.seq}`));
            const okWp  = new Set(body.waypoints_synced || []);

            await db.transaction('rw', db.positions, db.waypoints, async () => {
                for (const p of positions) {
                    if (okPos.has(`${p.node_id}:${p.seq}`)) {
                        await db.positions.update([p.node_id, p.seq], { synced: 1 });
                    }
                }
                for (const w of waypoints) {
                    if (okWp.has(w.uuid)) {
                        await db.waypoints.update(w.uuid, { synced: 1 });
                    }
                }
            });

            // Una vez sincronizados los metadatos, intentamos subir las fotos
            await this._uploadPendingPhotos(base);

            this.lastSync = new Date();
            this.onState({ syncing: false, ok: true, lastSync: this.lastSync, posCount: positions.length, wpCount: waypoints.length });
            return { ok: true, posCount: positions.length, wpCount: waypoints.length };
        } catch (e) {
            console.warn('[sync] fail', e);
            this.lastError = e.message;
            this.onState({ syncing: false, error: e.message });
            return { ok: false, error: e.message };
        } finally {
            this.busy = false;
        }
    }

    // Sube fotos de waypoints ya sincronizados que aun no tienen photo_uploaded=1.
    // Una request por foto: simple, robusta, y si una falla las demas continuan.
    async _uploadPendingPhotos(base) {
        const wps = await db.waypoints
            .filter((w) => w.photo && !w.photo_uploaded)
            .toArray();

        for (const w of wps) {
            try {
                const fd = new FormData();
                const filename = `${w.uuid}.jpg`;
                fd.append('photo', w.photo, filename);
                const r = await fetch(`${base}/api/waypoints/${encodeURIComponent(w.uuid)}/photo`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: fd,
                });
                if (r.ok) {
                    await db.waypoints.update(w.uuid, { photo_uploaded: 1 });
                } else {
                    console.warn('[sync] foto fail', w.uuid, r.status);
                }
            } catch (e) {
                console.warn('[sync] foto exception', w.uuid, e);
            }
        }
    }
}

// ===== Flags del firmware =====
const FLAG_MOVING  = 0x01;
const FLAG_NO_FIX  = 0x02;
const FLAG_SOS     = 0x04;
const FLAG_LOW_BAT = 0x08;

const hasFix   = (f) => (f & FLAG_NO_FIX) === 0;
const isMoving = (f) => (f & FLAG_MOVING) !== 0;

// ===== Estado global de UI =====
const state = {
    wsConnected: false,
    wsReconnecting: false,
    pktCount: 0,
    lastPacket: null,
    gpsOk: false,
    handlerPos: null,
};

// ===== UI helpers =====
const $ = (sel) => document.querySelector(sel);

function setChip(el, label, kind /* 'ok'|'warn'|'bad'|'busy'|null */) {
    el.textContent = label;
    el.classList.remove('ok', 'warn', 'bad', 'busy');
    if (kind) el.classList.add(kind);
}

function refreshChips() {
    if (state.wsReconnecting) {
        setChip($('#chip-ws'), cfg.mock ? 'MOCK ◌' : 'T3 ◌', 'busy');
    } else if (state.wsConnected) {
        setChip($('#chip-ws'), cfg.mock ? 'MOCK ●' : 'T3 ●', 'ok');
    } else {
        setChip($('#chip-ws'), cfg.mock ? 'MOCK ✕' : 'T3 ✕', 'bad');
    }
    setChip($('#chip-gps'), state.gpsOk ? 'GPS ●' : 'GPS ✕', state.gpsOk ? 'ok' : 'warn');
    setChip($('#chip-pkt'), `${state.pktCount} pkt`, state.pktCount > 0 ? 'ok' : null);
}

function showBanner(show) {
    $('#connect-banner').classList.toggle('hidden', !show);
}

// ===== DataSource (interfaz) =====
// Cualquier fuente debe exponer:
//   start(), stop(), onPacket(cb), onStateChange(cb)

// ----- T3 (real) -----
class T3DataSource {
    constructor(host) {
        this.host = host;
        this.ws = null;
        this.shouldRun = false;
        this.reconnectAttempt = 0;
        this.reconnectTimer = null;
        this.packetCb = () => {};
        this.stateCb = () => {};
    }
    onPacket(cb) { this.packetCb = cb; }
    onStateChange(cb) { this.stateCb = cb; }

    start() {
        this.shouldRun = true;
        this.reconnectAttempt = 0;
        this._connect();
    }
    stop() {
        this.shouldRun = false;
        if (this.reconnectTimer) { clearTimeout(this.reconnectTimer); this.reconnectTimer = null; }
        if (this.ws) {
            try { this.ws.close(); } catch {}
            this.ws = null;
        }
        this.stateCb({ connected: false, reconnecting: false });
    }

    _connect() {
        const url = `ws://${this.host}/api/stream`;
        this.stateCb({ connected: false, reconnecting: true });
        try {
            this.ws = new WebSocket(url);
        } catch (e) {
            console.warn('[T3] WS construct fail', e);
            this._scheduleReconnect();
            return;
        }

        this.ws.onopen = () => {
            console.log('[T3] WS conectado');
            this.reconnectAttempt = 0;
            this.stateCb({ connected: true, reconnecting: false });
        };
        this.ws.onmessage = (ev) => {
            let pkt;
            try { pkt = JSON.parse(ev.data); }
            catch { console.warn('[T3] WS message no es JSON:', ev.data); return; }
            // Filtra mensajes que no son paquetes de posicion (status, error)
            if (typeof pkt.lat !== 'number' || typeof pkt.lon !== 'number') return;
            this.packetCb(pkt);
        };
        this.ws.onerror = (e) => {
            // El handler real es onclose; aqui solo log
            console.warn('[T3] WS error', e);
        };
        this.ws.onclose = () => {
            console.log('[T3] WS cerrado');
            this.ws = null;
            this.stateCb({ connected: false, reconnecting: this.shouldRun });
            if (this.shouldRun) this._scheduleReconnect();
        };
    }

    _scheduleReconnect() {
        // Backoff exponencial 1s, 2s, 4s, 8s, max 16s
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempt), 16000);
        this.reconnectAttempt++;
        this.reconnectTimer = setTimeout(() => {
            if (this.shouldRun) this._connect();
        }, delay);
    }
}

// ----- Mock (sintetico) -----
// Camina al "perro" en circulos lentos alrededor de la base.
class MockDataSource {
    constructor(centerLat, centerLon) {
        this.centerLat = centerLat;
        this.centerLon = centerLon;
        this.timer = null;
        this.seq = 0;
        this.t = 0;
        this.packetCb = () => {};
        this.stateCb = () => {};
    }
    onPacket(cb) { this.packetCb = cb; }
    onStateChange(cb) { this.stateCb = cb; }

    start() {
        this.stateCb({ connected: true, reconnecting: false });
        this.timer = setInterval(() => this._tick(), 2000);
        // Primer tick inmediato para que se vea algo al toque
        setTimeout(() => this._tick(), 100);
    }
    stop() {
        if (this.timer) { clearInterval(this.timer); this.timer = null; }
        this.stateCb({ connected: false, reconnecting: false });
    }

    _tick() {
        this.t += 0.08;
        // Radio ~80m alrededor del centro
        const radDeg = 0.0008;
        const lat = this.centerLat + radDeg * Math.sin(this.t);
        const lon = this.centerLon + radDeg * Math.cos(this.t) * 1.2;
        const hdg = ((Math.atan2(Math.cos(this.t) * 1.2, Math.sin(this.t)) * 180 / Math.PI) + 360 + 90) % 360;
        const speed = 1.0 + 0.3 * Math.sin(this.t * 2);
        const pkt = {
            v: 1, id: 1, seq: this.seq++,
            lat: +lat.toFixed(7), lon: +lon.toFixed(7),
            alt: 543, spd: +speed.toFixed(2), hdg: +hdg.toFixed(1),
            ts: Math.floor(Date.now() / 1000),
            flags: FLAG_MOVING, // siempre moviendo en el mock
            rssi: -78 + Math.floor(Math.random() * 10),
            snr: +(8 + Math.random() * 4).toFixed(1),
        };
        this.packetCb(pkt);
    }
}

// ===== MapView =====
class MapView {
    constructor(elId, centerLat, centerLon) {
        this.map = L.map(elId, {
            zoomControl: true,
            attributionControl: true,
            tap: true,
        }).setView([centerLat, centerLon], 15);

        // Tile layer offline-capable: chequea IndexedDB antes de pedir a la red.
        // Si el tile esta cacheado, se sirve sin internet; sino, fallback a OSM.
        this.baseLayer = L.tileLayer.offline('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            subdomains: 'abc',
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19,
            minZoom: 1,
            crossOrigin: true,  // necesario para que el fetch del save pueda leer el blob
        });
        this.baseLayer.addTo(this.map);

        // Control de guardado offline (esquina superior derecha del mapa).
        // Dos botones: ⬇ guardar tiles del area visible, 🗑 borrar todos.
        this.saveControl = L.control.savetiles(this.baseLayer, {
            position: 'topright',
            zoomlevels: [14, 15, 16, 17],
            saveText: '⬇',
            rmText: '🗑',
            confirm(status, save) {
                const n = status._tilesforSave.length;
                const kb = Math.round(n * 25); // tile OSM tipico ~25 KB
                if (confirm(`Guardar ${n} tiles del area visible? (~${kb} KB)`)) save();
            },
            confirmRemoval(status, remove) {
                if (confirm('Borrar todos los tiles offline guardados?')) remove();
            },
        });
        this.saveControl.addTo(this.map);

        // Refresca el contador al terminar save/clear
        this.saveControl.on('saveend', () => this._refreshTileCount());
        this.saveControl.on('tilesremoved', () => this._refreshTileCount());
        this._refreshTileCount();

        // Base como triangulo discreto
        this.baseMarker = L.marker([centerLat, centerLon], {
            icon: L.divIcon({
                html: '<div style="width:0;height:0;border-left:10px solid transparent;border-right:10px solid transparent;border-bottom:16px solid #06b6d4;filter:drop-shadow(0 0 2px rgba(0,0,0,0.6));"></div>',
                className: '',
                iconSize: [20, 16],
                iconAnchor: [10, 16],
            }),
            interactive: false,
        }).addTo(this.map);

        this.dogMarker = null;
        this.handlerMarker = null;
        this.handlerCircle = null;
        this.trail = L.polyline([], { color: '#ef4444', weight: 4, opacity: 0.7 }).addTo(this.map);
        this.firstFix = false;

        // Waypoints: layer dedicado para poder limpiar sin tocar la traza.
        this.waypointLayer = L.layerGroup().addTo(this.map);
        this.waypointMarkers = new Map(); // uuid -> marker
    }

    addWaypoint(wp) {
        const t = WAYPOINT_TYPES[wp.type] || WAYPOINT_TYPES.other;
        const marker = L.marker([wp.lat, wp.lon], {
            icon: L.divIcon({
                html: `<div class="wp-marker" style="background:${t.color}">${t.icon}</div>`,
                className: '',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
            }),
        });
        marker.bindPopup(this._renderWaypointPopup(wp), { maxWidth: 280 });
        this.waypointLayer.addLayer(marker);
        this.waypointMarkers.set(wp.uuid, marker);
    }

    removeWaypoint(wpUuid) {
        const m = this.waypointMarkers.get(wpUuid);
        if (m) {
            this.waypointLayer.removeLayer(m);
            this.waypointMarkers.delete(wpUuid);
        }
    }

    _renderWaypointPopup(wp) {
        const t = WAYPOINT_TYPES[wp.type] || WAYPOINT_TYPES.other;
        const when = new Date(wp.recorded_at).toLocaleString();
        const div = document.createElement('div');

        const title = document.createElement('div');
        title.className = 'wp-popup-title';
        title.style.color = t.color;
        title.textContent = `${t.icon} ${t.label}`;
        div.appendChild(title);

        const meta = document.createElement('div');
        meta.className = 'wp-popup-meta';
        meta.textContent = `${wp.lat.toFixed(6)}, ${wp.lon.toFixed(6)}\n${when}`;
        div.appendChild(meta);

        if (wp.note) {
            const note = document.createElement('div');
            note.className = 'wp-popup-note';
            note.textContent = wp.note;
            div.appendChild(note);
        }

        if (wp.photo) {
            const ph = document.createElement('div');
            ph.className = 'wp-popup-photo';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(wp.photo);
            ph.appendChild(img);
            div.appendChild(ph);
        }

        const del = document.createElement('button');
        del.className = 'wp-popup-del';
        del.textContent = '🗑 Borrar punto';
        del.addEventListener('click', async () => {
            if (!confirm('Borrar este punto?')) return;
            await deleteWaypoint(wp.uuid);
            this.removeWaypoint(wp.uuid);
        });
        div.appendChild(del);

        return div;
    }

    updateDog(pkt) {
        if (!hasFix(pkt.flags) || (pkt.lat === 0 && pkt.lon === 0)) return;
        const ll = [pkt.lat, pkt.lon];
        const moving = isMoving(pkt.flags);

        if (!this.dogMarker) {
            this.dogMarker = L.marker(ll, {
                icon: L.divIcon({
                    html: `<div class="dog-marker ${moving ? 'moving' : ''}"></div>`,
                    className: '',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                }),
            }).addTo(this.map);
        } else {
            this.dogMarker.setLatLng(ll);
            this.dogMarker.setIcon(L.divIcon({
                html: `<div class="dog-marker ${moving ? 'moving' : ''}"></div>`,
                className: '',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            }));
        }
        this.trail.addLatLng(ll);

        if (!this.firstFix) {
            this.map.setView(ll, 16);
            this.firstFix = true;
        }
    }

    updateHandler(coords) {
        const ll = [coords.latitude, coords.longitude];
        const accuracy = coords.accuracy || 0;

        if (!this.handlerMarker) {
            this.handlerMarker = L.marker(ll, {
                icon: L.divIcon({
                    html: '<div class="handler-marker"></div>',
                    className: '',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10],
                }),
                interactive: false,
            }).addTo(this.map);
        } else {
            this.handlerMarker.setLatLng(ll);
        }

        // Circulo de precision (radio en metros)
        if (this.handlerCircle) this.handlerCircle.setLatLng(ll).setRadius(accuracy);
        else this.handlerCircle = L.circle(ll, { radius: accuracy, className: 'handler-accuracy' }).addTo(this.map);
    }

    centerOnDog() {
        if (this.dogMarker) this.map.setView(this.dogMarker.getLatLng(), 17);
    }
    centerOnHandler() {
        if (this.handlerMarker) this.map.setView(this.handlerMarker.getLatLng(), 17);
    }
    fitBoth() {
        const bounds = L.latLngBounds([]);
        if (this.dogMarker) bounds.extend(this.dogMarker.getLatLng());
        if (this.handlerMarker) bounds.extend(this.handlerMarker.getLatLng());
        bounds.extend(this.baseMarker.getLatLng());
        if (bounds.isValid()) this.map.fitBounds(bounds.pad(0.2));
    }

    _refreshTileCount() {
        if (!this.saveControl || typeof this.saveControl.setStorageSize !== 'function') return;
        this.saveControl.setStorageSize((n) => {
            const el = document.getElementById('tile-count');
            if (el) el.textContent = String(n);
        });
    }
}

// ===== Geolocation del guia =====
class HandlerGeolocation {
    constructor(onUpdate) {
        this.onUpdate = onUpdate;
        this.watchId = null;
    }
    start() {
        if (!navigator.geolocation) {
            console.warn('Geolocation no soportada');
            return;
        }
        this.watchId = navigator.geolocation.watchPosition(
            (pos) => this.onUpdate(pos.coords, null),
            (err) => this.onUpdate(null, err),
            { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 }
        );
    }
    stop() {
        if (this.watchId != null) navigator.geolocation.clearWatch(this.watchId);
        this.watchId = null;
    }
}

// ===== WaypointController =====
// Maneja el flujo de captura: abrir sheet, elegir ubicacion, tipo, nota, foto,
// guardar en Dexie, renderizar en el mapa. La ubicacion por defecto es el GPS
// del guia (si esta disponible); si el guia quiere otro punto, "pick on map".
class WaypointController {
    constructor(mapView, getHandlerCoords) {
        this.mapView = mapView;
        this.getHandlerCoords = getHandlerCoords;
        this.pickingFromMap = false;
        this.draft = null;          // { lat, lon, type, note, photoBlob }
        this.photoObjectUrl = null;

        this._bindUI();
        this._wireMapPicker();
        this._wireKeyboard();
    }

    _bindUI() {
        $('#fab-waypoint').addEventListener('click', () => this.open());
        $('#wp-close').addEventListener('click', () => this.close());
        $('#wp-cancel').addEventListener('click', () => this.close());

        $('#wp-here').addEventListener('click', () => this._useHandlerLocation());
        $('#wp-pick').addEventListener('click', () => this._startMapPick());

        $('#wp-type-grid').addEventListener('click', (ev) => {
            const btn = ev.target.closest('.wp-type');
            if (!btn) return;
            this._selectType(btn.dataset.type);
        });

        $('#wp-note').addEventListener('input', (ev) => {
            if (this.draft) this.draft.note = ev.target.value;
        });

        $('#wp-photo').addEventListener('change', (ev) => this._onPhotoSelected(ev.target.files[0]));

        $('#wp-save').addEventListener('click', () => this.save());
    }

    _wireMapPicker() {
        this.mapView.map.on('click', (ev) => {
            if (!this.pickingFromMap) return;
            this.draft.lat = ev.latlng.lat;
            this.draft.lon = ev.latlng.lng;
            this._stopMapPick();
            this._refreshSheet();
        });
    }

    _wireKeyboard() {
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape') {
                if (this.pickingFromMap) this._stopMapPick();
                else if (!$('#waypoint-sheet').classList.contains('hidden')) this.close();
            }
        });
    }

    open() {
        // Inicializa draft con la mejor ubicacion disponible: GPS del guia,
        // sino el ultimo paquete del perro, sino el centro de la base.
        const coords = this.getHandlerCoords();
        let lat, lon;
        if (coords) { lat = coords.latitude; lon = coords.longitude; }
        else if (state.lastPacket && hasFix(state.lastPacket.flags)) {
            lat = state.lastPacket.lat; lon = state.lastPacket.lon;
        } else {
            lat = window.K9_CONFIG.baseLat; lon = window.K9_CONFIG.baseLon;
        }
        this.draft = { lat, lon, type: null, note: '', photoBlob: null };

        $('#wp-note').value = '';
        $('#wp-photo').value = '';
        $('#wp-photo-preview').classList.add('hidden');
        $('#wp-photo-preview').innerHTML = '';
        if (this.photoObjectUrl) { URL.revokeObjectURL(this.photoObjectUrl); this.photoObjectUrl = null; }
        document.querySelectorAll('.wp-type.selected').forEach(el => el.classList.remove('selected'));

        this._refreshSheet();
        $('#waypoint-sheet').classList.remove('hidden');
    }

    close() {
        $('#waypoint-sheet').classList.add('hidden');
        this._stopMapPick();
        this.draft = null;
        if (this.photoObjectUrl) { URL.revokeObjectURL(this.photoObjectUrl); this.photoObjectUrl = null; }
    }

    _useHandlerLocation() {
        const coords = this.getHandlerCoords();
        if (!coords) {
            alert('GPS del telefono no disponible. Toca "En mapa" para elegir el punto manualmente.');
            return;
        }
        this.draft.lat = coords.latitude;
        this.draft.lon = coords.longitude;
        this._refreshSheet();
    }

    _startMapPick() {
        this.pickingFromMap = true;
        $('#waypoint-sheet').classList.add('hidden');
        $('#picking-banner').classList.remove('hidden');
    }
    _stopMapPick() {
        this.pickingFromMap = false;
        $('#picking-banner').classList.add('hidden');
        if (this.draft) $('#waypoint-sheet').classList.remove('hidden');
    }

    _selectType(type) {
        if (!WAYPOINT_TYPES[type]) return;
        this.draft.type = type;
        document.querySelectorAll('.wp-type').forEach(el => {
            el.classList.toggle('selected', el.dataset.type === type);
        });
        this._refreshSheet();
    }

    async _onPhotoSelected(file) {
        if (!file) return;
        // Downscale para que la foto no infle el IndexedDB. 1280px de lado largo
        // a JPEG 0.85 produce ~150-250KB tipico.
        try {
            const blob = await downscaleImage(file, 1280, 0.85);
            this.draft.photoBlob = blob;
            if (this.photoObjectUrl) URL.revokeObjectURL(this.photoObjectUrl);
            this.photoObjectUrl = URL.createObjectURL(blob);
            const prev = $('#wp-photo-preview');
            prev.innerHTML = `<img alt="preview">`;
            prev.querySelector('img').src = this.photoObjectUrl;
            prev.classList.remove('hidden');
        } catch (e) {
            console.warn('Foto fail', e);
            alert('No se pudo procesar la foto');
        }
    }

    _refreshSheet() {
        $('#wp-coords').textContent = (this.draft && this.draft.lat != null)
            ? `${this.draft.lat.toFixed(6)}, ${this.draft.lon.toFixed(6)}`
            : '— sin ubicacion —';
        const ready = this.draft && this.draft.type && this.draft.lat != null;
        $('#wp-save').disabled = !ready;
    }

    async save() {
        if (!this.draft || !this.draft.type) return;
        const wp = {
            uuid: uuid(),
            session_id: sessionId,
            type: this.draft.type,
            lat: +this.draft.lat.toFixed(7),
            lon: +this.draft.lon.toFixed(7),
            note: this.draft.note || '',
            photo: this.draft.photoBlob || null,
            recorded_at: new Date().toISOString(),
            synced: 0,
        };
        try {
            await persistWaypoint(wp);
            this.mapView.addWaypoint(wp);
            this.close();
        } catch (e) {
            console.error('save waypoint fail', e);
            alert('No se pudo guardar el punto: ' + e.message);
        }
    }
}

// Helper: resize de imagen via canvas. Devuelve Blob JPEG.
async function downscaleImage(file, maxSide = 1280, quality = 0.85) {
    const img = await new Promise((resolve, reject) => {
        const i = new Image();
        i.onload = () => resolve(i);
        i.onerror = reject;
        i.src = URL.createObjectURL(file);
    });
    const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
    const w = Math.round(img.width * scale);
    const h = Math.round(img.height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

// ===== Orquestador =====
const mapView = new MapView(
    'map',
    window.K9_CONFIG.baseLat,
    window.K9_CONFIG.baseLon
);

let dataSource = null;

function buildDataSource() {
    if (cfg.mock) {
        return new MockDataSource(window.K9_CONFIG.baseLat, window.K9_CONFIG.baseLon);
    }
    return new T3DataSource(cfg.host);
}

function startDataSource() {
    if (dataSource) dataSource.stop();
    dataSource = buildDataSource();
    dataSource.onPacket((pkt) => {
        state.pktCount++;
        state.lastPacket = pkt;
        mapView.updateDog(pkt);
        // Persistimos TODOS los paquetes (incluso NO_FIX) en IndexedDB —
        // util para auditoria post-mision. El render del mapa filtra los sin fix.
        persistPosition(pkt);
        refreshChips();
    });
    dataSource.onStateChange((s) => {
        state.wsConnected = s.connected;
        state.wsReconnecting = s.reconnecting;
        refreshChips();
        // Banner de "conectate al WiFi" solo aplica al T3 real
        showBanner(!cfg.mock && !s.connected && !s.reconnecting);
    });
    dataSource.start();
    refreshChips();
}

// Geolocation
const handlerGeo = new HandlerGeolocation((coords, err) => {
    if (err) {
        state.gpsOk = false;
        refreshChips();
        return;
    }
    state.gpsOk = true;
    state.handlerPos = coords;
    mapView.updateHandler(coords);
    refreshChips();
});
handlerGeo.start();

// ===== Eventos UI =====
$('#btn-center-dog').addEventListener('click', () => mapView.centerOnDog());
$('#btn-center-me').addEventListener('click', () => mapView.centerOnHandler());
$('#btn-fit').addEventListener('click', () => mapView.fitBoth());

$('#btn-settings').addEventListener('click', async () => {
    $('#cfg-host').value = cfg.host;
    $('#cfg-mock').checked = !!cfg.mock;
    $('#cfg-vps').value = cfg.vpsUrl || '';
    $('#cfg-sync-auto').checked = !!cfg.syncAuto;
    mapView._refreshTileCount();
    refreshSyncUI();
    $('#settings-sheet').classList.remove('hidden');
});
$('#settings-close').addEventListener('click', () => {
    const newHost   = $('#cfg-host').value.trim() || DEFAULT_CONFIG.host;
    const newMock   = $('#cfg-mock').checked;
    const newVps    = $('#cfg-vps').value.trim();
    const newAuto   = $('#cfg-sync-auto').checked;
    const dataChanged = (newHost !== cfg.host) || (newMock !== cfg.mock);

    cfg.host = newHost;
    cfg.mock = newMock;
    cfg.vpsUrl = newVps;
    cfg.syncAuto = newAuto;
    saveConfig(cfg);
    $('#settings-sheet').classList.add('hidden');

    if (dataChanged) {
        state.pktCount = 0;
        mapView.trail.setLatLngs([]);
        if (mapView.dogMarker) { mapView.map.removeLayer(mapView.dogMarker); mapView.dogMarker = null; }
        mapView.firstFix = false;
        startDataSource();
    }
});

$('#banner-retry').addEventListener('click', () => startDataSource());

// Visibility: cuando la app vuelve a foreground, intentar reconectar si caido
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !state.wsConnected && !state.wsReconnecting) {
        startDataSource();
    }
});

// Waypoint controller — inicializa despues del mapView, pasa accessor del GPS
const wpController = new WaypointController(mapView, () => state.handlerPos);

// ===== Sync UI + scheduler =====
const syncQueue = new SyncQueue();

async function refreshSyncUI() {
    try {
        const { total, pos, wp } = await countPending();
        const elPend = document.getElementById('sync-pending');
        if (elPend) elPend.textContent = `${total} (${pos} pos, ${wp} wp)`;
    } catch {}
    const elLast = document.getElementById('sync-last');
    if (elLast) {
        elLast.textContent = syncQueue.lastSync
            ? syncQueue.lastSync.toLocaleTimeString()
            : (syncQueue.lastError ? 'error' : 'nunca');
    }
}

function setSyncStatusMsg(msg, kind /* 'ok'|'warn'|'bad'|null */) {
    const el = document.getElementById('sync-status');
    if (!el) return;
    if (!msg) { el.classList.add('hidden'); return; }
    el.classList.remove('hidden');
    el.textContent = msg;
    el.dataset.kind = kind || '';
}

syncQueue.onState = (s) => {
    if (s.syncing) setSyncStatusMsg('Sincronizando...', 'busy');
    else if (s.error) setSyncStatusMsg(`Error: ${s.error}`, 'bad');
    else if (s.ok && s.empty) setSyncStatusMsg('Nada por sincronizar', 'ok');
    else if (s.ok) setSyncStatusMsg(`Sincronizado: ${s.posCount || 0} pos, ${s.wpCount || 0} wp`, 'ok');
    refreshSyncUI();
};

$('#btn-sync-now').addEventListener('click', () => syncQueue.run());

// Drain periodico cada 60s si syncAuto + online + vpsUrl configurada
setInterval(() => {
    if (cfg.syncAuto && navigator.onLine && cfg.vpsUrl) syncQueue.run();
}, 60_000);

// Drenar inmediatamente al recuperar conexion (Android/iOS dispara este evento)
window.addEventListener('online', () => {
    if (cfg.syncAuto && cfg.vpsUrl) syncQueue.run();
});

// Arranque asincronico: sesion -> hydrate waypoints -> WS
(async () => {
    try {
        await initSession();
        const stored = await loadAllWaypoints();
        for (const wp of stored) mapView.addWaypoint(wp);
        console.log(`[K9] hidratados ${stored.length} waypoints`);
    } catch (e) {
        console.error('[K9] init fail', e);
    }
    startDataSource();
    refreshChips();
})();

// ===== Service Worker =====
// Lo registramos solo en contextos seguros (HTTPS o localhost). En el T3 (HTTP
// no-localhost) el navegador rechaza la registracion, asi que ni intentamos.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    navigator.serviceWorker.register('/field/sw.js', { scope: '/field/' })
        .then((reg) => console.log('[SW] registrado, scope:', reg.scope))
        .catch((e) => console.warn('[SW] no se registro:', e));
}

})();
