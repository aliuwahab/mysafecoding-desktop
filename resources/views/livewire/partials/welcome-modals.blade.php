{{-- Web mode: first-visit pitch and "How it works". State lives in the webScanner Alpine component. --}}
@php
    $lockIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
    $features = [
        ['Vulnerable dependencies', 'Every lockfile checked against public CVE databases, with the exact upgrade that fixes it.', 'var(--sev-low)', '<path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/><path d="m3 7 9-5 9 5v10l-9 5-9-5V7Z"/>'],
        ['Insecure code', 'SQL injection, XSS, unsafe deserialization and more, with rules for your framework.', '#c69dff', '<path d="m8 6-6 6 6 6M16 6l6 6-6 6M14 4 10 20"/>'],
        ['Leaked secrets', 'API keys, tokens and passwords committed by accident.', 'var(--sev-critical)', '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M16 8l3 3M14 10l3 3"/>'],
        ['Risky config & CI', 'Dockerfile, Kubernetes, Terraform and GitHub Actions workflows.', 'var(--sev-medium)', '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M6 8.5v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2M12 12.5v3"/>'],
        ['End-of-life software', 'Runtimes and frameworks that no longer get security fixes.', 'var(--sev-high)', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'],
        ['Fix it with AI', 'One click copies a prompt for Claude Code, Cursor or Copilot to fix it safely.', 'var(--accent-hi)', '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/>'],
    ];
    $stacks = ['Laravel', 'PHP', 'Next.js', 'React', 'Vue', 'Node', 'Django', 'FastAPI', 'Rails', 'Go', 'Rust', 'Spring', '.NET', 'Phoenix'];
    $steps = [
        ['Pick a folder', 'Choose a project folder or file, or drag it onto the page. Your browser skips <span class="mono">node_modules</span>, <span class="mono">vendor</span>, <span class="mono">.git</span> and other excluded folders, so they never leave your machine.'],
        ['Open-source engines scan it', 'The rest is sent over HTTPS to a temporary workspace and checked by <strong>Trivy</strong> and <strong>OSV-Scanner</strong> (dependencies), <strong>Semgrep</strong> (code), <strong>Gitleaks</strong> (secrets), <strong>zizmor</strong> (GitHub Actions), endoflife.date and CheckMyCode\'s own rules. Then the workspace is deleted.'],
        ['Get a graded report', 'An A–F security grade and a tab per area: each finding with its file and line, severity, and how to fix it. Upgrades are labelled patch, minor or major so you know what\'s safe.'],
        ['Fix it with your coding agent', 'Every tab has <strong>Copy fix prompt</strong>: paste it into Claude Code, Cursor or Copilot and it fixes the issues without changing existing behaviour, flagging anything risky for review.'],
    ];
@endphp

{{-- ── Welcome (first visit) ─────────────────────────────────────────── --}}
<div x-show="showWelcome" x-cloak>
<div @keydown.escape.window="dismissWelcome()" @click.self="dismissWelcome()"
     class="m-modal-wrap" style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(5,4,3,0.72);backdrop-filter:blur(6px);">
    <div role="dialog" aria-modal="true" aria-labelledby="welcome-title"
         style="position:relative;width:760px;max-width:100%;max-height:calc(100vh - 48px);overflow:auto;background:var(--bg-1);border:1px solid var(--line-1);border-radius:18px;box-shadow:0 30px 90px rgba(0,0,0,0.55);" class="scroll">
        <div style="position:absolute;top:-120px;left:50%;transform:translateX(-50%);width:520px;height:260px;border-radius:50%;background:radial-gradient(closest-side,color-mix(in srgb,var(--accent) 28%,transparent),transparent);pointer-events:none;"></div>
        <button type="button" @click="dismissWelcome()" aria-label="Close" style="position:absolute;top:14px;right:16px;color:var(--fg-3);font-size:20px;line-height:1;z-index:1;">×</button>

        <div class="m-hero" style="position:relative;padding:40px 44px 28px;text-align:center;">
            <div style="display:inline-flex;align-items:center;gap:8px;padding:5px 12px;border-radius:999px;border:1px solid var(--line-1);background:var(--bg-2);font-size:11.5px;color:var(--fg-2);margin-bottom:22px;">
                <span style="width:6px;height:6px;border-radius:50%;background:var(--sev-ok);display:block;"></span>
                Free &amp; open source · No account needed
            </div>
            <h2 id="welcome-title" class="serif m-hero-title" style="margin:0;font-size:46px;line-height:1.05;font-weight:400;color:var(--fg-1);letter-spacing:-0.02em;">
                Find security issues<br><span style="color:var(--accent);">before you ship.</span>
            </h2>
            <p style="margin:16px auto 0;max-width:540px;font-size:15px;line-height:1.6;color:var(--fg-2);">
                Drop in a project folder and get one clear report on vulnerable dependencies, insecure code, leaked secrets, risky config and end-of-life software, in about a minute.
            </p>
        </div>

        <div class="m-grid1" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:0 32px;">
            @foreach ($features as [$title, $text, $color, $icon])
                <div style="padding:14px 15px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:12px;">
                    <div style="width:28px;height:28px;border-radius:8px;display:grid;place-items:center;margin-bottom:10px;background:color-mix(in srgb,{{ $color }} 14%,transparent);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="color:{{ $color }};">{!! $icon !!}</svg>
                    </div>
                    <div style="font-size:13px;font-weight:600;color:var(--fg-1);margin-bottom:4px;">{{ $title }}</div>
                    <div style="font-size:12px;line-height:1.5;color:var(--fg-3);">{{ $text }}</div>
                </div>
            @endforeach
        </div>

        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:6px;padding:20px 32px 0;">
            @foreach ($stacks as $stack)
                <span class="mono" style="font-size:10.5px;padding:3px 8px;border-radius:5px;background:var(--bg-2);border:1px solid var(--line-1);color:var(--fg-2);">{{ $stack }}</span>
            @endforeach
        </div>

        <div style="margin:22px 32px 0;padding:12px 16px;display:flex;align-items:center;gap:10px;border-radius:10px;background:color-mix(in srgb,var(--sev-ok) 7%,var(--bg-2));border:1px solid color-mix(in srgb,var(--sev-ok) 24%,var(--line-1));font-size:12.5px;color:var(--fg-2);">
            <span style="color:var(--sev-ok);display:flex;">{!! $lockIcon !!}</span>
            <span><strong style="color:var(--fg-1);">Nothing stored.</strong> Your files are scanned in a temporary workspace and deleted as soon as the scan finishes. No account, no tracking.</span>
        </div>

        <div style="display:flex;justify-content:center;gap:10px;padding:26px 32px 14px;flex-wrap:wrap;">
            <button type="button" @click="dismissWelcome(); pickFolder()"
                    style="display:inline-flex;align-items:center;gap:8px;height:42px;padding:0 22px;border-radius:10px;background:var(--accent);color:#fff;font-size:14px;font-weight:600;box-shadow:0 1px 0 rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.15);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2H3"/><path d="m3 9 1.5 9a2 2 0 0 0 2 2H19a2 2 0 0 0 2-2l1-7H5"/></svg>
                Scan a project
            </button>
            <button type="button" @click="dismissWelcome(); showHowItWorks = true"
                    style="display:inline-flex;align-items:center;gap:6px;height:42px;padding:0 20px;border-radius:10px;border:1px solid var(--line-1);background:var(--bg-2);color:var(--fg-1);font-size:14px;font-weight:600;">
                How it works <span aria-hidden="true">→</span>
            </button>
        </div>
        <div style="text-align:center;padding:0 32px 26px;font-size:11.5px;color:var(--fg-3);">
            MIT licensed · <a href="{{ config('checkmycode.github_url') }}" target="_blank" rel="noopener" style="color:var(--fg-2);">View the source on GitHub</a> · <a href="{{ route('terms') }}" target="_blank" style="color:var(--fg-2);">Terms</a>
        </div>
    </div>
</div>
</div>

{{-- ── How it works ──────────────────────────────────────────────────── --}}
<div x-show="showHowItWorks" x-cloak>
<div @keydown.escape.window="showHowItWorks = false" @click.self="showHowItWorks = false"
     class="m-modal-wrap" style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(5,4,3,0.72);backdrop-filter:blur(6px);">
    <div role="dialog" aria-modal="true" aria-labelledby="how-title" class="scroll"
         style="position:relative;width:700px;max-width:100%;max-height:calc(100vh - 48px);overflow:auto;background:var(--bg-1);border:1px solid var(--line-1);border-radius:18px;box-shadow:0 30px 90px rgba(0,0,0,0.55);">
        <button type="button" @click="showHowItWorks = false" aria-label="Close" style="position:absolute;top:14px;right:16px;color:var(--fg-3);font-size:20px;line-height:1;">×</button>
        <div class="m-pad" style="padding:34px 40px 8px;">
            <div style="font-size:10.5px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--accent-hi);margin-bottom:8px;">How it works</div>
            <h2 id="how-title" class="serif" style="margin:0 0 6px;font-size:32px;font-weight:400;color:var(--fg-1);letter-spacing:-0.015em;">From folder to fixes in four steps</h2>
            <p style="margin:0;font-size:13.5px;color:var(--fg-3);">The same scanner runs here, in the desktop app, in the CLI and in CI.</p>
        </div>

        <ol class="m-pad" style="list-style:none;margin:0;padding:18px 40px 4px;display:flex;flex-direction:column;gap:0;">
            @foreach ($steps as $i => [$title, $text])
                <li style="display:grid;grid-template-columns:36px 1fr;gap:16px;">
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <div class="serif" style="width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:18px;color:var(--accent);background:color-mix(in srgb,var(--accent) 12%,transparent);border:1px solid color-mix(in srgb,var(--accent) 30%,transparent);">{{ $i + 1 }}</div>
                        @unless ($loop->last)<div style="flex:1;width:1px;background:var(--line-1);margin:6px 0;"></div>@endunless
                    </div>
                    <div style="padding:5px 0 20px;">
                        <div style="font-size:14.5px;font-weight:600;color:var(--fg-1);margin-bottom:4px;">{{ $title }}</div>
                        <div style="font-size:13px;line-height:1.6;color:var(--fg-2);">{!! $text !!}</div>
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="m-inset" style="margin:0 40px;padding:14px 16px;border-radius:10px;background:var(--bg-2);border:1px solid var(--line-1);font-size:12.5px;line-height:1.6;color:var(--fg-2);">
            <strong style="color:var(--fg-1);">Want nothing to leave your machine?</strong>
            Run the same scanner locally with the desktop app or <span class="mono" style="color:var(--fg-1);">docker run --rm -v "$PWD:/src" ghcr.io/aliuwahab/checkmycode scan /src</span>, add it to CI, or self-host this site. It's all open source.
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:22px 40px 28px;flex-wrap:wrap;" class="m-pad">
            <div style="display:flex;gap:14px;font-size:12.5px;">
                <button type="button" @click="showHowItWorks = false; showPrivacy = true" style="color:var(--sev-ok);text-decoration:underline;text-underline-offset:2px;">What happens to my code?</button>
                <a href="{{ config('checkmycode.github_url') }}" target="_blank" rel="noopener" style="color:var(--fg-2);">Source on GitHub</a>
            </div>
            <button type="button" @click="showHowItWorks = false; pickFolder()"
                    style="display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 20px;border-radius:10px;background:var(--accent);color:#fff;font-size:13.5px;font-weight:600;">
                Scan a project
            </button>
        </div>
    </div>
</div>
</div>
