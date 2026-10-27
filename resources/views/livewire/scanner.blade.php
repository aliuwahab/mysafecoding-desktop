<div class="app-root" style="width:100vw;height:100vh;background:var(--bg-0);color:var(--fg-1);display:flex;flex-direction:column;overflow:hidden;position:relative;"
    @if ($webMode) x-data="webScanner(@js(['exclusions' => $exclusions] + $webLimits))" @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="dropped($event)" @endif>

@if ($status === 'running')
    <div wire:poll.750ms="pollLogs" style="display:none;"></div>
@endif

{{-- ── Title Bar ──────────────────────────────────────────────────── --}}
<div style="height:44px;flex-shrink:0;background:var(--bg-0);border-bottom:1px solid var(--line-1);display:grid;grid-template-columns:1fr auto 1fr;align-items:center;padding:0 14px;gap:12px;user-select:none;" class="drag m-titlebar" data-tauri-drag-region>

    {{-- Left: traffic lights + sidebar toggle --}}
    <div style="display:flex;align-items:center;gap:14px;">
        <button type="button" class="m-menu" aria-label="Menu" onclick="document.documentElement.classList.toggle('nav-open')" style="width:32px;height:32px;align-items:center;justify-content:center;border:1px solid var(--line-1);border-radius:7px;background:var(--bg-2);color:var(--fg-2);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        @unless ($webMode)
        <div class="traffic" style="-webkit-app-region:no-drag;">
            <i class="c"></i><i class="m"></i><i class="x"></i>
        </div>
        @endunless
    </div>

    {{-- Center: breadcrumb --}}
    <div class="m-crumb" style="display:flex;align-items:center;gap:8px;padding:5px 10px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;min-width:280px;max-width:480px;font-size:12px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);flex-shrink:0;"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
        <span class="mono" style="color:var(--fg-2);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            {{ $projectPath ?: ($webMode ? 'Pick a folder or drop it here…' : '~/Code/…') }}
        </span>
        @php
            $projectType = $results['project_type'] ?? null;
            $projectLabel = $projectType ? \App\Scanning\ProjectDetector::label($projectType) : ($projectPath ? 'Project' : null);
        @endphp
        @if ($projectPath && $projectLabel)
            <span style="font-size:10px;font-weight:600;color:var(--accent);padding:1px 6px;border-radius:4px;background:color-mix(in srgb,var(--accent) 14%,transparent);letter-spacing:0.04em;">{{ $projectLabel }}</span>
        @elseif ($projectPath)
            <span style="font-size:10px;font-weight:600;color:var(--accent);padding:1px 6px;border-radius:4px;background:color-mix(in srgb,var(--accent) 14%,transparent);letter-spacing:0.04em;">Selected</span>
        @endif
    </div>

    {{-- Right: status --}}
    <div style="display:flex;align-items:center;gap:10px;justify-content:flex-end;">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--fg-2);">
            @if ($status === 'running')
                <span class="pulse-dot" style="width:7px;height:7px;border-radius:50%;background:var(--accent);display:block;"></span>
                <span>Scanning</span>
            @elseif ($status === 'completed')
                <span style="width:7px;height:7px;border-radius:50%;background:var(--sev-ok);display:block;"></span>
                <span>Complete</span>
            @elseif ($status === 'failed')
                <span style="width:7px;height:7px;border-radius:50%;background:var(--sev-critical);display:block;"></span>
                <span>Failed</span>
            @else
                <span style="width:7px;height:7px;border-radius:50%;background:var(--fg-4);display:block;"></span>
                <span>Ready</span>
            @endif
        </div>
    </div>
</div>

{{-- ── Body row ─────────────────────────────────────────────────────── --}}
<div style="flex:1;display:flex;min-height:0;">

