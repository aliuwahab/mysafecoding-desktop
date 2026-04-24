<div style="width:100vw;height:100vh;background:var(--bg-0);color:var(--fg-1);display:flex;flex-direction:column;overflow:hidden;" wire:poll.500ms="pollLogs">

{{-- ── Title Bar ──────────────────────────────────────────────────── --}}
<div style="height:44px;flex-shrink:0;background:var(--bg-0);border-bottom:1px solid var(--line-1);display:grid;grid-template-columns:1fr auto 1fr;align-items:center;padding:0 14px;gap:12px;user-select:none;" class="drag" data-tauri-drag-region>

    {{-- Left: traffic lights + sidebar toggle --}}
    <div style="display:flex;align-items:center;gap:14px;">
        <div class="traffic" style="-webkit-app-region:no-drag;">
            <i class="c"></i><i class="m"></i><i class="x"></i>
        </div>
        <button wire:click="newScan" title="New scan" style="color:var(--fg-3);display:flex;align-items:center;padding:4px;" class="no-drag">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
        </button>
        <div style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--fg-3);">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><circle cx="6" cy="18" r="2"/><path d="M6 8v8M18 8a6 6 0 0 1-6 6H8"/></svg>
            <span class="mono">main</span>
        </div>
    </div>

    {{-- Center: breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;padding:5px 10px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;min-width:280px;max-width:480px;font-size:12px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);flex-shrink:0;"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
        <span class="mono" style="color:var(--fg-2);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            {{ $projectPath ?: '~/Code/…' }}
        </span>
        @if ($projectPath && isset($results['project_type']) && $results['project_type'] !== 'unknown')
            <span style="font-size:10px;font-weight:600;color:var(--accent);padding:1px 6px;border-radius:4px;background:color-mix(in srgb,var(--accent) 14%,transparent);letter-spacing:0.04em;">{{ strtoupper($results['project_type']) }}</span>
        @elseif ($projectPath)
            <span style="font-size:10px;font-weight:600;color:var(--accent);padding:1px 6px;border-radius:4px;background:color-mix(in srgb,var(--accent) 14%,transparent);letter-spacing:0.04em;">LARAVEL</span>
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
        <div style="width:1px;height:16px;background:var(--line-1);"></div>
        <button wire:click="exportJson" title="Export JSON" style="color:var(--fg-3);padding:4px;display:flex;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        </button>
    </div>
</div>

{{-- ── Body row ─────────────────────────────────────────────────────── --}}
<div style="flex:1;display:flex;min-height:0;">

{{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
<aside style="width:248px;flex-shrink:0;background:var(--bg-0);border-right:1px solid var(--line-1);display:flex;flex-direction:column;height:100%;overflow:hidden;">

    {{-- Brand --}}
    <div style="padding:16px 16px 10px;display:flex;align-items:center;gap:9px;">
        <div style="width:26px;height:26px;border-radius:7px;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 16%,transparent);border:1px solid color-mix(in srgb,var(--accent) 28%,transparent);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);"><path d="M12 2 4 5v7c0 4.5 3.4 8.3 8 10 4.6-1.7 8-5.5 8-10V5l-8-3Z"/></svg>
        </div>
        <div style="display:flex;flex-direction:column;line-height:1;">
            <span class="serif" style="font-size:19px;color:var(--fg-1);letter-spacing:-0.01em;">Laraguard</span>
            <span style="font-size:10.5px;color:var(--fg-3);margin-top:2px;letter-spacing:0.04em;">v1.1 · local scanner</span>
        </div>
    </div>

    {{-- CTAs --}}
    <div style="padding:4px 12px 12px;display:flex;flex-direction:column;gap:6px;">
        <button wire:click="newScan" style="height:32px;padding:0 12px;display:flex;align-items:center;justify-content:space-between;gap:8px;background:var(--accent);color:#fff;border-radius:7px;font-weight:600;font-size:12.5px;box-shadow:0 1px 0 rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.15);">
            <span style="display:flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                New scan
            </span>
            <span style="display:flex;gap:2px;">
                <span class="kbd" style="background:rgba(0,0,0,0.25);border-color:rgba(255,255,255,0.18);color:rgba(255,255,255,0.85);">⌘</span>
                <span class="kbd" style="background:rgba(0,0,0,0.25);border-color:rgba(255,255,255,0.18);color:rgba(255,255,255,0.85);">N</span>
            </span>
        </button>
        <button wire:click="openComprehensiveScan" style="height:30px;padding:0 10px;display:flex;align-items:center;gap:8px;color:var(--fg-2);border:1px dashed var(--line-1);border-radius:7px;font-size:11.5px;justify-content:center;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-hi);"><path d="M12 3v5M12 16v5M3 12h5M16 12h5M6 6l3 3M15 15l3 3M6 18l3-3M15 9l3-3"/></svg>
            <span>Comprehensive scan</span>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-4);"><path d="m9 6 6 6-6 6"/></svg>
        </button>
    </div>

    {{-- History list --}}
    <div class="scroll" style="flex:1;overflow-y:auto;padding:0 8px 12px;">
        @php
            $allScans = $this->history;
            $today = now()->startOfDay();
            $grouped = ['Today' => [], 'Earlier this week' => [], 'Older' => []];
            foreach ($allScans as $s) {
                if ($s->created_at->gte($today)) $grouped['Today'][] = $s;
                elseif ($s->created_at->gte($today->copy()->subDays(5))) $grouped['Earlier this week'][] = $s;
                else $grouped['Older'][] = $s;
            }
        @endphp
        @foreach ($grouped as $label => $items)
            @if (count($items) > 0)
                <div style="margin-bottom:10px;">
                    <div style="padding:6px 10px 4px;font-size:9.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--fg-3);">{{ $label }}</div>
                    @foreach ($items as $scan)
                        @php
                            $sr = $scan->results['summary'] ?? [];
                            $sc = ($sr['trivy']['CRITICAL'] ?? 0) + ($sr['laravel_rules']['CRITICAL'] ?? 0) + ($sr['gitleaks_total'] ?? 0);
                            $sh = ($sr['trivy']['HIGH'] ?? 0) + ($sr['semgrep']['ERROR'] ?? 0) + ($sr['laravel_rules']['HIGH'] ?? 0);
                            $sm = ($sr['trivy']['MEDIUM'] ?? 0) + ($sr['semgrep']['WARNING'] ?? 0) + ($sr['laravel_rules']['MEDIUM'] ?? 0);
                            $sg = $scan->status === 'completed' ? ($sc > 0 ? 'F' : ($sh > 3 ? 'D' : ($sh > 0 ? 'C' : ($sm > 0 ? 'B' : 'A')))) : '-';
                            $gradeVars = ['A'=>'--grade-a','B'=>'--grade-b','C'=>'--grade-c','D'=>'--grade-d','F'=>'--grade-f','-'=>'--fg-4'];
                            $gc = 'var(' . ($gradeVars[$sg] ?? '--fg-4') . ')';
                            $dotColor = match($scan->status) { 'completed'=>'var(--sev-ok)', 'running'=>'var(--accent)', 'failed'=>'var(--sev-critical)', default=>'var(--fg-4)' };
                            $isSelected = $selectedHistoryId === $scan->id;
                        @endphp
                        <div wire:click="loadScan({{ $scan->id }})"
                             style="padding:8px 10px;border-radius:7px;background:{{ $isSelected ? 'var(--bg-3)' : 'transparent' }};cursor:pointer;position:relative;transition:background 120ms;"
                             onmouseenter="if(!{{ $isSelected ? 'true' : 'false' }})this.style.background='var(--bg-2)'"
                             onmouseleave="if(!{{ $isSelected ? 'true' : 'false' }})this.style.background='transparent'">
                            @if ($isSelected)
                                <div style="position:absolute;left:-8px;top:10px;bottom:10px;width:2px;border-radius:2px;background:var(--accent);"></div>
                            @endif
                            <div style="display:flex;align-items:center;gap:7px;">
                                <span style="width:5px;height:5px;border-radius:50%;background:{{ $dotColor }};flex-shrink:0;display:block;"></span>
                                <span class="mono" style="flex:1;font-size:11.5px;color:var(--fg-1);font-weight:{{ $isSelected ? '600' : '500' }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ basename($scan->project_path) }}</span>
                                <span style="width:18px;height:18px;border-radius:4px;display:grid;place-items:center;font-size:10.5px;font-weight:700;color:{{ $gc }};background:color-mix(in srgb,{{ $gc }} 14%,transparent);font-family:var(--font-mono);">{{ $sg }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:3px;padding-left:12px;">
                                <span style="font-size:10.5px;color:var(--fg-3);">{{ $scan->created_at->diffForHumans() }}</span>
                                @if ($scan->findings_count > 0)
                                    <span style="font-size:10.5px;color:var(--fg-3);">· {{ $scan->findings_count }} findings</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>

    {{-- Footer --}}
    <div style="padding:10px 14px;border-top:1px solid var(--line-1);display:flex;align-items:center;justify-content:space-between;font-size:10.5px;color:var(--fg-3);">
        <span style="display:flex;align-items:center;gap:5px;">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--sev-ok);display:block;"></span>
            All engines ready
        </span>
        <span class="mono">php 8.4</span>
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
            <p style="font-size:13px;color:var(--fg-3);margin:0;">Laraguard needs the following tools installed:</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
            @foreach (['trivy', 'semgrep', 'gitleaks', 'jq'] as $tool)
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
            <button wire:click="installTools" style="flex:1;padding:9px 16px;background:var(--accent);color:#fff;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 1px 0 rgba(0,0,0,0.15);">Install via Homebrew</button>
            <button wire:click="recheckTools" style="padding:9px 14px;background:var(--bg-3);color:var(--fg-2);border-radius:8px;font-size:13px;border:1px solid var(--line-1);">Recheck</button>
        </div>
    </div>
</div>

@else
{{-- ── Scan Config Bar ───────────────────────────────────────────── --}}
<div style="padding:12px 20px;border-bottom:1px solid var(--line-1);background:var(--bg-1);display:flex;align-items:center;gap:12px;">
    <button wire:click="selectFolder" style="display:flex;align-items:center;gap:8px;padding:6px 11px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-hi);"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2H3"/><path d="m3 9 1.5 9a2 2 0 0 0 2 2H19a2 2 0 0 0 2-2l1-7H5"/></svg>
        <span class="mono" style="color:var(--fg-1);font-weight:500;">{{ $projectPath ? basename($projectPath) : 'Select Folder' }}</span>
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-3);"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <div style="flex:1;min-width:0;overflow:hidden;display:flex;align-items:center;gap:6px;flex-wrap:nowrap;">
        <span style="font-size:10.5px;color:var(--fg-3);text-transform:uppercase;letter-spacing:0.08em;margin-right:2px;white-space:nowrap;">Exclude</span>
        @foreach ($exclusions as $index => $exc)
            <span class="mono" style="display:inline-flex;align-items:center;gap:4px;padding:3px 4px 3px 8px;font-size:11px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);white-space:nowrap;">
                {{ $exc }}
                <button wire:click="removeExclusion({{ $index }})" style="color:var(--fg-4);display:flex;padding:1px;border-radius:3px;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </span>
        @endforeach
        <input wire:model="newExclusion" wire:keydown.enter="addExclusion" placeholder="+ add"
            style="width:70px;font-family:var(--font-mono);font-size:11px;padding:3px 6px;background:transparent;border:1px dashed var(--line-1);border-radius:5px;outline:none;color:var(--fg-1);" />
    </div>

    @if ($status === 'running')
        <button wire:click="$set('status','idle')" style="display:flex;align-items:center;gap:7px;padding:0 14px;height:32px;background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;font-size:12px;color:var(--fg-1);">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sev-critical);"><rect x="6" y="6" width="12" height="12" rx="1.5" fill="currentColor"/></svg>
            Stop
        </button>
    @else
        <button wire:click="startScan" @disabled(!$projectPath) style="display:flex;align-items:center;gap:7px;padding:0 16px;height:32px;background:var(--accent);color:#fff;border-radius:7px;font-size:12px;font-weight:600;box-shadow:0 1px 0 rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.15);">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v16l14-8L6 4Z" fill="currentColor"/></svg>
            Scan
            <span class="kbd" style="background:rgba(0,0,0,0.25);border-color:rgba(255,255,255,0.18);color:rgba(255,255,255,0.85);">⌘R</span>
        </button>
    @endif
