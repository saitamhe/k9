@php
    $hideTopbar = $hideTopbar ?? false;
    $bodyClass  = $bodyClass  ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="K9 SAR">
    <title>@yield('title', 'Rastreo K9 SAR')</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="apple-touch-icon" href="/icons/icon.svg">

    <style>
        :root {
            --bg: #0a0a0a;
            --panel: #1a1a1a;
            --panel-2: #262626;
            --border: #2a2a2a;
            --text: #eee;
            --text-muted: #888;
            --accent: #06b6d4;
            --accent-fg: #000;
            --safe-top: env(safe-area-inset-top, 0px);
            --topbar-h: calc(56px + var(--safe-top));
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; background: var(--bg); color: var(--text);
            font-family: -apple-system, Segoe UI, Roboto, sans-serif;
            -webkit-text-size-adjust: 100%;
            overscroll-behavior-y: none;
        }
        body { min-height: 100vh; }
        body.fixed-viewport {
            height: 100vh; height: 100dvh; overflow: hidden;
        }
        a { color: var(--accent); }

        .topbar {
            position: sticky; top: 0; z-index: 1500;
            background: #111; border-bottom: 1px solid var(--border);
            min-height: var(--topbar-h);
            padding-top: var(--safe-top);
            display: flex; align-items: center;
            padding-left: 12px; padding-right: 12px;
            gap: 8px;
        }
        .topbar-brand {
            font-weight: 700; color: var(--text); text-decoration: none; font-size: 14px;
            letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;
            white-space: nowrap; flex-shrink: 0;
        }
        .topbar-brand .logo { width: 28px; height: 28px; flex-shrink: 0; }
        .topbar-brand .name { color: var(--accent); }

        .topbar-nav {
            display: flex; align-items: center; gap: 2px; flex: 1; min-width: 0;
        }
        .topbar-nav a {
            color: var(--text); text-decoration: none; font-size: 13px;
            padding: 8px 12px; border-radius: 4px; white-space: nowrap;
        }
        .topbar-nav a:hover { background: #222; }
        .topbar-nav a.active { background: var(--panel-2); color: var(--accent); }

        .topbar-user {
            display: flex; align-items: center; gap: 10px;
            color: var(--text-muted); font-size: 12px;
            flex-shrink: 0;
        }
        .topbar-user form { margin: 0; }
        .topbar-user .who { white-space: nowrap; }
        .topbar-user .who b { color: var(--text); }
        .topbar-user .logout {
            background: #1f2937; border: 1px solid #374151; color: var(--accent);
            padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;
            font-family: inherit; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
            min-height: 36px;
        }
        .topbar-user .logout:hover { background: #273548; }
        .topbar-user .logout-icon { font-size: 14px; }
        .topbar-user .logout-text { display: inline; }

        .topbar-toggle {
            background: #1f2937; border: 1px solid #374151; color: var(--text);
            width: 40px; height: 40px; border-radius: 6px; cursor: pointer; display: none;
            font-size: 18px; line-height: 1; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        @media (max-width: 820px) {
            .topbar { padding-left: 10px; padding-right: 10px; gap: 6px; }
            .topbar-toggle { display: inline-flex; }
            .topbar-nav {
                position: fixed; top: var(--topbar-h); left: 0; right: 0;
                background: #111; border-bottom: 1px solid var(--border);
                flex-direction: column; align-items: stretch; gap: 0;
                padding: 6px 0; transform: translateY(-110%); transition: transform 0.2s ease;
                z-index: 1450; max-height: calc(100vh - var(--topbar-h)); overflow-y: auto;
                box-shadow: 0 8px 18px rgba(0,0,0,0.5);
            }
            .topbar-nav.open { transform: translateY(0); }
            .topbar-nav a {
                padding: 14px 18px; font-size: 15px; border-radius: 0;
                border-bottom: 1px solid #1c1c1c;
            }
            .topbar-user .who { display: none; }
            .topbar-user .logout-text { display: none; }
            .topbar-user .logout {
                padding: 8px 10px; min-width: 40px; justify-content: center;
            }
        }

        @media (max-width: 380px) {
            .topbar-brand .name { display: none; }
        }

        .nav-backdrop {
            position: fixed; inset: var(--topbar-h) 0 0 0;
            background: rgba(0,0,0,0.45); z-index: 1400; display: none;
        }
        .nav-backdrop.open { display: block; }

        .flash {
            background: #1f3a1f; border-left: 3px solid #10b981;
            padding: 10px 14px; border-radius: 4px; color: #a7f3d0;
            font-size: 13px; margin-bottom: 14px;
        }

        @yield('layout_styles')
    </style>

    @yield('head')
</head>
<body class="{{ $bodyClass }}">
    @unless($hideTopbar)
        <header class="topbar">
            <a class="topbar-brand" href="{{ route('map') }}">
                <svg class="logo" viewBox="0 0 512 512" aria-hidden="true">
                    <rect width="512" height="512" rx="96" fill="#0f172a"/>
                    <circle cx="256" cy="256" r="180" fill="none" stroke="#2563eb" stroke-width="14" opacity="0.6"/>
                    <circle cx="256" cy="256" r="110" fill="none" stroke="#2563eb" stroke-width="14" opacity="0.85"/>
                    <circle cx="256" cy="256" r="40" fill="#ef4444"/>
                </svg>
                <span class="name">K9 SAR</span>
            </a>
            <nav class="topbar-nav" id="topbar-nav">
                <a href="{{ route('map') }}" class="{{ request()->routeIs('map') ? 'active' : '' }}">🗺 Mapa</a>
                <a href="{{ route('sessions.index') }}" class="{{ request()->routeIs('sessions.*') ? 'active' : '' }}">📋 Operativos</a>
            </nav>
            @auth
                <div class="topbar-user">
                    <span class="who"><b>{{ auth()->user()->name }}</b> · {{ auth()->user()->role }}</span>
                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                        @csrf
                        <button class="logout" type="submit" aria-label="Cerrar sesión">
                            <span class="logout-icon">⎋</span><span class="logout-text">Salir</span>
                        </button>
                    </form>
                </div>
            @endauth
            <button class="topbar-toggle" id="topbar-toggle" aria-label="Abrir menú" aria-expanded="false">☰</button>
        </header>
        <div class="nav-backdrop" id="nav-backdrop"></div>
    @endunless

    @yield('content')

    <script>
        (function () {
            const toggle   = document.getElementById('topbar-toggle');
            const nav      = document.getElementById('topbar-nav');
            const backdrop = document.getElementById('nav-backdrop');
            const logoutForm = document.getElementById('logout-form');

            function closeNav() {
                if (!nav) return;
                nav.classList.remove('open');
                backdrop && backdrop.classList.remove('open');
                toggle && toggle.setAttribute('aria-expanded', 'false');
            }
            function openNav() {
                nav.classList.add('open');
                backdrop && backdrop.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
            }

            if (toggle && nav) {
                toggle.addEventListener('click', () => {
                    nav.classList.contains('open') ? closeNav() : openNav();
                });
                backdrop && backdrop.addEventListener('click', closeNav);
                nav.addEventListener('click', (e) => {
                    if (e.target.tagName === 'A') closeNav();
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeNav();
                });
            }

            // Antes de hacer logout: limpiar caches del SW para que la siguiente
            // navegación no muestre la versión vieja autenticada de '/'.
            if (logoutForm) {
                logoutForm.addEventListener('submit', async (e) => {
                    if (!window.caches) return; // browser sin Cache Storage: dejar pasar
                    e.preventDefault();
                    try {
                        const names = await caches.keys();
                        await Promise.all(names.map((n) => caches.delete(n)));
                        if (navigator.serviceWorker?.controller) {
                            navigator.serviceWorker.controller.postMessage({ type: 'logout' });
                        }
                    } catch (err) {
                        console.warn('cache cleanup failed', err);
                    } finally {
                        logoutForm.submit();
                    }
                });
            }

            // Registro del service worker para PWA (sólo HTTPS o localhost).
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js', { scope: '/' })
                        .catch((e) => console.warn('SW registration failed:', e));
                });
            }
        })();
    </script>

    @yield('scripts')
</body>
</html>