{{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
<div class="m-backdrop" onclick="document.documentElement.classList.remove('nav-open')"></div>
<aside class="m-sidebar" onclick="if (event.target.closest('button, a, [wire\\:click]')) document.documentElement.classList.remove('nav-open')" style="width:248px;flex-shrink:0;background:var(--bg-0);border-right:1px solid var(--line-1);display:flex;flex-direction:column;height:100%;overflow:hidden;">

    {{-- Brand --}}
    <div style="padding:16px 16px 10px;display:flex;align-items:center;gap:9px;">
        <img src="{{ asset('logo.svg') }}" width="28" height="28" alt="" style="flex-shrink:0;">
        <div style="display:flex;flex-direction:column;line-height:1;">
            <span class="serif" style="font-size:19px;color:var(--fg-1);letter-spacing:-0.01em;">CheckMyCode</span>
            <span style="font-size:10.5px;color:var(--fg-3);margin-top:2px;letter-spacing:0.04em;">{{ $webMode ? 'open-source web scanner' : 'v2.0 · multi-stack local scanner' }}</span>
        </div>
    </div>

    {{-- CTAs --}}
    <div style="padding:4px 12px 12px;display:flex;flex-direction:column;gap:6px;">
        <button wire:click="newScan" style="height:32px;padding:0 12px;display:flex;align-items:center;justify-content:space-between;gap:8px;background:var(--accent);color:#fff;border-radius:7px;font-weight:600;font-size:12.5px;box-shadow:0 1px 0 rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.15);">
            <span style="display:flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                New scan
            </span>
        </button>
        {{-- Free and open source: point people at the repo and sponsorship --}}
        @php
            $osLinks = [
                ['github', 'Star on GitHub', config('checkmycode.github_url'), 'var(--fg-2)', '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.9a3.4 3.4 0 0 0-.9-2.6c3-.3 6.2-1.5 6.2-6.7a5.2 5.2 0 0 0-1.4-3.6 4.8 4.8 0 0 0-.1-3.6s-1.2-.3-3.8 1.4a13 13 0 0 0-7 0C6.3 1.3 5.1 1.6 5.1 1.6a4.8 4.8 0 0 0-.1 3.6A5.2 5.2 0 0 0 3.6 8.8c0 5.2 3.2 6.4 6.2 6.7a3.4 3.4 0 0 0-.9 2.6V22"/>'],
                ['sponsor', 'Sponsor', config('checkmycode.sponsor_url'), '#ff6b9a', '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/>'],
            ];
        @endphp
        <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:6px;">
            @foreach ($osLinks as [$key, $label, $url, $color, $icon])
                @php $linkStyle = 'height:30px;padding:0 6px;display:flex;align-items:center;gap:5px;white-space:nowrap;color:var(--fg-2);border:1px dashed var(--line-1);border-radius:7px;font-size:11.5px;justify-content:center;text-decoration:none;'; @endphp
                @if ($webMode)
                    <a href="{{ $url }}" target="_blank" rel="noopener" style="{{ $linkStyle }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="color:{{ $color }};">{!! $icon !!}</svg>{{ $label }}
                    </a>
                @else
                    <button wire:click="openLink('{{ $key }}')" style="{{ $linkStyle }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="color:{{ $color }};">{!! $icon !!}</svg>{{ $label }}
                    </button>
                @endif
            @endforeach
        </div>
    </div>

    @if ($webMode)
    {{-- Community scan counter: real completed scans by everyone on this site --}}
    @php $stats = $this->scanStats; @endphp
    <div style="margin:0 12px 12px;padding:12px 14px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;" title="Scans run on CheckMyCode today. Only the count is stored, never the code.">
        <div style="font-size:9.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);margin-bottom:8px;">Scans by the community</div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="pulse-dot" style="width:7px;height:7px;border-radius:50%;background:var(--sev-ok);display:block;flex-shrink:0;"></span>
            <div>
                <div class="serif" style="font-size:28px;line-height:1;color:var(--fg-1);">{{ number_format($stats['today']) }}</div>
                <div style="font-size:10.5px;color:var(--fg-3);margin-top:3px;">{{ Str::plural('project', $stats['today']) }} scanned today</div>
            </div>
        </div>
    </div>
    @endif

    {{-- History list --}}
    <div class="scroll" style="flex:1;overflow-y:auto;padding:0 8px 12px;">
        @php
            $allScans = $this->history;
            $today = now()->startOfDay();
            $grouped = ['Today' => [], 'Earlier this week' => [], 'Older' => []];
            foreach ($allScans as $s) {
                if ($s->createdAt->gte($today)) $grouped['Today'][] = $s;
                elseif ($s->createdAt->gte($today->copy()->subDays(5))) $grouped['Earlier this week'][] = $s;
                else $grouped['Older'][] = $s;
            }
        @endphp
        @if ($webMode && $allScans->isEmpty())
            <div style="padding:10px 12px;font-size:11px;line-height:1.55;color:var(--fg-3);">
                Scans from this browser session appear here for {{ $webLimits['ttlMinutes'] }} minutes, then they are gone. Export JSON or SARIF to keep a copy.
            </div>
        @endif
        @foreach ($grouped as $label => $items)
            @if (count($items) > 0)
                <div style="margin-bottom:10px;">
                    <div style="padding:6px 10px 4px;font-size:9.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);">{{ $label }}</div>
                    @foreach ($items as $scan)
                        @php
                            $sr = $scan->results['summary'] ?? [];
                            $sg = $scan->status === 'completed' ? ($sr['grade'] ?? \App\Scanning\ScanGrade::fromSummary($sr)) : '-';
                            $gradeVars = ['A'=>'--grade-a','B'=>'--grade-b','C'=>'--grade-c','D'=>'--grade-d','F'=>'--grade-f','-'=>'--fg-4'];
                            $gc = 'var(' . ($gradeVars[$sg] ?? '--fg-4') . ')';
                            $dotColor = match($scan->status) { 'completed'=>'var(--sev-ok)', 'running'=>'var(--accent)', 'failed'=>'var(--sev-critical)', default=>'var(--fg-4)' };
                            $isSelected = $selectedHistoryId === $scan->id;
                        @endphp
                        <div wire:click="loadScan('{{ $scan->id }}')"
                             style="padding:8px 10px;border-radius:7px;background:{{ $isSelected ? 'var(--bg-3)' : 'transparent' }};cursor:pointer;position:relative;transition:background 120ms;"
                             onmouseenter="if(!{{ $isSelected ? 'true' : 'false' }})this.style.background='var(--bg-2)'"
                             onmouseleave="if(!{{ $isSelected ? 'true' : 'false' }})this.style.background='transparent'">
                            @if ($isSelected)
                                <div style="position:absolute;left:-8px;top:10px;bottom:10px;width:2px;border-radius:2px;background:var(--accent);"></div>
                            @endif
                            <div style="display:flex;align-items:center;gap:7px;">
                                <span style="width:5px;height:5px;border-radius:50%;background:{{ $dotColor }};flex-shrink:0;display:block;"></span>
                                <span class="mono" style="flex:1;font-size:11.5px;color:var(--fg-1);font-weight:{{ $isSelected ? '600' : '500' }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $scan->projectName }}</span>
                                <span style="width:18px;height:18px;border-radius:4px;display:grid;place-items:center;font-size:10.5px;font-weight:700;color:{{ $gc }};background:color-mix(in srgb,{{ $gc }} 14%,transparent);font-family:var(--font-mono);">{{ $sg }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:3px;padding-left:12px;">
                                <span style="font-size:10.5px;color:var(--fg-3);">{{ $scan->createdAt->diffForHumans() }}</span>
                                @if ($scan->findingsCount > 0)
                                    <span style="font-size:10.5px;color:var(--fg-3);">· {{ $scan->findingsCount }} findings</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>

    {{-- Footer --}}
    <div style="padding:10px 14px;border-top:1px solid var(--line-1);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:4px 8px;font-size:10.5px;color:var(--fg-3);white-space:nowrap;">
        @if ($webMode)
            <span style="display:flex;align-items:center;gap:8px;">
                <button type="button" @click="showHowItWorks = true" style="color:var(--fg-2);font-size:10.5px;">How it works</button>
                <span style="color:var(--fg-4);">·</span>
                <a href="{{ route('terms') }}" target="_blank" style="color:var(--fg-2);text-decoration:none;">Terms</a>
                <span style="color:var(--fg-4);">·</span>
                <a href="{{ route('terms') }}#9-license" target="_blank" style="color:var(--fg-2);text-decoration:none;">MIT License</a>
            </span>
            <span class="mono" title="Uploaded code is deleted as soon as the scan finishes.">no code stored</span>
        @else
            <span style="display:flex;align-items:center;gap:5px;">
                <span style="width:6px;height:6px;border-radius:50%;background:var(--sev-ok);display:block;"></span>
                All engines ready
            </span>
            <span class="mono" title="Scans run on this machine.">local</span>
        @endif
    </div>
</aside>

{{-- ── Main ──────────────────────────────────────────────────────────── --}}
<main style="flex:1;display:flex;flex-direction:column;min-width:0;background:var(--bg-1);">

@if ($showSetup)
{{-- ── Setup screen ──────────────────────────────────────────────── --}}
<div style="flex:1;display:flex;align-items:center;justify-content:center;padding:32px;">
    <div style="max-width:420px;width:100%;background:var(--bg-2);border-radius:14px;border:1px solid var(--line-1);padding:32px;">
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:48px;height:48px;border-radius:50%;background:color-mix(in srgb,var(--sev-medium) 14%,transparent);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-medium);"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
            </div>
            <h2 style="font-size:18px;font-weight:600;color:var(--fg-1);margin:0 0 8px;">Setup Required</h2>
            <p style="font-size:13px;color:var(--fg-3);margin:0;">{{ $webMode ? 'This server is missing scanner tools. Use the Docker image, which ships with all of them.' : 'CheckMyCode needs the following tools installed:' }}</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
            @foreach (['trivy', 'semgrep', 'gitleaks'] as $tool)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-3);border-radius:8px;">
                    <span class="mono" style="font-size:13px;color:var(--fg-1);">{{ $tool }}</span>
                    @if (in_array($tool, $missingTools))
                        <span style="font-size:11px;color:var(--sev-critical);">Missing</span>
                    @else
                        <span style="font-size:11px;color:var(--sev-ok);">Installed</span>
                    @endif
                </div>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;">
            @unless ($webMode)
            <button wire:click="installTools" style="flex:1;padding:9px 16px;background:var(--accent);color:#fff;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 1px 0 rgba(0,0,0,0.15);">Install via Homebrew</button>
            @endunless
            <button wire:click="recheckTools" style="padding:9px 14px;background:var(--bg-3);color:var(--fg-2);border-radius:8px;font-size:13px;border:1px solid var(--line-1);">Recheck</button>
        </div>
    </div>
</div>

@else
{{-- ── Scan Config Bar ───────────────────────────────────────────── --}}
<div class="m-configbar" style="padding:12px 20px;border-bottom:1px solid var(--line-1);background:var(--bg-1);display:flex;align-items:center;gap:12px;">
    @php $folderIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-hi);"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2H3"/><path d="m3 9 1.5 9a2 2 0 0 0 2 2H19a2 2 0 0 0 2-2l1-7H5"/></svg>'; @endphp
    @php $fileIcon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-2);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>'; @endphp
    @if ($webMode)
        <input type="file" x-ref="folderInput" webkitdirectory directory multiple style="display:none;" @change="pickedFolder($event)">
        <input type="file" x-ref="fileInput" multiple style="display:none;" @change="pickedFiles($event)">
        <button type="button" class="m-pick" @click="pickFolder()" :disabled="busy" style="display:flex;min-width:0;align-items:center;gap:8px;padding:6px 11px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;">
            {!! $folderIcon !!}
            <span class="mono" style="color:var(--fg-1);font-weight:500;">{{ $projectPath ?: 'Select Folder' }}</span>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <button type="button" @click="$refs.fileInput.click()" :disabled="busy" title="Scan individual files" style="display:flex;align-items:center;gap:6px;padding:6px 10px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;color:var(--fg-2);">
            {!! $fileIcon !!} File
        </button>
        <span x-show="files.length" x-cloak class="mono" style="font-size:10.5px;color:var(--fg-3);white-space:nowrap;" x-text="selectionSummary"></span>
    @else
        <button wire:click="selectFolder" style="display:flex;align-items:center;gap:8px;padding:6px 11px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;">
            {!! $folderIcon !!}
            <span class="mono" style="color:var(--fg-1);font-weight:500;">{{ $projectPath ? basename($projectPath) : 'Select Folder' }}</span>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <button wire:click="selectFile" title="Scan a single file" style="display:flex;align-items:center;gap:6px;padding:6px 10px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;color:var(--fg-2);">
            {!! $fileIcon !!} File
        </button>
    @endif

    {{-- Exclusions: a compact button; the panel holds the list, so it never runs off the bar. --}}
    <div class="m-exclusions" x-data="{ open: false }" @open-exclusions.window="open = true" @keydown.escape.window="open = false" style="flex:1;min-width:0;position:relative;">
        <button type="button" @click="open = !open" :aria-expanded="open" title="Folders skipped before scanning" style="display:inline-flex;align-items:center;gap:7px;padding:0 10px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;color:var(--fg-2);max-width:100%;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);flex-shrink:0;"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
            <span>Exclude</span>
            <span class="mono" style="font-size:10.5px;padding:1px 6px;border-radius:9px;background:var(--bg-3);color:var(--fg-1);">{{ count($exclusions) }}</span>
            <span class="mono m-hide" style="font-size:11px;color:var(--fg-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ implode(', ', array_slice($exclusions, 0, 4)) }}{{ count($exclusions) > 4 ? ', …' : '' }}</span>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);flex-shrink:0;"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.120ms
            style="position:absolute;top:calc(100% + 6px);left:0;z-index:40;width:min(440px, calc(100vw - 24px));background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;box-shadow:0 18px 50px rgba(0,0,0,0.45);padding:14px;display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px;">
                <span style="font-size:12.5px;color:var(--fg-1);font-weight:600;">Excluded folders</span>
                <button type="button" wire:click="resetExclusions" style="font-size:11px;color:var(--fg-3);text-decoration:underline;">Reset to defaults</button>
            </div>

            <form wire:submit="addExclusion()" style="display:flex;gap:6px;">
                <input wire:model="newExclusion" x-ref="exclusionInput" x-init="$watch('open', v => v && $nextTick(() => $refs.exclusionInput.focus()))"
                    placeholder="Folder name or path, e.g. deps, priv/static" aria-label="Folders to exclude"
                    style="flex:1;min-width:0;font-family:var(--font-mono);font-size:12px;padding:7px 9px;background:var(--bg-1);border:1px solid var(--line-1);border-radius:6px;outline:none;color:var(--fg-1);" />
                <button type="submit" style="padding:0 12px;background:var(--accent);color:#fff;border-radius:6px;font-size:12px;font-weight:600;">Add</button>
            </form>
            <span style="font-size:11px;color:var(--fg-3);margin-top:-6px;">Separate several with commas or spaces. A name like <span class="mono">deps</span> matches that folder anywhere; a path like <span class="mono">priv/static</span> matches from the project root.</span>

            <div style="display:flex;flex-wrap:wrap;gap:6px;max-height:180px;overflow-y:auto;">
                @forelse ($exclusions as $index => $exc)
                    <span wire:key="exc-{{ $exc }}" class="mono" style="display:inline-flex;align-items:center;gap:4px;padding:3px 4px 3px 8px;font-size:11px;background:var(--bg-1);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);white-space:nowrap;">
                        {{ $exc }}
                        <button type="button" wire:click="removeExclusion({{ $index }})" title="Stop excluding {{ $exc }}" style="color:var(--fg-3);display:flex;padding:2px;border-radius:3px;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </span>
                @empty
                    <span style="font-size:11.5px;color:var(--fg-3);">Nothing excluded. Every folder is scanned.</span>
                @endforelse
            </div>

            @if ($webMode)
                <div x-show="largestFolders.length" x-cloak style="display:flex;flex-direction:column;gap:6px;border-top:1px solid var(--line-2);padding-top:10px;">
                    <span style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:0.06em;">Largest folders in this selection</span>
                    <template x-for="folder in largestFolders" :key="folder.path">
                        <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                            <span class="mono" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--fg-1);" x-text="folder.path"></span>
                            <span class="mono" style="font-size:11px;color:var(--fg-3);white-space:nowrap;" x-text="folder.label"></span>
                            <button type="button" @click="$wire.addExclusion(folder.path)" style="padding:2px 8px;font-size:11px;border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);background:var(--bg-1);">Exclude</button>
                        </div>
                    </template>
                </div>
            @endif

            @php $suggestions = array_values(array_diff(config('checkmycode.suggested_exclusions', []), $exclusions)); @endphp
            @if ($suggestions)
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;border-top:1px solid var(--line-2);padding-top:10px;">
                    <span style="font-size:11px;color:var(--fg-3);margin-right:2px;">Common:</span>
                    @foreach ($suggestions as $suggestion)
                        <button type="button" wire:click="addExclusion('{{ $suggestion }}')" class="mono" style="padding:2px 7px;font-size:11px;border:1px dashed var(--line-1);border-radius:5px;color:var(--fg-3);">+ {{ $suggestion }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($status === 'running' && ! $webMode)
        <button wire:click="stopScan" style="display:flex;align-items:center;gap:7px;padding:0 14px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;color:var(--fg-1);">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-critical);"><rect x="6" y="6" width="12" height="12" rx="1.5" fill="currentColor"/></svg>
            Stop
        </button>
    @elseif ($webMode)
        <button type="button" @click="upload()" :disabled="!files.length || busy || {{ $status === 'running' ? 'true' : 'false' }}" :style="{ opacity: (!files.length || busy || {{ $status === 'running' ? 'true' : 'false' }}) ? 0.5 : 1 }" style="display:flex;align-items:center;gap:7px;padding:0 16px;height:32px;background:var(--accent);color:#fff;border-radius:7px;font-size:12px;font-weight:600;box-shadow:0 1px 0 rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.15);">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v16l14-8L6 4Z" fill="currentColor"/></svg>
            <span x-text="busy ? phase : '{{ $status === 'running' ? 'Scanning…' : 'Scan' }}'">Scan</span>
        </button>
    @else
        <button wire:click="startScan" @disabled(!$projectPath) style="display:flex;align-items:center;gap:7px;padding:0 16px;height:32px;background:var(--accent);color:#fff;border-radius:7px;font-size:12px;font-weight:600;box-shadow:0 1px 0 rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.15);">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v16l14-8L6 4Z" fill="currentColor"/></svg>
            Scan
        </button>
    @endif