</div>

{{-- ── Tab Bar ──────────────────────────────────────────────────────── --}}
@php
    $summary = $this->results['summary'] ?? [];
    $trivyTotal = $summary['trivy_total'] ?? 0;
    $semgrepTotal = $summary['semgrep_total'] ?? 0;
    $gitleaksTotal = $summary['gitleaks_total'] ?? 0;
    $laravelTotal = $summary['laravel_rules_total'] ?? 0;
    $envTotal = $summary['env_audit_total'] ?? 0;
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
        ['id'=>'laravel_rules','label'=>'Laravel',      'count'=>$laravelTotal, 'color'=>'var(--accent-hi)',
         'icon'=>'<path d="M12 3 4 8v8l8 5 8-5V8l-8-5Z"/><path d="m4 8 8 5 8-5M12 13v8"/>'],
        ['id'=>'env_audit',    'label'=>'.env',         'count'=>$envTotal,     'color'=>'var(--sev-ok)',
         'icon'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/>'],
    ];
@endphp
<div style="display:flex;align-items:stretch;border-bottom:1px solid var(--line-1);background:var(--bg-1);padding-left:12px;position:relative;">
    @foreach ($tabs as $tab)
        @php $sel = $activeTab === $tab['id']; @endphp
        <button wire:click="$set('activeTab','{{ $tab['id'] }}')" style="position:relative;display:flex;align-items:center;gap:7px;padding:10px 13px;font-size:12px;font-weight:500;color:{{ $sel ? 'var(--fg-1)' : 'var(--fg-3)' }};transition:color 160ms;">
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
    <button wire:click="exportJson" style="display:flex;align-items:center;gap:6px;padding:6px 12px;margin:6px 12px 6px 0;font-size:11.5px;color:var(--fg-2);background:var(--bg-2);border:1px solid var(--line-1);border-radius:6px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        Export JSON
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
            <div class="log-in" style="display:grid;grid-template-columns:80px 84px 1fr;gap:14px;padding:2px 20px;font-family:var(--font-mono);font-size:11.5px;line-height:1.7;align-items:baseline;">
                <span style="color:var(--fg-4);text-align:right;">{{ $log['time'] }}</span>
                <span style="display:inline-block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;padding:1px 6px;border-radius:3px;background:{{ $s['bg'] }};color:{{ $s['fg'] }};text-align:center;">{{ $log['scanner'] }}</span>
                <span style="color:{{ $log['level'] === 'error' ? 'var(--sev-critical)' : ($log['level'] === 'warn' ? 'var(--fg-1)' : 'var(--fg-2)') }};word-break:break-word;">
                    @if ($log['level'] === 'warn') <span style="color:var(--sev-medium);margin-right:6px;">⚠</span> @endif
                    @if ($log['level'] === 'error') <span style="color:var(--sev-critical);margin-right:6px;">✗</span> @endif
                    {{ $log['line'] }}
                </span>
            </div>
        @empty
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--fg-4);font-size:12px;">
                <span>Select a project and press <span class="kbd" style="margin:0 3px;">⌘R</span> to scan.</span>
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
    $grandTotal = $trivyTotal + $semgrepTotal + $gitleaksTotal + $laravelTotal + $envTotal;
