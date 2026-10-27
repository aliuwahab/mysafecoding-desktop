{{-- Shared branded layout for error pages. Self-contained: works even if the asset build is missing. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') · CheckMyCode</title>
    @include('partials.favicons')
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root {
            --font-display: "Instrument Serif", Georgia, serif;
            --font-sans: "Inter Tight", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-mono: "JetBrains Mono", ui-monospace, Menlo, monospace;
            --accent: #f53003; --accent-hi: #ff6a48; --ok: #3dd68c;
            --bg-0: #0e0c0b; --bg-2: #1a1715; --line-1: #2b2623;
            --fg-1: #f5efe7; --fg-2: #bfb5a8; --fg-3: #867d71;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; background: var(--bg-0); color: var(--fg-2); font-family: var(--font-sans); -webkit-font-smoothing: antialiased; display: flex; flex-direction: column; }
        header, footer { padding: 0 20px; }
        header { height: 56px; display: flex; align-items: center; border-bottom: 1px solid var(--line-1); }
        .brand { display: flex; align-items: center; gap: 9px; color: var(--fg-1); text-decoration: none; font-family: var(--font-display); font-size: 21px; }
        main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .card { max-width: 560px; width: 100%; text-align: center; }
        .code { font-family: var(--font-display); font-size: 120px; line-height: 0.9; color: var(--accent); letter-spacing: -0.04em; }
        h1 { font-family: var(--font-display); font-weight: 400; color: var(--fg-1); font-size: 34px; margin: 14px 0 10px; letter-spacing: -0.01em; }
        p { font-size: 15px; line-height: 1.65; margin: 0 auto 10px; max-width: 470px; }
        .note { display: inline-flex; align-items: center; gap: 8px; margin-top: 10px; padding: 9px 14px; font-size: 13px; color: var(--fg-2); background: color-mix(in srgb, var(--ok) 7%, var(--bg-2)); border: 1px solid color-mix(in srgb, var(--ok) 25%, var(--line-1)); border-radius: 9px; text-align: left; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
        .btn { display: inline-flex; align-items: center; height: 38px; padding: 0 18px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-ghost { color: var(--fg-2); border: 1px solid var(--line-1); }
        footer { height: 52px; display: flex; align-items: center; justify-content: center; gap: 10px; border-top: 1px solid var(--line-1); font-size: 12.5px; color: var(--fg-3); }
        footer a { color: var(--fg-2); text-decoration: none; }
        code { font-family: var(--font-mono); font-size: 13px; color: var(--fg-1); }
    </style>
</head>
<body>
<header>
    <a class="brand" href="{{ url('/') }}">
        <img src="{{ asset('logo.svg') }}" width="24" height="24" alt="">
        CheckMyCode
    </a>
</header>
<main>
    <div class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        @yield('message')
        @hasSection('note')
            <div class="note">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3dd68c" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <span>@yield('note')</span>
            </div>
        @endif
        <div class="actions">
            @section('actions')
                <a class="btn btn-primary" href="{{ url('/') }}">Back to the scanner</a>
            @show
        </div>
    </div>
</main>
<footer>
    <span>Free and open source</span><span>·</span>
    @if (Route::has('terms'))<a href="{{ route('terms') }}">Terms</a><span>·</span>@endif
    <a href="{{ config('checkmycode.github_url', 'https://github.com/aliuwahab') }}" rel="noopener">GitHub</a>
</footer>
</body>
</html>