</div>

@if ($webMode)
{{-- ── Privacy strip: what happens to the selected files ─────────────── --}}
<div class="m-privacy" style="padding:7px 20px;border-bottom:1px solid var(--line-1);background:color-mix(in srgb,var(--sev-ok) 5%,var(--bg-1));display:flex;align-items:center;gap:8px;font-size:11.5px;color:var(--fg-2);">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-ok);flex-shrink:0;"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
    <span x-show="!busy">
        <strong style="color:var(--fg-1);font-weight:600;">Private scan.</strong>
        Excluded folders never leave your browser. The rest is sent over HTTPS to a temporary workspace, scanned, and <strong style="color:var(--fg-1);font-weight:600;">deleted as soon as the scan finishes</strong>. Your code is never stored.
    </span>
    <span x-show="busy" x-cloak>
        <strong style="color:var(--fg-1);font-weight:600;" x-text="phase"></strong>
        Your files go to a temporary workspace only for this scan and are deleted right after it.
    </span>
    <span style="flex:1;"></span>
    <span style="white-space:nowrap;color:var(--fg-3);">By scanning, you agree to the <a href="{{ route('terms') }}" target="_blank" style="color:var(--fg-2);text-decoration:underline;text-underline-offset:2px;">Terms</a>.</span>
    <button type="button" @click="showPrivacy = true" style="color:var(--sev-ok);white-space:nowrap;text-decoration:underline;text-underline-offset:2px;">What happens to my code?</button>
</div>
@endif

