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
            --topbar-h: 52px;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; background: var(--bg); color: var(--text);
            font-family: -apple-system, Segoe UI, Roboto, sans-serif;
            -webkit-text-size-adjust: 100%;
        }
        body { min-height: 100vh; }
        body.fixed-viewport {
            height: 100vh; height: 100dvh; overflow: hidden;
        }
        a { color: var(--accent); }

        .topbar {
            position: sticky; top: 0; z-index: 1500;
            background: #111; border-bottom: 1px solid var(--border);
            height: var(--topbar-h); display: flex; align-items: center;
            padding: 0 12px; padding-top: env(safe-area-inset-top);
            gap: 8px;
        }
        .topbar-brand {
            font-weight: 700; color: var(--text); text-decoration: none; font-size: 14px;
            letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;
            white-space: nowrap;
        }
        .topbar-brand .logo { width: 26px; height: 26px; flex-shrink: 0; }
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
        .topbar-spacer { flex: 1; }

        .topbar-user {
            display: flex; align-items: center; gap: 10px; color: var(--text-muted); font-size: 12px;
        }
        .topbar-user form { margin: 0; }
        .topbar-user .who { white-space: nowrap; }
        .topbar-user .who b { color: var(--text); }
        .topbar-user .logout {
            background: none; border: 1px solid #333; color: var(--accent);
            padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;
            font-family: inherit;
        }
        .topbar-user .logout:hover { background: #222; }

        .topbar-toggle {
            background: none; border: 1px solid #333; color: var(--text);
            width: 38px; height: 38px; border-radius: 4px; cursor: pointer; display: none;
            font-size: 18px; line-height: 1; align-items: center; justify-content: center;
        }

        @media (max-width: 820px) {
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
            .topbar-spacer { display: none; }
            .topbar-user {
                padding: 12px 18px; flex-direction: row; align-items: center;
                justify-content: space-between; gap: 10px; flex-wrap: wrap;
                border-top: 1px solid #1c1c1c;
            }
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
            <div class="topbar-spacer"></div>
            <button class="topbar-toggle" id="topbar-toggle" aria-label="Abrir menú" aria-expanded="false">☰</button>
            <nav class="topbar-nav" id="topbar-nav">
                <a href="{{ route('map') }}" class="{{ request()->routeIs('map') ? 'active' : '' }}">🗺 Mapa</a>
                <a href="{{ route('sessions.index') }}" class="{{ request()->routeIs('sessions.*') ? 'active' : '' }}">📋 Operativos</a>
                <a href="{{ route('field') }}" class="{{ request()->routeIs('field') ? 'active' : '' }}">📡 Campo</a>
                @auth
                    <div class="topbar-spacer"></div>
                    <div class="topbar-user">
                        <span class="who"><b>{{ auth()->user()->name }}</b> · {{ auth()->user()->role }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="logout" type="submit">Salir</button>
                        </form>
                    </div>
                @endauth
            </nav>
        </header>
        <div class="nav-backdrop" id="nav-backdrop"></div>
    @endunless

    @yield('content')

    <script>
        (function () {
            const toggle  = document.getElementById('topbar-toggle');
            const nav     = document.getElementById('topbar-nav');
            const backdrop = document.getElementById('nav-backdrop');

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
