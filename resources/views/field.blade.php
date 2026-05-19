<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="K9 Field">
    <title>K9 Field</title>

    <link rel="manifest" href="/field/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/field/icons/icon.svg">
    <link rel="apple-touch-icon" href="/field/icons/icon.svg">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="/field/styles.css?v=4">
</head>
<body>
    <div id="app">
        <header id="top-bar">
            <div class="brand">K9 Field</div>
            <div class="status">
                <span class="chip" id="chip-ws" title="Conexion al T3">T3 —</span>
                <span class="chip" id="chip-gps" title="GPS del telefono">GPS —</span>
                <span class="chip" id="chip-pkt" title="Paquetes recibidos">0 pkt</span>
            </div>
            <button id="btn-settings" class="icon-btn" aria-label="Configuracion">⚙</button>
        </header>

        <div id="map"></div>

        <button id="fab-waypoint" class="fab" aria-label="Marcar punto de interes">+</button>

        <footer id="bottom-bar">
            <button id="btn-center-dog" class="action-btn primary">🐕 Centrar perro</button>
            <button id="btn-center-me" class="action-btn">📍 Yo</button>
            <button id="btn-fit" class="action-btn">↔ Ajustar</button>
        </footer>

        <div id="picking-banner" class="banner picking-banner hidden">
            <div class="banner-title">Toca el mapa para elegir el punto</div>
            <div class="banner-body">O ESC para cancelar.</div>
        </div>

        <div id="waypoint-sheet" class="sheet hidden">
            <div class="sheet-header">
                <div class="sheet-title">Marcar punto de interes</div>
                <button id="wp-close" class="icon-btn">✕</button>
            </div>
            <div class="sheet-body">
                <div class="wp-position">
                    <div class="wp-coords" id="wp-coords">— sin ubicacion —</div>
                    <div class="wp-pos-actions">
                        <button id="wp-here" class="action-btn">📍 Aqui</button>
                        <button id="wp-pick" class="action-btn">🗺 En mapa</button>
                    </div>
                </div>

                <div class="wp-type-label">Tipo</div>
                <div class="wp-type-grid" id="wp-type-grid">
                    <button class="wp-type" data-type="article"      style="--c:#f59e0b">🦴 Articulo</button>
                    <button class="wp-type" data-type="k9_alert"     style="--c:#ef4444">🐕 Alerta K9</button>
                    <button class="wp-type" data-type="k9_interest"  style="--c:#f97316">👃 Interes</button>
                    <button class="wp-type" data-type="contamination" style="--c:#6b7280">🚫 Contaminacion</button>
                    <button class="wp-type" data-type="rest"         style="--c:#06b6d4">💧 Descanso</button>
                    <button class="wp-type" data-type="other"        style="--c:#8b5cf6">📝 Otro</button>
                </div>

                <label class="field-row">
                    <span>Nota (opcional)</span>
                    <textarea id="wp-note" rows="3" placeholder="Detalles, comportamiento del K9, observaciones..."></textarea>
                </label>

                <label class="field-row">
                    <span>Foto (opcional)</span>
                    <input id="wp-photo" type="file" accept="image/*" capture="environment">
                    <div id="wp-photo-preview" class="wp-photo-preview hidden"></div>
                </label>

                <div class="wp-actions">
                    <button id="wp-cancel" class="action-btn">Cancelar</button>
                    <button id="wp-save" class="action-btn primary" disabled>Guardar</button>
                </div>
            </div>
        </div>

        <div id="connect-banner" class="banner hidden">
            <div class="banner-title">Conecta tu telefono al WiFi del T3</div>
            <div class="banner-body">
                Red: <b>{{ $t3['ssid'] }}</b> · Clave: <b>{{ $t3['pass'] }}</b><br>
                Luego vuelve aqui. Esta app puede operar sin internet.
            </div>
            <button id="banner-retry" class="action-btn primary">Reintentar conexion</button>
        </div>

        <div id="settings-sheet" class="sheet hidden">
            <div class="sheet-header">
                <div class="sheet-title">Configuracion</div>
                <button id="settings-close" class="icon-btn">✕</button>
            </div>
            <div class="sheet-body">
                <label class="field-row">
                    <span>Host del T3</span>
                    <input id="cfg-host" type="text" inputmode="url" autocapitalize="off" autocomplete="off">
                </label>
                <label class="field-row checkbox">
                    <input id="cfg-mock" type="checkbox">
                    <span>Modo simulado (mock) — datos sinteticos para probar sin el T3</span>
                </label>
                <div class="hint">
                    <b>Conexion al T3:</b><br>
                    1. En el ajuste de WiFi del telefono, conectate a <b>{{ $t3['ssid'] }}</b><br>
                    2. Clave: <b>{{ $t3['pass'] }}</b><br>
                    3. Vuelve aqui y toca <i>Reintentar conexion</i>
                </div>

                <div class="section-title">Mapa offline</div>
                <div class="tile-status">
                    Tiles guardados: <b id="tile-count">—</b>
                </div>
                <div class="hint">
                    Usa los botones <b>⬇</b> (arriba a la derecha del mapa) para guardar los tiles del area visible
                    en zoom 14-17, o <b>🗑</b> para borrar todos. Hazlo con WiFi/4G antes de salir al monte.
                </div>

                <div class="section-title">Sincronizacion al VPS</div>
                <label class="field-row">
                    <span>URL del VPS (vacio = sync desactivada)</span>
                    <input id="cfg-vps" type="url" inputmode="url" autocapitalize="off" autocomplete="off"
                           placeholder="https://mi-vps.com">
                </label>
                <label class="field-row checkbox">
                    <input id="cfg-sync-auto" type="checkbox">
                    <span>Sincronizar automaticamente cuando haya internet (cada 60s)</span>
                </label>
                <div class="tile-status">
                    Pendientes: <b id="sync-pending">—</b> · Ultima sync: <b id="sync-last">nunca</b>
                </div>
                <button id="btn-sync-now" class="action-btn primary">☁ Sincronizar ahora</button>
                <div id="sync-status" class="hint hidden"></div>
            </div>
        </div>
    </div>

    <script type="application/json" id="k9-config">
        {!! json_encode([
            't3Host'   => $t3['host'],
            't3Ssid'   => $t3['ssid'],
            't3Pass'   => $t3['pass'],
            'baseName' => $base['name'],
            'baseLat'  => $base['lat'],
            'baseLon'  => $base['lon'],
        ]) !!}
    </script>
    <script>
        window.K9_CONFIG = JSON.parse(document.getElementById('k9-config').textContent);
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/dexie@4.0.8/dist/dexie.min.js"></script>
    <script src="https://unpkg.com/leaflet.offline@3.1.2/dist/bundle.js"></script>
    <script src="/field/app.js?v=4"></script>
</body>
</html>