{{-- ── Tab Bar ──────────────────────────────────────────────────────── --}}
@php
    $summary = $this->results['summary'] ?? [];
    $trivyTotal = $summary['trivy_total'] ?? 0;
    $semgrepTotal = $summary['semgrep_total'] ?? 0;
    $gitleaksTotal = $summary['gitleaks_total'] ?? 0;
    $laravelTotal = $summary['laravel_rules_total'] ?? 0;
    $customTotal = $summary['custom_rules_total'] ?? 0;
    $rulesTotal = $laravelTotal + $customTotal;
    $configTotal = $summary['trivy_config_total'] ?? 0;
    $workflowsTotal = $summary['workflows_total'] ?? 0;
    $lifecycleTotal = $summary['endoflife_total'] ?? 0;
    $licensesTotal = $summary['licenses_total'] ?? 0;
    $envTotal = $summary['env_audit_total'] ?? 0;
    $codeQualityTotal = $summary['code_quality_total'] ?? 0;
    $scanProjectType = $this->results['project_type'] ?? null;
    $isLaravelProject = $scanProjectType === 'laravel' || $laravelTotal > 0;
    $rulesSeverity = fn (string $sev) => ($summary['laravel_rules'][$sev] ?? 0) + ($summary['custom_rules'][$sev] ?? 0);
    $tabs = [
        ['id'=>'log',          'label'=>'Log',          'count'=>null,          'color'=>null,
         'icon'=>'<path d="m5 9 3 3-3 3M12 15h7"/><rect x="2" y="4" width="20" height="16" rx="2"/>'],
        ['id'=>'summary',      'label'=>'Summary',      'count'=>null,          'color'=>null,
         'icon'=>'<path d="M12 3v5M12 16v5M3 12h5M16 12h5M6 6l3 3M15 15l3 3M6 18l3-3M15 9l3-3"/>'],
        ['id'=>'trivy',        'label'=>'Dependencies', 'count'=>$trivyTotal,   'color'=>'var(--sev-low)',
         'icon'=>'<path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/><path d="m3 7 9-5 9 5v10l-9 5-9-5V7Z"/>'],
        ['id'=>'semgrep',      'label'=>'Code',         'count'=>$semgrepTotal, 'color'=>'#c69dff',
         'icon'=>'<path d="m8 6-6 6 6 6M16 6l6 6-6 6M14 4 10 20"/>'],
        ['id'=>'gitleaks',     'label'=>'Secrets',      'count'=>$gitleaksTotal,'color'=>'var(--sev-critical)',
         'icon'=>'<circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M16 8l3 3M14 10l3 3"/>'],
        ['id'=>'rules',        'label'=>'Rules',        'count'=>$rulesTotal,   'color'=>'var(--accent-hi)',
         'icon'=>'<path d="M12 3 4 8v8l8 5 8-5V8l-8-5Z"/><path d="m4 8 8 5 8-5M12 13v8"/>'],
        ['id'=>'config',       'label'=>'Config',       'count'=>$configTotal,  'color'=>'var(--sev-medium)',
         'icon'=>'<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>'],
        ['id'=>'workflows',    'label'=>'Workflows',    'count'=>$workflowsTotal, 'color'=>'var(--sev-high)',
         'icon'=>'<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M6 8.5v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2M12 12.5v3"/>'],
        ['id'=>'lifecycle',    'label'=>'Lifecycle',    'count'=>$lifecycleTotal, 'color'=>'var(--sev-high)',
         'icon'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'],
        ['id'=>'env_audit',    'label'=>'.env',         'count'=>$envTotal,     'color'=>'var(--sev-ok)',
         'icon'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/>'],
        ['id'=>'code_quality', 'label'=>'Quality',      'count'=>$codeQualityTotal, 'color'=>'#c69dff',
         'icon'=>'<path d="m12 3 1.9 5.8h6.1l-4.9 3.6 1.9 5.8-5-3.6-5 3.6 1.9-5.8-4.9-3.6h6.1L12 3Z"/>'],
        ['id'=>'licenses',     'label'=>'Licenses',     'count'=>$licensesTotal, 'color'=>'var(--fg-2)',
         'icon'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M20 17v4H6.5A2.5 2.5 0 0 1 4 18.5"/>'],
    ];
    // Optional engines only get a tab when they have something to show.
    $tabs = array_values(array_filter($tabs, fn ($t) => ! in_array($t['id'], ['config', 'workflows', 'lifecycle', 'licenses', 'env_audit'], true) || $t['count'] > 0 || $activeTab === $t['id']
        || ($t['id'] === 'lifecycle' && ! empty($results['endoflife']['components']))
        || ($t['id'] === 'env_audit' && ! empty($results['env_audit']))));
@endphp
<div class="scroll" style="display:flex;align-items:stretch;border-bottom:1px solid var(--line-1);background:var(--bg-1);padding-left:12px;position:relative;overflow-x:auto;white-space:nowrap;">
    @foreach ($tabs as $tab)
        @php $sel = $activeTab === $tab['id']; @endphp
        <button wire:click="$set('activeTab','{{ $tab['id'] }}')" style="flex-shrink:0;position:relative;display:flex;align-items:center;gap:7px;padding:10px 13px;font-size:12px;font-weight:500;color:{{ $sel ? 'var(--fg-1)' : 'var(--fg-3)' }};transition:color 160ms;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:{{ $sel ? '1' : '0.7' }};">{!! $tab['icon'] !!}</svg>
            <span>{{ $tab['label'] }}</span>
            @if ($tab['count'] && $tab['count'] > 0)
                <span style="font-size:10px;font-weight:600;padding:1px 5px;border-radius:3px;min-width:16px;text-align:center;color:{{ $tab['color'] }};background:color-mix(in srgb,{{ $tab['color'] }} 16%,transparent);font-family:var(--font-mono);">{{ $tab['count'] }}</span>
            @endif
            @if ($sel)
                <div class="tab-active-bar"></div>
            @endif
        </button>
    @endforeach
    <div style="flex:1;"></div>
    @if (config('checkmycode.ci_workflow'))
    <button wire:click="exportGithubActionsWorkflow" style="display:flex;align-items:center;gap:6px;padding:6px 10px;margin:6px 6px 6px 0;font-size:11.5px;color:var(--fg-2);background:var(--bg-2);border:1px solid var(--line-1);border-radius:6px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M8.5 8.5 15.5 15.5"/><path d="M6 9v6a3 3 0 0 0 3 3h6"/></svg>
        CI YAML
    </button>
    @endif
    <button wire:click="exportSarif" @disabled(!$results) style="display:flex;align-items:center;gap:6px;padding:6px 10px;margin:6px 6px 6px 0;font-size:11.5px;color:var(--fg-2);background:var(--bg-2);border:1px solid var(--line-1);border-radius:6px;opacity:{{ $results ? '1' : '0.45' }};">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>
        SARIF
    </button>
    <button wire:click="exportJson" @disabled(!$results) style="display:flex;align-items:center;gap:6px;padding:6px 12px;margin:6px 12px 6px 0;font-size:11.5px;color:var(--fg-2);background:var(--bg-2);border:1px solid var(--line-1);border-radius:6px;opacity:{{ $results ? '1' : '0.45' }};">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        JSON
    </button>
</div>

{{-- ── Tab content ──────────────────────────────────────────────────── --}}

{{-- LOG ---------------------------------------------------------------- --}}
@if ($activeTab === 'log')
@php
    $scannerStyles = [
        'sys'      => ['bg'=>'rgba(140,140,140,0.14)', 'fg'=>'var(--fg-2)'],
        'trivy'    => ['bg'=>'rgba(111,179,255,0.14)', 'fg'=>'var(--sev-low)'],
        'semgrep'  => ['bg'=>'rgba(200,150,255,0.14)', 'fg'=>'#c69dff'],
        'gitleaks' => ['bg'=>'rgba(255,77,77,0.14)',   'fg'=>'var(--sev-critical)'],
        'laravel_rules'=>['bg'=>'rgba(245,48,3,0.14)', 'fg'=>'var(--accent-hi)'],
        'env_audit'=> ['bg'=>'rgba(61,214,140,0.14)',  'fg'=>'var(--sev-ok)'],
        'code_quality'=> ['bg'=>'rgba(200,150,255,0.14)', 'fg'=>'#c69dff'],
        'custom_rules'=> ['bg'=>'rgba(245,48,3,0.14)', 'fg'=>'var(--accent-hi)'],
        'endoflife'=> ['bg'=>'rgba(255,138,61,0.14)', 'fg'=>'var(--sev-high)'],
        'workflows'=> ['bg'=>'rgba(255,138,61,0.14)', 'fg'=>'var(--sev-high)'],
    ];
@endphp
<div style="flex:1;display:flex;flex-direction:column;background:var(--log-bg);min-height:0;">
    <div style="padding:8px 20px;border-bottom:1px solid var(--line-2);display:flex;align-items:center;gap:16px;font-size:10.5px;color:var(--fg-3);text-transform:uppercase;letter-spacing:0.08em;font-weight:600;position:relative;">
        <span style="display:flex;align-items:center;gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 9 3 3-3 3M12 15h7"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>
            Scan log
        </span>
        <span style="color:var(--fg-4);">·</span>
        <span>{{ count($logs) }} lines</span>
        <span style="flex:1;"></span>
        @if ($status === 'running')
            <span style="color:var(--accent-hi);display:flex;align-items:center;gap:6px;">
                <span class="pulse-dot" style="width:6px;height:6px;border-radius:50%;background:var(--accent);display:block;"></span>
                Scanning…
            </span>
            <div style="position:absolute;left:0;bottom:-1px;height:1.5px;width:100%;overflow:hidden;">
                <div class="sweep"></div>
            </div>
        @endif
    </div>
    <div class="scroll" id="log-container" style="flex:1;overflow-y:auto;padding:8px 0;">
        @forelse ($logs as $log)
            @php $s = $scannerStyles[$log['scanner']] ?? $scannerStyles['sys']; @endphp
            <div class="log-in m-logrow" style="display:grid;grid-template-columns:80px 84px 1fr;gap:14px;padding:2px 20px;font-family:var(--font-mono);font-size:11.5px;line-height:1.7;align-items:baseline;">
                <span class="m-hide" style="color:var(--fg-4);text-align:right;">{{ $log['time'] }}</span>
                <span style="display:inline-block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;padding:1px 6px;border-radius:3px;background:{{ $s['bg'] }};color:{{ $s['fg'] }};text-align:center;">{{ $log['scanner'] }}</span>
                <span style="color:{{ $log['level'] === 'error' ? 'var(--sev-critical)' : ($log['level'] === 'warn' ? 'var(--fg-1)' : 'var(--fg-2)') }};word-break:break-word;">
                    @if ($log['level'] === 'warn') <span style="color:var(--sev-medium);margin-right:6px;">⚠</span> @endif
                    @if ($log['level'] === 'error') <span style="color:var(--sev-critical);margin-right:6px;">✗</span> @endif
                    {{ $log['line'] }}
                </span>
            </div>
        @empty
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--fg-4);font-size:12px;">
                @if ($webMode)
                    <div style="text-align:center;max-width:460px;line-height:1.6;padding:0 16px;">
                        <div style="font-size:13px;color:var(--fg-2);margin-bottom:6px;">Pick a project folder or file, or drag it onto this window, then press Scan.</div>
                        <div style="font-size:11.5px;">Excluded folders never leave your browser. The rest is scanned in a temporary workspace and deleted as soon as the scan finishes. No account, no database, no stored code.</div>
                    </div>
                @else
                    <span>Select a project folder or file, then press Scan.</span>
                @endif
            </div>
        @endforelse
    </div>
</div>

{{-- SUMMARY ------------------------------------------------------------ --}}
@elseif ($activeTab === 'summary')
@php
    $grade = $this->getSecurityGrade();
    $gradeVarMap = ['A'=>'--grade-a','B'=>'--grade-b','C'=>'--grade-c','D'=>'--grade-d','F'=>'--grade-f','-'=>'--fg-3'];
    $gradeColor = 'var(' . ($gradeVarMap[$grade['grade']] ?? '--fg-3') . ')';
    $gradeLabels = ['A'=>'Excellent','B'=>'Good','C'=>'Fair','D'=>'Poor','F'=>'Critical','-'=>'No data'];
    $gradeLabel = $gradeLabels[$grade['grade']] ?? '—';
    $grandTotal = $summary['findings_total'] ?? \App\Scanning\ScanEngine::findingsCount($results ?? []);
    $severityCounts = \App\Scanning\ScanGrade::severityCounts($summary);
@endphp
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
  <div class="m-pad" style="max-width:1180px;margin:0 auto;padding:28px 32px 40px;">

    {{-- Headline row --}}
    <div class="m-stack" style="display:grid;grid-template-columns:200px 1fr 260px;gap:20px;align-items:stretch;margin-bottom:22px;">

      {{-- Grade card --}}
      <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:14px;padding:20px 20px 18px;position:relative;overflow:hidden;">
        <div style="position:absolute;top:-40px;right:-40px;width:140px;height:140px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,{{ $gradeColor }} 22%,transparent),transparent 70%);"></div>
        <div style="font-size:10.5px;color:var(--fg-3);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:8px;">Security grade</div>
        <div class="serif" style="font-size:120px;line-height:0.85;color:{{ $gradeColor }};letter-spacing:-0.04em;position:relative;">{{ $grade['grade'] }}</div>
        <div style="margin-top:8px;font-size:15px;color:var(--fg-1);font-weight:500;">{{ $gradeLabel }}</div>
      </div>

      {{-- Narrative --}}
      <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:14px;padding:22px 24px;display:flex;flex-direction:column;justify-content:space-between;">
        <div>
          <div style="font-size:10.5px;color:var(--fg-3);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:10px;">Scan result</div>
          <div class="serif" style="font-size:28px;line-height:1.15;color:var(--fg-1);letter-spacing:-0.015em;">
            @if ($grandTotal === 0)
              No issues found — ship with confidence.
            @else
              <span style="color:var(--accent);">{{ $grandTotal }} findings</span> across {{ $webMode ? '' : 'local ' }}security engines.
              Address <span style="color:{{ $gradeColor }};">{{ $severityCounts['secrets'] + $severityCounts['critical'] }} blockers</span> before deploy.
            @endif
          </div>
        </div>
        @php $ignoredStats = $this->ignoredStats; @endphp
        <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line-2);display:flex;flex-wrap:wrap;align-items:center;gap:8px 20px;font-size:11.5px;color:var(--fg-3);">
          @if ($scanRecord = $this->selectedScan)
            <span>Finished {{ $scanRecord->completedAt?->format('H:i') ?? '—' }}
            @if ($scanRecord->completedAt && $scanRecord->startedAt)
              · took {{ (int) $scanRecord->startedAt->diffInSeconds($scanRecord->completedAt) }}s
            @endif
            </span>
          @endif
          @if ($webMode && $scanRecord)
            <span style="color:var(--fg-4);">·</span>
            <span style="display:inline-flex;align-items:center;gap:5px;color:var(--sev-ok);">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-ok);flex-shrink:0;"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              {{ $scanRecord->codeDeletedAt ? 'Your code was deleted from the server at '.$scanRecord->codeDeletedAt->format('H:i:s') : 'Your code is deleted when the scan finishes' }}
            </span>
            <span style="color:var(--fg-4);">·</span>
            <span>findings expire {{ ($scanRecord->codeDeletedAt ?? $scanRecord->completedAt ?? $scanRecord->createdAt)->copy()->addMinutes($webLimits['ttlMinutes'])->format('H:i') }}</span>
          @endif
          @if ($ignoredStats['total'] > 0)
            <span style="color:var(--fg-4);">·</span>
            <span>{{ $ignoredStats['total'] }} ignored</span>
          @endif
        </div>
      </div>

      {{-- Trend / totals mini card --}}
      <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:14px;padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between;">
        <div style="font-size:10.5px;color:var(--fg-3);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:10px;">Findings breakdown</div>
        <div style="display:flex;flex-direction:column;gap:7px;">
          @php
            $breakdownItems = [['Dependencies', $trivyTotal, 'var(--sev-low)'], ['Code', $semgrepTotal, '#c69dff'], ['Secrets', $gitleaksTotal, 'var(--sev-critical)'], ['Rules', $rulesTotal, 'var(--accent-hi)'], ['Config', $configTotal, 'var(--sev-medium)'], ['Workflows', $workflowsTotal, 'var(--sev-high)'], ['Lifecycle', $lifecycleTotal, 'var(--sev-high)']];
            $breakdownItems[] = ['.env', $envTotal, 'var(--sev-ok)'];
          @endphp
          @foreach ($breakdownItems as [$el, $ev, $ec])
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:11.5px;">
              <span style="color:var(--fg-2);">{{ $el }}</span>
              <span class="mono" style="color:{{ $ev > 0 ? $ec : 'var(--fg-4)' }};font-weight:600;">{{ $ev }}</span>
            </div>
          @endforeach
        </div>
        <div style="margin-top:12px;">
          <div class="serif" style="font-size:32px;color:var(--fg-1);line-height:1;">{{ $grandTotal }}</div>
          <div style="font-size:11px;color:var(--fg-3);">total findings</div>
        </div>
      </div>
    </div>

    {{-- Engine stat cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:22px;">
      @php
        $engineCards = [
          ['Dependencies', $trivyTotal, 'var(--sev-low)', 'Trivy + OSV-Scanner',
           [['CRIT',$summary['trivy']['CRITICAL']??0],['HIGH',$summary['trivy']['HIGH']??0],['MED',$summary['trivy']['MEDIUM']??0],['LOW',$summary['trivy']['LOW']??0]]],
          ['Code Analysis', $semgrepTotal, '#c69dff', 'Semgrep · SAST',
           [['ERR',$summary['semgrep']['ERROR']??0],['WARN',$summary['semgrep']['WARNING']??0],['INFO',$summary['semgrep']['INFO']??0]]],
          ['Secrets', $gitleaksTotal, 'var(--sev-critical)', ($results['meta']['git_history'] ?? false) ? 'Gitleaks · files + history' : 'Gitleaks · files',
           [['LEAKED',$gitleaksTotal]]],
          ['Rules', $rulesTotal, 'var(--accent-hi)', $isLaravelProject ? 'Laravel + custom rule packs' : 'Custom rule pack',
           [['CRIT',$rulesSeverity('CRITICAL')],['HIGH',$rulesSeverity('HIGH')],['MED',$rulesSeverity('MEDIUM')]]],
          ['Config / IaC', $configTotal, 'var(--sev-medium)', 'Trivy · misconfiguration',
           [['CRIT',$summary['trivy_config']['CRITICAL']??0],['HIGH',$summary['trivy_config']['HIGH']??0],['MED',$summary['trivy_config']['MEDIUM']??0],['LOW',$summary['trivy_config']['LOW']??0]]],
          ['Workflows', $workflowsTotal, 'var(--sev-high)', 'zizmor · GitHub Actions',
           [['HIGH',$summary['workflows']['HIGH']??0],['MED',$summary['workflows']['MEDIUM']??0],['LOW',$summary['workflows']['LOW']??0]]],
          ['Lifecycle', $lifecycleTotal, 'var(--sev-high)', ($summary['endoflife']['components'] ?? 0).' components · endoflife.date',
           [['EOL',$summary['endoflife']['CRITICAL']??0],['HIGH',$summary['endoflife']['HIGH']??0],['MED',$summary['endoflife']['MEDIUM']??0]]],
          ['.env Audit', $envTotal, 'var(--sev-ok)', empty($results['env_audit']) ? 'no .env file' : 'environment checks',
           [['HIGH',$summary['env_audit']['HIGH']??0],['MED',$summary['env_audit']['MEDIUM']??0],['INFO',$summary['env_audit']['INFO']??0]]],
          ['Quality', $codeQualityTotal, '#c69dff', 'Best practices',
           [['FIX',$summary['code_quality']['ERROR']??0],['IMP',$summary['code_quality']['WARNING']??0],['INFO',$summary['code_quality']['INFO']??0]]],
        ];
      @endphp
      @foreach ($engineCards as [$label, $total, $color, $sub, $breakdown])
        <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;padding:14px 14px 12px;display:flex;flex-direction:column;">
          <div style="font-size:11px;color:var(--fg-2);font-weight:500;margin-bottom:8px;">{{ $label }}</div>
          <div class="serif" style="font-size:36px;line-height:0.9;color:{{ $total > 0 ? 'var(--fg-1)' : 'var(--fg-3)' }};">{{ $total }}</div>
          <div style="font-size:10.5px;color:var(--fg-3);margin-top:3px;">{{ $sub }}</div>
          <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
            @foreach ($breakdown as [$k, $v])
              @if ($v > 0)
                <span style="font-size:10px;color:var(--fg-3);" class="mono"><span style="color:var(--fg-1);font-weight:600;">{{ $v }}</span> {{ $k }}</span>
              @endif
            @endforeach
            @if (array_sum(array_column($breakdown, 1)) === 0)
              <span style="font-size:10px;color:var(--sev-ok);">· clean</span>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    {{-- Open-source nudge --}}
    <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;margin-bottom:22px;background:color-mix(in srgb,#ff6b9a 6%,var(--bg-2));border:1px solid color-mix(in srgb,#ff6b9a 22%,var(--line-1));border-radius:10px;font-size:12.5px;color:var(--fg-2);">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="color:#ff6b9a;flex-shrink:0;"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/></svg>
      <span style="flex:1;">CheckMyCode is free and open source. If this scan helped, star it on GitHub or sponsor its development so it stays free.</span>
      @if ($webMode)
        <a href="{{ config('checkmycode.github_url') }}" target="_blank" rel="noopener" style="color:var(--fg-1);text-decoration:none;white-space:nowrap;">★ Star on GitHub</a>
        <a href="{{ config('checkmycode.sponsor_url') }}" target="_blank" rel="noopener" style="color:#ff6b9a;font-weight:600;text-decoration:none;white-space:nowrap;">♥ Sponsor</a>
      @else
        <button wire:click="openLink('github')" style="color:var(--fg-1);white-space:nowrap;">★ Star on GitHub</button>
        <button wire:click="openLink('sponsor')" style="color:#ff6b9a;font-weight:600;white-space:nowrap;">♥ Sponsor</button>
      @endif
    </div>

    {{-- Severity distribution + diff panel --}}
    <div class="m-stack" style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

      {{-- Stacked bar chart --}}
      <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:12px;padding:18px 22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h3 style="margin:0;font-size:13px;font-weight:600;color:var(--fg-1);">Severity distribution</h3>
          <span class="mono" style="font-size:10.5px;color:var(--fg-3);">normalized to max</span>
        </div>
        <div style="display:flex;gap:14px;margin-bottom:14px;font-size:11px;">
          @foreach ([['Critical','var(--sev-critical)'],['High','var(--sev-high)'],['Medium','var(--sev-medium)'],['Low/Info','var(--sev-low)']] as [$l,$c])
            <span style="display:inline-flex;align-items:center;gap:5px;color:var(--fg-3);">
              <span style="width:8px;height:8px;border-radius:2px;background:{{ $c }};display:inline-block;"></span>{{ $l }}
            </span>
          @endforeach
        </div>
        @php
          $sevRows = [
            ['Dependencies (Trivy)', [['CRITICAL',$summary['trivy']['CRITICAL']??0,'var(--sev-critical)'],['HIGH',$summary['trivy']['HIGH']??0,'var(--sev-high)'],['MEDIUM',$summary['trivy']['MEDIUM']??0,'var(--sev-medium)'],['LOW',$summary['trivy']['LOW']??0,'var(--sev-low)']]],
            ['Code Analysis (Semgrep)', [['ERROR',$summary['semgrep']['ERROR']??0,'var(--sev-critical)'],['WARNING',$summary['semgrep']['WARNING']??0,'var(--sev-medium)'],['INFO',$summary['semgrep']['INFO']??0,'var(--sev-info)']]],
            ['Rules', [['CRITICAL',$rulesSeverity('CRITICAL'),'var(--sev-critical)'],['HIGH',$rulesSeverity('HIGH'),'var(--sev-high)'],['MEDIUM',$rulesSeverity('MEDIUM'),'var(--sev-medium)'],['LOW',$rulesSeverity('LOW'),'var(--sev-low)']]],
            ['Config / IaC', [['CRITICAL',$summary['trivy_config']['CRITICAL']??0,'var(--sev-critical)'],['HIGH',$summary['trivy_config']['HIGH']??0,'var(--sev-high)'],['MEDIUM',$summary['trivy_config']['MEDIUM']??0,'var(--sev-medium)'],['LOW',$summary['trivy_config']['LOW']??0,'var(--sev-low)']]],
            ['Workflows', [['HIGH',$summary['workflows']['HIGH']??0,'var(--sev-high)'],['MEDIUM',$summary['workflows']['MEDIUM']??0,'var(--sev-medium)'],['LOW',$summary['workflows']['LOW']??0,'var(--sev-low)']]],
            ['Lifecycle', [['CRITICAL',$summary['endoflife']['CRITICAL']??0,'var(--sev-critical)'],['HIGH',$summary['endoflife']['HIGH']??0,'var(--sev-high)'],['MEDIUM',$summary['endoflife']['MEDIUM']??0,'var(--sev-medium)']]],
            ['.env Audit', [['CRITICAL',$summary['env_audit']['CRITICAL']??0,'var(--sev-critical)'],['HIGH',$summary['env_audit']['HIGH']??0,'var(--sev-high)'],['MEDIUM',$summary['env_audit']['MEDIUM']??0,'var(--sev-medium)'],['INFO',$summary['env_audit']['INFO']??0,'var(--sev-info)']]],
          ];
          $maxSevRow = max([...array_map(fn($r) => array_sum(array_column($r[1],1)), $sevRows), 1]);
        @endphp
        @foreach ($sevRows as [$rl, $rd])
          @php $rt = array_sum(array_column($rd,1)); $pct = ($rt/$maxSevRow)*100; @endphp
          <div class="m-sevrow" style="margin-bottom:12px;display:grid;grid-template-columns:170px 1fr 40px;align-items:center;gap:12px;">
            <span style="font-size:11.5px;color:var(--fg-2);">{{ $rl }}</span>
            <div style="height:14px;background:var(--bg-3);border-radius:3px;overflow:hidden;display:flex;width:{{ $pct }}%;min-width:4px;">
              @foreach ($rd as [$rk,$rn,$rc])
                @if ($rn > 0)
                  <div title="{{ $rk }}: {{ $rn }}" style="background:{{ $rc }};flex:{{ $rn }};"></div>
                @endif
              @endforeach
            </div>
            <span class="mono" style="font-size:11px;color:var(--fg-1);text-align:right;font-weight:600;">{{ $rt }}</span>
          </div>
        @endforeach
      </div>

      {{-- Diff panel --}}
      <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:12px;padding:18px 22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
          <h3 style="margin:0;font-size:13px;font-weight:600;color:var(--fg-1);">Compare with previous</h3>
        </div>
        @if ($scanDiff)
          <p style="font-size:11px;color:var(--fg-3);margin-bottom:10px;">{{ $scanDiff['old_date'] }} → {{ $scanDiff['new_date'] }}</p>
          @php
            $diffRows = [['Total',$scanDiff['total']],['Dependencies',$scanDiff['trivy']],['Code',$scanDiff['semgrep']],['Secrets',$scanDiff['gitleaks']],['Rules',($scanDiff['laravel_rules'] ?? 0) + ($scanDiff['custom_rules'] ?? 0)],['Config',$scanDiff['trivy_config'] ?? 0],['Workflows',$scanDiff['workflows'] ?? 0],['Lifecycle',$scanDiff['endoflife'] ?? 0]];
            $diffRows[] = ['.env',$scanDiff['env_audit']];
            $diffRows[] = ['Quality',$scanDiff['code_quality'] ?? 0];
          @endphp
          @foreach ($diffRows as [$dl,$dd])
            <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-2);">
              <span style="font-size:11.5px;color:var(--fg-2);font-weight:{{ $dl==='Total'?'600':'400' }};">{{ $dl }}</span>
              <span style="font-size:11.5px;font-weight:500;color:{{ $dd < 0 ? 'var(--sev-ok)' : ($dd > 0 ? 'var(--sev-critical)' : 'var(--fg-3)') }};">
                {{ $dd > 0 ? '+'.$dd : ($dd < 0 ? $dd : '=') }}
              </span>
            </div>
          @endforeach
          <button wire:click="clearComparison" style="margin-top:10px;font-size:11px;color:var(--fg-3);">Clear</button>
        @elseif ($this->comparableScans->isNotEmpty())
          <p style="font-size:11.5px;color:var(--fg-3);margin-bottom:10px;">Select a previous scan:</p>
          <div style="display:flex;flex-direction:column;gap:6px;">
            @foreach ($this->comparableScans as $cs)
              <button wire:click="compareScan('{{ $cs->id }}')" style="padding:8px 10px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:7px;font-size:11.5px;color:var(--fg-2);text-align:left;">
                {{ $cs->createdAt->format('M j, H:i') }} — {{ $cs->findingsCount }} findings
              </button>
            @endforeach
          </div>
        @else
          <p style="font-size:11.5px;color:var(--fg-4);">No previous scans to compare.</p>
        @endif
      </div>
    </div>

  </div>
</div>

{{-- TRIVY ---------------------------------------------------------------- --}}
@elseif ($activeTab === 'trivy')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div class="m-tabhead" style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Dependency vulnerabilities</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $trivyTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Trivy + OSV-Scanner · public CVE databases for every lockfile</span>
        @include('livewire.partials.fix-prompt-button', ['tab' => 'trivy'])
    </div>
    <div class="m-pad" style="padding:4px 32px 32px;">
      @if (($summary['dev_dependencies_total'] ?? 0) > 0)
        <div style="margin:12px 0;padding:12px 16px;background:color-mix(in srgb,var(--sev-low) 8%,transparent);border:1px solid color-mix(in srgb,var(--sev-low) 22%,transparent);border-radius:8px;font-size:12px;color:var(--fg-2);line-height:1.5;">
          {{ $summary['dev_dependencies_total'] }} {{ Str::plural('vulnerability', $summary['dev_dependencies_total']) }} marked <span class="mono" style="color:var(--fg-1);">DEV</span> are in development dependencies (build and test tools). They don't count toward your grade, but still fix them if the package ends up in your shipped bundle (common for front-end libraries) or your dev server is reachable from a network.
        </div>
      @endif
      @if (!empty($results['trivy']['Results']))
        <div class="m-table" style="--table-min:980px;border:1px solid var(--line-1);border-radius:10px;background:var(--bg-2);overflow:hidden;">
          <div style="display:grid;grid-template-columns:84px 160px 200px 110px 110px 1fr 100px;padding:10px 16px;border-bottom:1px solid var(--line-1);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);background:var(--bg-1);">
            <span>Severity</span><span>CVE</span><span>Package</span><span>Installed</span><span>Fixed</span><span>Title</span><span>Action</span>
          </div>
          @foreach ($results['trivy']['Results'] as $result)
            @foreach ($result['Vulnerabilities'] ?? [] as $vuln)
              @php
                $tfp = ($vuln['VulnerabilityID']??'').':'.($vuln['PkgName']??'');
                $ignored = $this->isIgnored('trivy', $tfp);
                $sevColors = ['CRITICAL'=>'var(--sev-critical)','HIGH'=>'var(--sev-high)','MEDIUM'=>'var(--sev-medium)','LOW'=>'var(--sev-low)','UNKNOWN'=>'var(--fg-3)'];
                $sc = $sevColors[$vuln['Severity']??'UNKNOWN'] ?? 'var(--fg-3)';
              @endphp
              <div style="display:grid;grid-template-columns:84px 160px 200px 110px 110px 1fr 100px;padding:12px 16px;align-items:center;gap:8px;border-bottom:1px solid var(--line-2);font-size:12px;opacity:{{ $ignored?'0.45':'1' }};"
                   onmouseenter="this.style.background='var(--bg-3)'" onmouseleave="this.style.background='transparent'">
                <span style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 6px;border-radius:3px;color:{{ $sc }};background:color-mix(in srgb,{{ $sc }} 14%,transparent);border:1px solid color-mix(in srgb,{{ $sc }} 24%,transparent);font-family:var(--font-mono);">{{ $vuln['Severity']??'?' }}</span>
                <span class="mono" style="color:var(--fg-1);">{{ $vuln['VulnerabilityID']??'' }}</span>
                <span style="display:flex;flex-direction:column;gap:3px;min-width:0;">
                <span style="display:flex;align-items:center;gap:6px;min-width:0;">
                  @php
                    $ecoMap = ['composer'=>'PHP','npm'=>'JS','yarn'=>'JS','pnpm'=>'JS','bun'=>'JS','node-pkg'=>'JS','pip'=>'PY','pipenv'=>'PY','poetry'=>'PY','uv'=>'PY','python-pkg'=>'PY','bundler'=>'RB','gemspec'=>'RB','gomod'=>'GO','gobinary'=>'GO','cargo'=>'RS','rustbinary'=>'RS','mix'=>'EX','hex'=>'EX','pom'=>'JAVA','gradle'=>'JAVA','jar'=>'JAVA','sbt'=>'JAVA','nuget'=>'.NET','dotnet-core'=>'.NET','packages-props'=>'.NET','pub'=>'DART','swift'=>'SWIFT','cocoapods'=>'SWIFT','conan'=>'C++'];
                    $eco = $ecoMap[strtolower($result['Type'] ?? '')] ?? strtoupper(substr($result['Type'] ?? 'PKG', 0, 5));
                    $ecoColor = $eco==='PHP' ? 'var(--accent-hi)' : 'var(--sev-low)'; $ecoBg = $eco==='PHP' ? 'rgba(245,48,3,0.14)' : 'rgba(111,179,255,0.14)';
                  @endphp
                  <span style="font-size:9px;font-weight:700;letter-spacing:0.06em;padding:1px 4px;border-radius:3px;background:{{ $ecoBg }};color:{{ $ecoColor }};font-family:var(--font-mono);">{{ $eco }}</span>
                  <span class="mono" style="color:var(--fg-1);">{{ $vuln['PkgName']??'' }}</span>
                  @if (!empty($vuln['DevDependency']))
                    <span title="Dev dependency: not graded" style="font-size:9px;font-weight:700;letter-spacing:0.06em;padding:1px 4px;border-radius:3px;background:var(--bg-3);color:var(--fg-3);font-family:var(--font-mono);">DEV</span>
                  @endif
                </span>
                @php $introducedBy = $vuln['IntroducedBy'] ?? null; @endphp
                @if ($introducedBy)
                  <span class="mono" style="font-size:10.5px;color:var(--fg-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="{{ $introducedBy['direct'] ? 'Listed directly in your manifest' : 'Pulled in by: '.implode(', ', $introducedBy['via']) }}">
                    {{ $introducedBy['direct'] ? 'direct' : 'via '.$introducedBy['via'][0].(count($introducedBy['via']) > 1 ? ' +'.(count($introducedBy['via']) - 1) : '') }}
                  </span>
                @endif
                </span>
                <span class="mono" style="color:var(--fg-3);">{{ $vuln['InstalledVersion']??'' }}</span>
                @php
                  $upgrade = \App\Scanning\UpgradePath::classify($vuln['InstalledVersion'] ?? null, $vuln['FixedVersion'] ?? null);
                  $upgradeStyles = ['patch' => ['var(--sev-ok)', 'safe patch upgrade'], 'minor' => ['var(--sev-ok)', 'minor upgrade, usually safe'], 'major' => ['var(--sev-high)', 'major upgrade: may break things, review first'], 'unknown' => ['var(--fg-3)', 'could not compare versions'], 'none' => ['var(--fg-3)', 'no fixed version published yet']];
                  [$upgradeColor, $upgradeHint] = $upgradeStyles[$upgrade['type']];
                @endphp
                <span style="display:flex;flex-direction:column;gap:3px;min-width:0;" title="{{ $vuln['FixedVersion'] ?? '' }}">
                  <span class="mono" style="color:{{ $upgrade['type'] === 'none' ? 'var(--fg-3)' : 'var(--sev-ok)' }};overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $upgrade['target'] ? '→ '.$upgrade['target'] : 'no fix yet' }}</span>
                  @if (in_array($upgrade['type'], ['patch', 'minor', 'major'], true))
                    <span title="{{ $upgradeHint }}" style="width:fit-content;font-size:9px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:1px 5px;border-radius:3px;color:{{ $upgradeColor }};background:color-mix(in srgb,{{ $upgradeColor }} 14%,transparent);font-family:var(--font-mono);">{{ $upgrade['type'] }}</span>
                  @endif
                </span>
                <span style="color:var(--fg-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $result['Target'] ?? '' }}">@if (!empty($vuln['PrimaryURL']))<a href="{{ $vuln['PrimaryURL'] }}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">{{ $vuln['Title']??$vuln['Description']??'' }}</a>@else{{ $vuln['Title']??$vuln['Description']??'' }}@endif</span>
                @if ($ignored)
                  <div style="display:flex;align-items:center;gap:4px;">
                    <span style="font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:3px;color:var(--sev-medium);background:color-mix(in srgb,var(--sev-medium) 14%,transparent);" title="{{ $this->getIgnoreReason('trivy',$tfp) }}">IGNORED</span>
                    <button wire:click="unignoreFinding('trivy','{{ $tfp }}')" style="font-size:11px;color:var(--fg-4);">×</button>
                  </div>
                @else
                  <button wire:click="promptIgnore('trivy:{{ $tfp }}')" style="font-size:11px;color:var(--fg-2);padding:4px 8px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;">Ignore</button>
                @endif
              </div>
            @endforeach
          @endforeach
        </div>
      @else
        <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--sev-ok);flex-direction:column;gap:8px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
          <span style="font-size:13px;">No dependency vulnerabilities found.</span>
        </div>
      @endif
    </div>
</div>

{{-- SEMGREP ------------------------------------------------------------- --}}
@elseif ($activeTab === 'semgrep')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div class="m-tabhead" style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Static analysis findings</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $semgrepTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Semgrep · language-aware rules</span>
        @include('livewire.partials.fix-prompt-button', ['tab' => 'semgrep'])
    </div>
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:12px;">
        @forelse ($results['semgrep']['results'] ?? [] as $finding)
          @php
            $sfp = ($finding['check_id']??'').':'.($finding['path']??'').':'.($finding['start']['line']??'');
            $sig = $this->isIgnored('semgrep',$sfp);
            $ssev = $finding['extra']['severity']??'INFO';
            $ssevColors = ['ERROR'=>'var(--sev-critical)','WARNING'=>'var(--sev-medium)','INFO'=>'var(--sev-info)'];
            $ssc = $ssevColors[$ssev]??'var(--fg-3)';
          @endphp
          <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;overflow:hidden;opacity:{{ $sig?'0.45':'1' }};">
            <div style="padding:12px 16px;display:flex;align-items:flex-start;gap:12px;">
              <span style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 6px;border-radius:3px;color:{{ $ssc }};background:color-mix(in srgb,{{ $ssc }} 14%,transparent);border:1px solid color-mix(in srgb,{{ $ssc }} 24%,transparent);font-family:var(--font-mono);flex-shrink:0;margin-top:2px;">{{ $ssev }}</span>
              <div style="flex:1;min-width:0;">
                <div class="mono" style="font-size:11px;color:var(--fg-3);margin-bottom:3px;">{{ $finding['check_id']??'' }}</div>
                <div style="font-size:13px;color:var(--fg-1);line-height:1.45;margin-bottom:6px;">{{ $finding['extra']['message']??'' }}</div>
                <div style="display:flex;align-items:center;gap:8px;font-size:11px;color:var(--fg-3);">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                  <span class="mono" style="color:var(--fg-2);">{{ $finding['path']??'' }}</span>
                  <span style="color:var(--fg-4);">:</span>
                  <span class="mono" style="color:var(--accent-hi);">{{ $finding['start']['line']??'' }}</span>
                </div>
              </div>
              @if ($sig)
                <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                  <span style="font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:3px;color:var(--sev-medium);background:color-mix(in srgb,var(--sev-medium) 14%,transparent);" title="{{ $this->getIgnoreReason('semgrep',$sfp) }}">IGNORED</span>
                  <button wire:click="unignoreFinding('semgrep','{{ $sfp }}')" style="font-size:11px;padding:4px 8px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);">Remove</button>
                </div>
              @else
                <button wire:click="promptIgnore('semgrep:{{ $sfp }}')" style="font-size:11px;padding:4px 10px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);flex-shrink:0;">Ignore</button>
              @endif
            </div>
            @if (!empty($finding['extra']['lines']))
              <div style="background:var(--log-bg);border-top:1px solid var(--line-2);padding:10px 16px;font-family:var(--font-mono);font-size:11.5px;display:flex;gap:14px;align-items:baseline;">
                <span style="color:var(--fg-4);user-select:none;min-width:30px;text-align:right;">{{ $finding['start']['line']??'' }}</span>
                <span style="flex:1;color:var(--fg-1);">{{ $finding['extra']['lines'] }}</span>
              </div>
            @endif
          </div>
        @empty
          <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--sev-ok);flex-direction:column;gap:8px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
            <span style="font-size:13px;">No static analysis findings.</span>
          </div>
        @endforelse
    </div>
