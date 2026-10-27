<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('checkmycode.mode') === 'web' ? 'CheckMyCode — Open-Source Security Scanner' : 'CheckMyCode — Local Security Scanner' }}</title>
    @include('partials.favicons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        :root {
            --font-display: "Instrument Serif", "Iowan Old Style", Georgia, serif;
            --font-sans: "Inter Tight", "SF Pro Text", -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: "JetBrains Mono", "SF Mono", ui-monospace, Menlo, monospace;
            --accent: #f53003;
            --accent-hi: #ff6a48;
            --accent-lo: #bb1f00;
            --sev-critical: #ff4d4d;
            --sev-high: #ff8a3d;
            --sev-medium: #f5c542;
            --sev-low: #6fb3ff;
            --sev-info: #8b93a1;
            --sev-ok: #3dd68c;
            --bg-0: #0e0c0b;
            --bg-1: #141210;
            --bg-2: #1a1715;
            --bg-3: #241f1c;
            --line-1: #2b2623;
            --line-2: #20201d;
            --fg-1: #f5efe7;
            --fg-2: #bfb5a8;
            --fg-3: #867d71;
            --fg-4: #4f4a43;
            --log-bg: #0a0908;
            --grade-a: #3dd68c;
            --grade-b: #6fb3ff;
            --grade-c: #f5c542;
            --grade-d: #ff8a3d;
            --grade-f: #ff4d4d;
        }
        * { box-sizing: border-box; }
        [x-cloak] { display: none !important; }
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; }
        body {
            font-family: var(--font-sans);
            font-feature-settings: "cv11", "ss01", "ss03";
            background: var(--bg-0);
            color: var(--fg-1);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        button { font: inherit; color: inherit; background: none; border: 0; cursor: pointer; padding: 0; }
        input { font: inherit; color: inherit; }
        pre, code { font-family: var(--font-mono); }
        .scroll::-webkit-scrollbar { width: 10px; height: 10px; }
        .scroll::-webkit-scrollbar-track { background: transparent; }
        .scroll::-webkit-scrollbar-thumb { background: var(--line-1); border-radius: 10px; border: 2px solid transparent; background-clip: padding-box; }
        .scroll::-webkit-scrollbar-thumb:hover { background: var(--fg-4); background-clip: padding-box; border: 2px solid transparent; }
        .mono { font-family: var(--font-mono); font-feature-settings: "zero", "ss02"; }
        .serif { font-family: var(--font-display); font-feature-settings: "liga"; }
        .kbd {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 4px; border: 1px solid var(--line-1);
            background: var(--bg-2); color: var(--fg-3);
            font-family: var(--font-mono); font-size: 10.5px; line-height: 1;
        }
        .traffic { display: flex; gap: 8px; align-items: center; }
        .traffic i { width: 12px; height: 12px; border-radius: 50%; display: block; border: 0.5px solid rgba(0,0,0,0.2); box-shadow: inset 0 0 0 0.5px rgba(255,255,255,0.08); }
        .traffic .c { background: #ff5f57; }
        .traffic .m { background: #febc2e; }
        .traffic .x { background: #28c840; }
        @keyframes pulse-dot { 0%, 100% { opacity: 0.4; transform: scale(1); } 50% { opacity: 1; transform: scale(1.15); } }
        .pulse-dot { animation: pulse-dot 1.6s ease-in-out infinite; }
        @keyframes log-in { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: translateY(0); } }
        .log-in { animation: log-in 180ms ease-out both; }
        @keyframes sweep { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .sweep {
            position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(245,48,3,0) 20%, rgba(245,48,3,0.65) 50%, rgba(245,48,3,0) 80%, transparent 100%);
            animation: sweep 1.6s ease-in-out infinite;
        }
        .tab-active-bar { position: absolute; bottom: -1px; left: 8px; right: 8px; height: 1.5px; background: var(--accent); }

        /* ── Small screens: drawer sidebar, wrapping toolbars, stacked cards, sideways-scrolling tables ── */
        .m-menu { display: none !important; }
        .m-backdrop { display: none; }
        @media (max-width: 860px) {
            .app-root { height: 100dvh !important; }
            .m-menu { display: inline-flex !important; }
            .m-titlebar { grid-template-columns: auto minmax(0, 1fr) auto !important; padding: 0 10px !important; gap: 8px !important; }
            .m-crumb { min-width: 0 !important; max-width: none !important; }
            .m-sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 55; height: 100% !important; width: 280px !important; max-width: 85vw; transform: translateX(-100%); transition: transform 220ms ease; box-shadow: 0 0 60px rgba(0,0,0,0.5); }
            .nav-open .m-sidebar { transform: none; }
            .nav-open .m-backdrop { display: block; position: fixed; inset: 0; z-index: 54; background: rgba(0,0,0,0.55); }
            .m-configbar { flex-wrap: wrap; padding: 10px 12px !important; gap: 8px !important; }
            .m-pick span { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .m-configbar > button:last-child { margin-left: auto; }
            .m-exclusions { order: 5; flex-basis: 100% !important; overflow-x: auto !important; scrollbar-width: none; }
            .m-privacy { flex-wrap: wrap; padding: 8px 12px !important; row-gap: 4px !important; }
            .m-privacy > span[style*="flex:1"] { display: none; }
            .m-logrow { grid-template-columns: 76px 1fr !important; gap: 10px !important; padding: 2px 12px !important; }
            .m-hide { display: none !important; }
            .m-tabhead { flex-wrap: wrap; padding: 16px 16px 12px !important; row-gap: 6px !important; }
            .m-pad { padding-left: 16px !important; padding-right: 16px !important; }
            .m-stack { grid-template-columns: 1fr !important; }
            .m-sevrow { grid-template-columns: 110px 1fr 32px !important; gap: 8px !important; }
            .m-grid2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; word-break: break-all; }
            .m-grid1 { grid-template-columns: 1fr !important; padding: 0 16px !important; }
            .m-table { overflow-x: auto !important; }
            .m-table > * { min-width: var(--table-min, 800px); }
            .m-hero { padding: 36px 20px 22px !important; }
            .m-hero-title { font-size: 34px !important; }
            .m-modal-wrap { padding: 12px !important; }
            .m-inset { margin: 0 16px !important; }
        }
    </style>
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