@endphp
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
  <div style="max-width:1180px;margin:0 auto;padding:28px 32px 40px;">

    {{-- Headline row --}}
    <div style="display:grid;grid-template-columns:200px 1fr 260px;gap:20px;align-items:stretch;margin-bottom:22px;">

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
              <span style="color:var(--accent);">{{ $grandTotal }} findings</span> across five engines.
              Address <span style="color:{{ $gradeColor }};">{{ $gitleaksTotal + ($summary['trivy']['CRITICAL'] ?? 0) + ($summary['laravel_rules']['CRITICAL'] ?? 0) }} blockers</span> before deploy.
            @endif
          </div>
        </div>
        @php $ignoredStats = $this->ignoredStats; @endphp
        <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line-2);display:flex;align-items:center;gap:20px;font-size:11.5px;color:var(--fg-3);">
          @if ($selectedHistoryId)
            @php $scanRecord = \App\Models\Scan::find($selectedHistoryId); @endphp
            @if ($scanRecord)
              <span>Finished {{ $scanRecord->completed_at?->format('H:i') ?? '—' }}
              @if ($scanRecord->completed_at && $scanRecord->started_at)
                · took {{ $scanRecord->started_at->diffInSeconds($scanRecord->completed_at) }}s
              @endif
              </span>
            @endif
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
          @foreach ([['Trivy', $trivyTotal, 'var(--sev-low)'], ['Semgrep', $semgrepTotal, '#c69dff'], ['Gitleaks', $gitleaksTotal, 'var(--sev-critical)'], ['Laravel', $laravelTotal, 'var(--accent-hi)'], ['.env', $envTotal, 'var(--sev-ok)']] as [$el, $ev, $ec])
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
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:22px;">
      @php
        $engineCards = [
          ['Dependencies', $trivyTotal, 'var(--sev-low)', 'Trivy · SCA',
           [['CRIT',$summary['trivy']['CRITICAL']??0],['HIGH',$summary['trivy']['HIGH']??0],['MED',$summary['trivy']['MEDIUM']??0],['LOW',$summary['trivy']['LOW']??0]]],
          ['Code Analysis', $semgrepTotal, '#c69dff', 'Semgrep · SAST',
           [['ERR',$summary['semgrep']['ERROR']??0],['WARN',$summary['semgrep']['WARNING']??0],['INFO',$summary['semgrep']['INFO']??0]]],
          ['Secrets', $gitleaksTotal, 'var(--sev-critical)', 'Gitleaks',
           [['LEAKED',$gitleaksTotal]]],
          ['Laravel Rules', $laravelTotal, 'var(--accent-hi)', '20 custom rules',
           [['CRIT',$summary['laravel_rules']['CRITICAL']??0],['HIGH',$summary['laravel_rules']['HIGH']??0],['MED',$summary['laravel_rules']['MEDIUM']??0]]],
          ['.env Audit', $envTotal, 'var(--sev-ok)', '32 vars checked',
           [['HIGH',$summary['env_audit']['HIGH']??0],['MED',$summary['env_audit']['MEDIUM']??0],['INFO',$summary['env_audit']['INFO']??0]]],
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

    {{-- Severity distribution + diff panel --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

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
            ['Laravel Rules', [['CRITICAL',$summary['laravel_rules']['CRITICAL']??0,'var(--sev-critical)'],['HIGH',$summary['laravel_rules']['HIGH']??0,'var(--sev-high)'],['MEDIUM',$summary['laravel_rules']['MEDIUM']??0,'var(--sev-medium)']]],
          ];
          $maxSevRow = max([...array_map(fn($r) => array_sum(array_column($r[1],1)), $sevRows), 1]);
        @endphp
        @foreach ($sevRows as [$rl, $rd])
          @php $rt = array_sum(array_column($rd,1)); $pct = ($rt/$maxSevRow)*100; @endphp
          <div style="margin-bottom:12px;display:grid;grid-template-columns:170px 1fr 40px;align-items:center;gap:12px;">
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
          @foreach ([['Total',$scanDiff['total']],['Trivy',$scanDiff['trivy']],['Semgrep',$scanDiff['semgrep']],['Gitleaks',$scanDiff['gitleaks']],['Laravel',$scanDiff['laravel_rules']],['.env',$scanDiff['env_audit']]] as [$dl,$dd])
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
              <button wire:click="compareScan({{ $cs->id }})" style="padding:8px 10px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:7px;font-size:11.5px;color:var(--fg-2);text-align:left;">
                {{ $cs->created_at->format('M j, H:i') }} — {{ $cs->findings_count }} findings
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
    <div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Dependency vulnerabilities</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $trivyTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Trivy · composer.lock + package-lock.json</span>
    </div>
    <div style="padding:4px 32px 32px;">
      @if (!empty($results['trivy']['Results']))
        <div style="border:1px solid var(--line-1);border-radius:10px;background:var(--bg-2);overflow:hidden;">
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
                <span style="display:flex;align-items:center;gap:6px;">
                  @php $eco = str_contains(strtolower($result['Type']??''), 'composer') ? 'PHP' : 'JS'; $ecoColor = $eco==='PHP' ? 'var(--accent-hi)' : 'var(--sev-low)'; $ecoBg = $eco==='PHP' ? 'rgba(245,48,3,0.14)' : 'rgba(111,179,255,0.14)'; @endphp
                  <span style="font-size:9px;font-weight:700;letter-spacing:0.06em;padding:1px 4px;border-radius:3px;background:{{ $ecoBg }};color:{{ $ecoColor }};font-family:var(--font-mono);">{{ $eco }}</span>
                  <span class="mono" style="color:var(--fg-1);">{{ $vuln['PkgName']??'' }}</span>
                </span>
                <span class="mono" style="color:var(--fg-3);">{{ $vuln['InstalledVersion']??'' }}</span>
                <span class="mono" style="color:var(--sev-ok);">→ {{ $vuln['FixedVersion']??'N/A' }}</span>
                <span style="color:var(--fg-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $vuln['Title']??$vuln['Description']??'' }}</span>
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
    <div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Static analysis findings</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $semgrepTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Semgrep · PHP files · rules</span>
    </div>
    <div style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:12px;">
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
    <div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Leaked secrets</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $gitleaksTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Gitleaks · full git history</span>
    </div>
    <div style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:12px;">
        @forelse ($results['gitleaks'] ?? [] as $secret)
          @php
            $gfp = ($secret['RuleID']??'').':'.($secret['File']??'').':'.($secret['StartLine']??'');
            $gig = $this->isIgnored('gitleaks',$gfp);
          @endphp
          <div style="background:var(--bg-2);border:1px solid color-mix(in srgb,var(--sev-critical) 28%,var(--line-1));border-radius:10px;padding:14px 16px;position:relative;overflow:hidden;opacity:{{ $gig?'0.45':'1' }};">
            <div style="position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--sev-critical);"></div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
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
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;font-size:11px;">
              @foreach ([['Rule',['mono',true,$secret['RuleID']??'']],['Match',['mono','critical',$secret['Match']??'']],['File',['mono',false,($secret['File']??'').':'.($secret['StartLine']??'')]],['Author',['text',false,($secret['Author']??'').' · '.substr($secret['Commit']??'',0,8)]]] as [$kl,$kv])
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

{{-- LARAVEL RULES -------------------------------------------------------- --}}
@elseif ($activeTab === 'laravel_rules')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Laravel-specific rules</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $laravelTotal }} results</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">20 custom rules · ORM, routing, config</span>
    </div>
    <div style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
        @forelse ($results['laravel_rules']['findings'] ?? [] as $finding)
          @php
            $lfp = ($finding['rule_id']??'').':'.($finding['file_path']??'').':'.($finding['line_start']??'');
            $lig = $this->isIgnored('laravel_rules',$lfp);
            $lsevColors = ['CRITICAL'=>'var(--sev-critical)','HIGH'=>'var(--sev-high)','MEDIUM'=>'var(--sev-medium)'];
            $lsc = $lsevColors[$finding['severity']??'MEDIUM']??'var(--fg-3)';
          @endphp
          <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;padding:14px 16px;display:flex;align-items:flex-start;gap:14px;opacity:{{ $lig?'0.45':'1' }};">
            <div style="width:34px;height:34px;flex-shrink:0;border-radius:7px;background:color-mix(in srgb,var(--accent) 14%,transparent);border:1px solid color-mix(in srgb,var(--accent) 26%,transparent);display:grid;place-items:center;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-hi);"><path d="M12 3 4 8v8l8 5 8-5V8l-8-5Z"/><path d="m4 8 8 5 8-5M12 13v8"/></svg>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                <span style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 6px;border-radius:3px;color:{{ $lsc }};background:color-mix(in srgb,{{ $lsc }} 14%,transparent);border:1px solid color-mix(in srgb,{{ $lsc }} 24%,transparent);font-family:var(--font-mono);">{{ $finding['severity']??'MEDIUM' }}</span>
                <span class="mono" style="font-size:11px;color:var(--fg-3);">{{ $finding['rule_id']??'' }}</span>
                <span style="font-size:13px;color:var(--fg-1);font-weight:600;">{{ $finding['title']??'' }}</span>
              </div>
              <div style="font-size:12.5px;color:var(--fg-2);line-height:1.5;margin-top:4px;">{{ $finding['description']??'' }}</div>
              <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--fg-3);margin-top:6px;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                <span class="mono" style="color:var(--fg-2);">{{ $finding['file_path']??'' }}:{{ $finding['line_start']??'' }}</span>
              </div>
              @if (!empty($finding['code_snippet']))
                <pre style="margin-top:8px;padding:8px 12px;background:var(--log-bg);border:1px solid var(--line-2);border-radius:6px;font-size:11.5px;color:var(--fg-1);overflow-x:auto;white-space:pre-wrap;">{{ $finding['code_snippet'] }}</pre>
              @endif
              @if (!empty($finding['fix_guidance']))
                <div style="margin-top:6px;padding:8px 12px;background:color-mix(in srgb,var(--sev-ok) 8%,transparent);border:1px solid color-mix(in srgb,var(--sev-ok) 20%,transparent);border-radius:6px;font-size:11.5px;color:var(--sev-ok);">
                  <strong>Fix:</strong> {{ $finding['fix_guidance'] }}
                </div>
              @endif
            </div>
            @if ($lig)
              <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                <span style="font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:3px;color:var(--sev-medium);background:color-mix(in srgb,var(--sev-medium) 14%,transparent);" title="{{ $this->getIgnoreReason('laravel_rules',$lfp) }}">IGNORED</span>
                <button wire:click="unignoreFinding('laravel_rules','{{ $lfp }}')" style="font-size:11px;padding:4px 8px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);">Remove</button>
              </div>
            @else
              <button wire:click="promptIgnore('laravel_rules:{{ $lfp }}')" style="font-size:11px;padding:4px 10px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);flex-shrink:0;">Ignore</button>
            @endif
          </div>
        @empty
          <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--sev-ok);flex-direction:column;gap:8px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>
            <span style="font-size:13px;">No Laravel-specific issues found.</span>
          </div>
        @endforelse
    </div>
</div>

{{-- ENV AUDIT ------------------------------------------------------------ --}}
@elseif ($activeTab === 'env_audit')
<div class="scroll" style="flex:1;overflow-y:auto;background:var(--bg-1);">
    <div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Environment audit</h2>
        <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $envTotal }} issues</span>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">.env · variables checked</span>
    </div>
    <div style="padding:16px 32px 32px;">
      @if (!empty($results['env_audit']['findings']))
        <div style="border:1px solid var(--line-1);border-radius:10px;background:var(--bg-2);overflow:hidden;">
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
    <div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
        <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">Code quality</h2>
        <span style="flex:1;"></span>
        <span style="font-size:11.5px;color:var(--fg-3);">Best practice suggestions</span>
    </div>
    <div style="padding:16px 32px 32px;display:flex;flex-direction:column;gap:10px;">
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

{{-- ── Ignore modal ─────────────────────────────────────────────────── --}}
@if ($pendingIgnoreKey)
<div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
    <div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:14px;padding:24px;width:384px;box-shadow:0 20px 60px rgba(0,0,0,0.4);">
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