</div>

{{-- GITLEAKS ------------------------------------------------------------- --}}
@elseif ($activeTab === 'gitleaks')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div class="m-tabhead" style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Leaked secrets</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $gitleaksTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Gitleaks · {{ ($results['meta']['git_history'] ?? true) ? 'files + full git history' : 'current files' }}</span>
        @include('livewire.partials.fix-prompt-button', ['tab' => 'gitleaks'])
    </div>
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:12px;">
        @include('livewire.partials.gitignored-note')
        @forelse ($results['gitleaks'] ?? [] as $secret)
          @php
            $gfp = ($secret['RuleID']??'').':'.($secret['File']??'').':'.($secret['StartLine']??'');
            $gig = $this->isIgnored('gitleaks',$gfp);
          @endphp
          <div style="background:var(--bg-2);border:1px solid color-mix(in srgb,var(--sev-critical) 28%,var(--line-1));border-radius:10px;padding:14px 16px;position:relative;overflow:hidden;opacity:{{ $gig?'0.45':'1' }};">
            <div style="position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--sev-critical);"></div>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:10px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-critical);"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M16 8l3 3M14 10l3 3"/></svg>
              <span style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 6px;border-radius:3px;color:var(--sev-critical);background:color-mix(in srgb,var(--sev-critical) 14%,transparent);border:1px solid color-mix(in srgb,var(--sev-critical) 24%,transparent);font-family:var(--font-mono);">CRITICAL</span>
              <span style="font-size:13px;font-weight:600;color:var(--fg-1);">{{ $secret['Description']??'' }}</span>
              <span style="flex:1;"></span>
              @if ($gig)
                <span style="font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:3px;color:var(--sev-medium);background:color-mix(in srgb,var(--sev-medium) 14%,transparent);" title="{{ $this->getIgnoreReason('gitleaks',$gfp) }}">IGNORED</span>
                <button wire:click="unignoreFinding('gitleaks','{{ $gfp }}')" style="font-size:11px;padding:4px 8px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);">Remove</button>
              @else
                <button wire:click="promptIgnore('gitleaks:{{ $gfp }}')" style="font-size:11px;padding:4px 10px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);">Ignore</button>
              @endif
            </div>
            <div class="m-grid2" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;font-size:11px;">
              @foreach ([['Rule',['mono',true,$secret['RuleID']??'']],['Match',['mono','critical',$secret['Match']??'']],['File',['mono',false,($secret['File']??'').':'.($secret['StartLine']??'')]],['Author',['text',false,!empty($secret['Commit']) ? ($secret['Author']??'').' · '.substr($secret['Commit'],0,8) : 'working tree']]] as [$kl,$kv])
                <div>
                  <div style="font-size:10px;color:var(--fg-3);font-weight:600;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:3px;">{{ $kl }}</div>
                  <span @class(['mono' => $kv[0]==='mono']) style="color:{{ $kv[1]==='critical'?'var(--sev-critical)':'var(--fg-1)' }};">{{ $kv[2] }}</span>
                </div>
              @endforeach
            </div>
          </div>
        @empty
          <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--sev-ok);flex-direction:column;gap:8px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
            <span style="font-size:13px;">No secrets detected.</span>
          </div>
        @endforelse
    </div>
</div>

{{-- RULES (Laravel rule pack + CheckMyCode custom rules) --------------- --}}
@elseif ($activeTab === 'rules')
@php
  $ruleFindings = array_merge(
    array_map(fn ($f) => $f + ['_scanner' => 'laravel_rules'], $results['laravel_rules']['findings'] ?? []),
    array_map(fn ($f) => $f + ['_scanner' => 'custom_rules'], $results['custom_rules']['findings'] ?? []),
  );
@endphp
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    @include('livewire.partials.tab-header', ['title' => 'Rule findings', 'count' => $rulesTotal, 'subtitle' => ($isLaravelProject ? 'Laravel rule pack + ' : '').'CheckMyCode custom rules · '.\App\Scanning\ProjectDetector::label($scanProjectType ?? 'unknown'), 'promptTab' => 'rules'])
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
        @include('livewire.partials.gitignored-note')
        @if (!empty($results['suppressed']))
          <details style="padding:12px 16px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:8px;font-size:12px;color:var(--fg-2);line-height:1.5;">
            <summary style="cursor:pointer;">{{ count($results['suppressed']) }} {{ Str::plural('finding', count($results['suppressed'])) }} suppressed by <span class="mono" style="color:var(--fg-1);">checkmycode-ignore</span> comments in your code</summary>
            <ul style="margin:8px 0 0;padding-left:18px;">
              @foreach ($results['suppressed'] as $s)
                <li><span class="mono" style="color:var(--fg-1);">{{ $s['file'] }}:{{ $s['line'] }}</span> <span class="mono" style="color:var(--fg-3);">{{ $s['rule'] }}</span>{{ $s['reason'] ? ': '.$s['reason'] : ' (no reason given)' }}</li>
              @endforeach
            </ul>
          </details>
        @endif
        @forelse ($ruleFindings as $finding)
          @include('livewire.partials.finding-card', ['finding' => $finding, 'scanner' => $finding['_scanner'], 'icon' => '<path d="M12 3 4 8v8l8 5 8-5V8l-8-5Z"/><path d="m4 8 8 5 8-5M12 13v8"/>'])
        @empty
          @include('livewire.partials.empty-state', ['message' => 'No rule findings.'])
        @endforelse
    </div>
</div>

{{-- CONFIG / IaC -------------------------------------------------------- --}}
@elseif ($activeTab === 'config')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    @include('livewire.partials.tab-header', ['title' => 'Configuration & IaC', 'count' => $configTotal, 'subtitle' => 'Trivy · Dockerfile, Kubernetes, Terraform, CloudFormation, Helm', 'promptTab' => 'config'])
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
        @forelse ($results['trivy_config']['findings'] ?? [] as $finding)
          @include('livewire.partials.finding-card', ['finding' => $finding, 'scanner' => 'trivy_config', 'icon' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M4 12h2M18 12h2M12 4v2M12 18v2M6.3 6.3l1.4 1.4M16.3 16.3l1.4 1.4M6.3 17.7l1.4-1.4M16.3 7.7l1.4-1.4"/>'])
        @empty
          @include('livewire.partials.empty-state', ['message' => 'No misconfigurations found.'])
        @endforelse
    </div>
</div>

{{-- WORKFLOWS (GitHub Actions) ------------------------------------------ --}}
@elseif ($activeTab === 'workflows')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    @include('livewire.partials.tab-header', ['title' => 'Workflow security', 'count' => $workflowsTotal, 'subtitle' => 'zizmor · GitHub Actions injection, triggers, permissions, pinning', 'promptTab' => 'workflows'])
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
        @forelse ($results['workflows']['findings'] ?? [] as $finding)
          @include('livewire.partials.finding-card', ['finding' => $finding, 'scanner' => 'workflows', 'icon' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M6 8.5v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2M12 12.5v3"/>'])
        @empty
          @include('livewire.partials.empty-state', ['message' => 'No GitHub Actions workflow issues found.'])
        @endforelse
    </div>
</div>

{{-- LIFECYCLE ------------------------------------------------------------ --}}
@elseif ($activeTab === 'lifecycle')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    @include('livewire.partials.tab-header', ['title' => 'Lifecycle support', 'count' => $lifecycleTotal, 'subtitle' => 'endoflife.date · runtimes and frameworks', 'promptTab' => 'lifecycle'])
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
        @foreach ($results['endoflife']['findings'] ?? [] as $finding)
          @include('livewire.partials.finding-card', ['finding' => $finding, 'scanner' => 'endoflife', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'])
        @endforeach
        @if (!empty($results['endoflife']['components']))
          <div class="m-table" style="--table-min:820px;border:1px solid var(--line-1);border-radius:10px;background:var(--bg-2);overflow:hidden;margin-top:6px;">
            <div style="display:grid;grid-template-columns:180px 120px 1fr 170px 140px;padding:10px 16px;border-bottom:1px solid var(--line-1);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);background:var(--bg-1);">
              <span>Component</span><span>Version</span><span>Status</span><span>Security support ends</span><span>Latest</span>
            </div>
            @foreach ($results['endoflife']['components'] as $component)
              @php $supported = ($component['status'] ?? '') === 'supported'; @endphp
              <div style="display:grid;grid-template-columns:180px 120px 1fr 170px 140px;padding:10px 16px;align-items:center;border-bottom:1px solid var(--line-2);font-size:12px;">
                <span style="color:var(--fg-1);">{{ $component['label'] ?? $component['product'] }}</span>
                <span class="mono" style="color:var(--fg-2);">{{ $component['detected_version'] ?? '' }}</span>
                <span style="color:{{ $supported ? 'var(--sev-ok)' : 'var(--sev-high)' }};">{{ $component['status_label'] ?? '—' }}</span>
                <span class="mono" style="color:var(--fg-3);">{{ $component['security_support_ends_at'] ?? '—' }}</span>
                <span class="mono" style="color:var(--fg-3);">{{ $component['latest_release'] ?? '—' }}</span>
              </div>
            @endforeach
          </div>
        @else
          @include('livewire.partials.empty-state', ['message' => 'No supported runtimes or frameworks detected for lifecycle checks.'])
        @endif
    </div>
</div>

{{-- LICENSES ------------------------------------------------------------- --}}
@elseif ($activeTab === 'licenses')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    @include('livewire.partials.tab-header', ['title' => 'Flagged licenses', 'count' => $licensesTotal, 'subtitle' => 'Trivy · copyleft and restricted licenses (not part of the grade)', 'promptTab' => 'licenses'])
    <div class="m-pad" style="padding:16px 32px 32px;">
      @if (!empty($results['licenses']['findings']))
        <div class="m-table" style="--table-min:760px;border:1px solid var(--line-1);border-radius:10px;background:var(--bg-2);overflow:hidden;">
          <div style="display:grid;grid-template-columns:84px 1fr 200px 140px 1fr;padding:10px 16px;border-bottom:1px solid var(--line-1);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);background:var(--bg-1);">
            <span>Severity</span><span>Package</span><span>License</span><span>Category</span><span>Source</span>
          </div>
          @foreach ($results['licenses']['findings'] as $license)
            @php $lc = ($license['severity'] ?? '') === 'CRITICAL' ? 'var(--sev-critical)' : 'var(--sev-high)'; @endphp
            <div style="display:grid;grid-template-columns:84px 1fr 200px 140px 1fr;padding:10px 16px;align-items:center;gap:8px;border-bottom:1px solid var(--line-2);font-size:12px;">
              <span style="display:inline-flex;font-size:9.5px;font-weight:700;letter-spacing:0.08em;padding:2px 6px;border-radius:3px;color:{{ $lc }};background:color-mix(in srgb,{{ $lc }} 14%,transparent);font-family:var(--font-mono);width:fit-content;">{{ $license['severity'] ?? '' }}</span>
              <span class="mono" style="color:var(--fg-1);">{{ $license['package'] ?? '' }}</span>
              <span class="mono" style="color:var(--fg-2);">{{ $license['license'] ?? '' }}</span>
              <span style="color:var(--fg-3);">{{ $license['category'] ?? '' }}</span>
              <span class="mono" style="color:var(--fg-3);">{{ $license['file_path'] ?? '' }}</span>
            </div>
          @endforeach
        </div>
      @else
        @include('livewire.partials.empty-state', ['message' => 'No flagged licenses.'])
      @endif
    </div>
</div>

{{-- ENV AUDIT ------------------------------------------------------------ --}}
@elseif ($activeTab === 'env_audit')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div class="m-tabhead" style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Environment audit</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $envTotal }} issues</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">.env · variables checked</span>
        @include('livewire.partials.fix-prompt-button', ['tab' => 'env_audit'])
    </div>
    <div class="m-pad" style="padding:16px 32px 32px;">
      @if (!empty($results['env_audit']['local_only']))
        <div style="padding:12px 16px;margin-bottom:12px;background:color-mix(in srgb,var(--sev-low) 8%,transparent);border:1px solid color-mix(in srgb,var(--sev-low) 22%,transparent);border-radius:8px;font-size:12px;color:var(--fg-2);line-height:1.5;">
          Your <span class="mono">.env</span> is gitignored, so these are your <strong style="color:var(--fg-1);">local</strong> settings and they don't affect the grade. Make sure your production environment doesn't use them (for example <span class="mono">APP_DEBUG=true</span>).
        </div>
      @endif
      @if (!empty($results['env_audit']['findings']))
        <div class="m-table" style="--table-min:640px;border:1px solid var(--line-1);border-radius:10px;background:var(--bg-2);overflow:hidden;">
          <div style="display:grid;grid-template-columns:22px 200px 1fr 180px;padding:10px 16px;border-bottom:1px solid var(--line-1);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);background:var(--bg-1);">
            <span></span><span>Key</span><span>Value</span><span>Note</span>
          </div>
          @foreach ($results['env_audit']['findings'] as $row)
            @php
              $efp = ($row['key']??'').':'.($row['line']??'');
              $eig = $this->isIgnored('env_audit',$efp);
              $eok = $row['ok']??false;
              $esevColors = ['CRITICAL'=>'var(--sev-critical)','HIGH'=>'var(--sev-high)','MEDIUM'=>'var(--sev-medium)','INFO'=>'var(--sev-info)'];
              $esc = $esevColors[$row['severity']??'INFO']??'var(--fg-3)';
            @endphp
            <div style="display:grid;grid-template-columns:22px 200px 1fr 180px;padding:10px 16px;align-items:center;gap:8px;border-bottom:1px solid var(--line-2);font-size:12px;opacity:{{ $eig?'0.45':'1' }};">
              @if ($eok)
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-ok);"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
              @else
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:{{ $esc }};"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
              @endif
              <span class="mono" style="color:var(--fg-1);font-weight:500;">{{ $row['key']??'' }}</span>
              <span class="mono" style="color:{{ $eok ? 'var(--fg-2)' : $esc }};">{{ $row['current']??$row['value']??'' }}</span>
              <span style="color:var(--fg-3);font-size:11.5px;">{{ $row['description']??$row['note']??'—' }}</span>
            </div>
          @endforeach
        </div>
      @else
        <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--sev-ok);flex-direction:column;gap:8px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
          <span style="font-size:13px;">Environment configuration looks good!</span>
        </div>
      @endif
    </div>
</div>

{{-- CODE QUALITY --------------------------------------------------------- --}}
@elseif ($activeTab === 'code_quality')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div class="m-tabhead" style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Code quality</h2>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Best practice suggestions</span>
        @include('livewire.partials.fix-prompt-button', ['tab' => 'code_quality'])
    </div>
    <div class="m-pad" style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
      <div style="padding:12px 16px;background:color-mix(in srgb,#c69dff 8%,transparent);border:1px solid color-mix(in srgb,#c69dff 20%,transparent);border-radius:8px;font-size:12px;color:var(--fg-2);">
        These are code quality suggestions, not security issues. They don't affect your security grade.
      </div>
      @forelse ($results['code_quality']['results'] ?? [] as $finding)
        <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;padding:14px 16px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            @php $cqs = $finding['extra']['severity']??'INFO'; $cqColor = $cqs==='ERROR'?'var(--sev-high)':($cqs==='WARNING'?'var(--sev-medium)':'var(--sev-info)'); @endphp
            <span style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 6px;border-radius:3px;color:{{ $cqColor }};background:color-mix(in srgb,{{ $cqColor }} 14%,transparent);border:1px solid color-mix(in srgb,{{ $cqColor }} 24%,transparent);font-family:var(--font-mono);">{{ $cqs==='ERROR'?'NEEDS FIX':($cqs==='WARNING'?'IMPROVE':'SUGGEST') }}</span>
            <span style="font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:3px;color:#c69dff;background:color-mix(in srgb,#c69dff 14%,transparent);font-family:var(--font-mono);">{{ last(explode('.',$finding['check_id']??'')) }}</span>
          </div>
          <p style="font-size:12.5px;color:var(--fg-2);margin:0 0 8px;line-height:1.5;">{{ Str::limit($finding['extra']['message']??'',300) }}</p>
          <div style="font-size:11px;color:var(--fg-3);">
            <span class="mono">{{ $finding['path']??'' }}:{{ $finding['start']['line']??'' }}</span>
          </div>
        </div>
      @empty
        <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--sev-ok);flex-direction:column;gap:8px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
          <span style="font-size:13px;">Code quality looks great!</span>
        </div>
      @endforelse
    </div>
</div>

@endif {{-- end tab content --}}
@endif {{-- end showSetup --}}

</main>
</div>{{-- end body row --}}

@if ($webMode)
{{-- ── Web: drop zone + upload errors ──────────────────────────────── --}}
<div x-show="dragging" x-cloak><div style="position:absolute;inset:0;z-index:40;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--bg-0) 80%,transparent);backdrop-filter:blur(3px);pointer-events:none;">
    <div style="padding:36px 48px;border:2px dashed var(--accent);border-radius:16px;text-align:center;background:var(--bg-2);">
        <div class="serif" style="font-size:26px;color:var(--fg-1);margin-bottom:6px;">Drop a folder or files to scan</div>
        <div style="font-size:12px;color:var(--fg-3);">Excluded folders are skipped in your browser. Nothing is stored.</div>
    </div>
</div>
</div>
<div x-show="error" x-cloak><div style="position:absolute;left:50%;bottom:24px;transform:translateX(-50%);z-index:45;width:max-content;max-width:min(560px,calc(100vw - 32px));display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--bg-2);border:1px solid color-mix(in srgb,var(--sev-critical) 40%,var(--line-1));border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,0.4);font-size:12.5px;color:var(--fg-1);">
    <span style="color:var(--sev-critical);">✗</span>
    <span x-text="error" style="flex:1;"></span>
    <button type="button" @click="error = null" style="color:var(--fg-3);">×</button>
</div>
</div>
@endif

@if ($webMode)
<div x-show="showPrivacy" x-cloak><div @keydown.escape.window="showPrivacy = false" @click.self="showPrivacy = false" style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
    <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:14px;padding:26px 28px;width:520px;max-width:calc(100vw - 32px);box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-ok);flex-shrink:0;"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <h3 class="serif" style="font-size:22px;font-weight:400;color:var(--fg-1);margin:0;">What happens to my code</h3>
        </div>
        <ol style="margin:0 0 16px;padding-left:20px;font-size:12.5px;color:var(--fg-2);line-height:1.65;display:flex;flex-direction:column;gap:6px;">
            <li><strong style="color:var(--fg-1);">In your browser:</strong> excluded folders (<span class="mono">node_modules</span>, <span class="mono">vendor</span>, <span class="mono">.git</span>, …), media and large binaries are filtered out. They never leave your machine.</li>
            <li><strong style="color:var(--fg-1);">Your browser asks first:</strong> when you pick a folder, your browser shows its own confirmation ("View files" or "Upload files … only do this if you trust the site"). That message comes from the browser and can't be changed by websites. It's there to protect you. Prefer no prompt? Drag the folder onto the page instead.</li>
            <li><strong style="color:var(--fg-1);">Sent over HTTPS:</strong> the remaining files are compressed and sent to the scanner for this one scan.</li>
            <li><strong style="color:var(--fg-1);">Scanned in a temporary workspace:</strong> open-source scanners (Trivy, OSV-Scanner, Semgrep, Gitleaks, zizmor) run against it. Nothing is shared with third parties; only package names and versions are checked against public vulnerability databases.</li>
            <li><strong style="color:var(--fg-1);">Deleted immediately:</strong> the workspace is deleted the moment the scan finishes, whether it succeeds or fails. Your summary shows the exact time.</li>
            <li><strong style="color:var(--fg-1);">Findings expire:</strong> the results (including short code snippets around each finding) are kept for {{ $webLimits['ttlMinutes'] }} minutes so you can view them, visible only to this browser, then they're gone. Secret values are masked.</li>
        </ol>
        <p style="font-size:12px;color:var(--fg-3);margin:0 0 18px;line-height:1.6;">No account, no database of projects. The only thing counted is the number of scans. Want nothing to leave your machine at all? Use the desktop app or CLI: same scanner, fully local.</p>
        <div style="display:flex;justify-content:flex-end;">
            <a href="{{ route('terms') }}" target="_blank" style="margin-right:auto;align-self:center;font-size:12px;color:var(--fg-2);">Read the full Terms</a>
            <button type="button" @click="showPrivacy = false" style="padding:8px 18px;font-size:12px;color:#fff;background:var(--accent);border-radius:8px;font-weight:600;">Got it</button>
        </div>
    </div>
</div>
</div>
@endif

@if ($webMode)
    @include('livewire.partials.welcome-modals')
@endif

{{-- ── Ignore modal ─────────────────────────────────────────────────── --}}
@if ($pendingIgnoreKey)
<div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
    <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:14px;padding:24px;width:384px;max-width:calc(100vw - 32px);box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <h3 style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0 0 8px;">Ignore this finding?</h3>
        <p style="font-size:12px;color:var(--fg-3);margin:0 0 16px;line-height:1.5;">This finding will still appear in future scans but will be labeled as ignored.</p>
        <label style="font-size:11.5px;color:var(--fg-3);display:block;margin-bottom:6px;">Reason (optional)</label>
        <input type="text" wire:model="ignoreReason" wire:keydown.enter="confirmIgnore"
            placeholder="e.g. Test fixture, false positive, accepted risk…"
            style="width:100%;padding:8px 12px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:8px;font-size:13px;color:var(--fg-1);outline:none;margin-bottom:16px;"
            autofocus />
        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <button wire:click="cancelIgnore" style="padding:8px 16px;font-size:12px;color:var(--fg-2);background:var(--bg-3);border:1px solid var(--line-1);border-radius:8px;">Cancel</button>
            <button wire:click="confirmIgnore" style="padding:8px 16px;font-size:12px;color:#fff;background:var(--sev-medium);border-radius:8px;font-weight:600;">Ignore Finding</button>
        </div>
    </div>
</div>
@endif

</div>{{-- end root --}}

@script
<script>
    $wire.on('scroll-log', () => {
        const container = document.getElementById('log-container');
        if (container) container.scrollTop = container.scrollHeight;
    });
</script>
@endscript
