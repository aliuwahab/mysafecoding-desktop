<div class="flex h-screen" wire:poll.500ms="pollLogs">

    {{-- Sidebar --}}
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        {{-- App Header / Drag Region --}}
        <div class="h-12 flex items-center px-4 border-b border-slate-800 drag" style="-webkit-app-region: drag;">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-md bg-emerald-500 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <span class="font-semibold text-sm text-white tracking-tight">LaraGuard</span>
            </div>
        </div>

        {{-- New Scan Button --}}
        <div class="p-3">
            <button wire:click="newScan" class="w-full px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-lg transition-colors">
                + New Scan
            </button>
            <button wire:click="openComprehensiveScan" class="mt-2 w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-sm font-medium rounded-lg transition-all shadow-lg shadow-violet-500/20">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                Comprehensive Scan
            </button>
        </div>

        {{-- Scan History --}}
        <div class="flex-1 overflow-y-auto px-3 pb-3">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold mb-2 px-1">History</p>
            @foreach ($this->history as $scan)
                <div
                    class="w-full text-left px-3 py-2.5 rounded-lg mb-1 transition-colors group
                        {{ $selectedHistoryId === $scan->id ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-300' }}"
                >
                    <div class="flex items-center justify-between">
                        <button wire:click="loadScan({{ $scan->id }})" class="text-xs font-mono truncate flex-1 text-left">
                            {{ basename($scan->project_path) }}
                        </button>
                        <button wire:click="deleteScan({{ $scan->id }})" class="opacity-0 group-hover:opacity-100 text-slate-600 hover:text-red-400 ml-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        @if ($scan->status === 'completed')
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        @elseif ($scan->status === 'running')
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        @elseif ($scan->status === 'failed')
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                        @else
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                        @endif
                        <span class="text-[10px] text-slate-500">{{ $scan->created_at->diffForHumans() }}</span>
                        @if ($scan->findings_count > 0)
                            <span class="text-[10px] text-amber-400">{{ $scan->findings_count }} findings</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 flex flex-col min-w-0">
        {{-- Title Bar Drag Region --}}
        <div class="h-12 bg-slate-900/50 border-b border-slate-800 flex items-center justify-between px-4" style="-webkit-app-region: drag;">
            <div class="flex items-center gap-3 no-drag" style="-webkit-app-region: no-drag;">
                @if ($projectPath)
                    <span class="text-xs font-mono text-slate-400 bg-slate-800 px-2 py-1 rounded">{{ $projectPath }}</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if ($status === 'running')
                    <span class="text-xs text-amber-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Scanning...
                    </span>
                @elseif ($status === 'completed')
                    <span class="text-xs text-emerald-400">Scan complete</span>
                @elseif ($status === 'failed')
                    <span class="text-xs text-red-400">Scan failed</span>
                @endif
            </div>
        </div>

        @if ($showSetup)
            {{-- Setup Screen --}}
            <div class="flex-1 flex items-center justify-center p-8">
                <div class="max-w-md w-full bg-slate-900 rounded-xl border border-slate-800 p-8">
                    <div class="text-center mb-6">
                        <div class="w-12 h-12 rounded-full bg-amber-500/10 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-semibold text-white">Setup Required</h2>
                        <p class="text-sm text-slate-400 mt-2">LaraGuard needs the following tools installed:</p>
                    </div>

                    <div class="space-y-2 mb-6">
                        @php $allTools = ['trivy', 'semgrep', 'gitleaks', 'jq']; @endphp
                        @foreach ($allTools as $tool)
                            <div class="flex items-center justify-between px-3 py-2 bg-slate-800 rounded-lg">
                                <span class="text-sm font-mono text-slate-300">{{ $tool }}</span>
                                @if (in_array($tool, $missingTools))
                                    <span class="text-xs text-red-400">Missing</span>
                                @else
                                    <span class="text-xs text-emerald-400">Installed</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-2">
                        <button wire:click="installTools" class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-lg transition-colors">
                            Install via Homebrew
                        </button>
                        <button wire:click="recheckTools" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm rounded-lg transition-colors">
                            Recheck
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{-- Scanner UI --}}
            <div class="flex-1 flex flex-col overflow-hidden">

                {{-- Scan Config Bar --}}
                <div class="bg-slate-900/30 border-b border-slate-800 p-4">
                    <div class="flex items-center gap-3">
                        {{-- Folder Picker --}}
                        <button wire:click="selectFolder" class="flex items-center gap-2 px-3 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg transition-colors text-sm">
                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            <span class="text-slate-300">{{ $projectPath ? basename($projectPath) : 'Select Folder' }}</span>
                        </button>

                        {{-- Exclusions --}}
                        <div class="flex-1 flex items-center gap-2 flex-wrap">
                            @foreach ($exclusions as $index => $exc)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-slate-800 border border-slate-700 rounded text-xs text-slate-400">
                                    {{ $exc }}
                                    <button wire:click="removeExclusion({{ $index }})" class="text-slate-500 hover:text-red-400">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                            <div class="flex items-center gap-1">
                                <input
                                    type="text"
                                    wire:model="newExclusion"
                                    wire:keydown.enter="addExclusion"
                                    placeholder="+ exclude"
                                    class="w-20 px-2 py-1 bg-transparent border border-slate-700 rounded text-xs text-slate-400 placeholder-slate-600 focus:outline-none focus:border-slate-500"
                                >
                            </div>
                        </div>

                        {{-- Scan Button --}}
                        <button
                            wire:click="startScan"
                            @disabled(!$projectPath || $status === 'running')
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:bg-slate-700 disabled:text-slate-500 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                        >
                            @if ($status === 'running')
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Scanning...
                            @else
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                                Scan
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="flex items-center border-b border-slate-800 bg-slate-900/20">
                    @foreach (['log' => 'Log', 'summary' => 'Summary', 'trivy' => 'Trivy', 'semgrep' => 'Semgrep', 'gitleaks' => 'Gitleaks', 'laravel_rules' => 'Laravel Rules', 'env_audit' => 'Env Audit'] as $tab => $label)
                        <button
                            wire:click="$set('activeTab', '{{ $tab }}')"
                            class="px-4 py-2.5 text-xs font-medium transition-colors border-b-2
                                {{ $activeTab === $tab
                                    ? 'text-emerald-400 border-emerald-400'
                                    : 'text-slate-500 border-transparent hover:text-slate-300' }}"
                        >
                            {{ $label }}
                            @if ($tab === 'trivy' && isset($results['summary']['trivy_total']) && $results['summary']['trivy_total'] > 0)
                                <span class="ml-1 px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded text-[10px]">{{ $results['summary']['trivy_total'] }}</span>
                            @elseif ($tab === 'semgrep' && isset($results['summary']['semgrep_total']) && $results['summary']['semgrep_total'] > 0)
                                <span class="ml-1 px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded text-[10px]">{{ $results['summary']['semgrep_total'] }}</span>
                            @elseif ($tab === 'gitleaks' && isset($results['summary']['gitleaks_total']) && $results['summary']['gitleaks_total'] > 0)
                                <span class="ml-1 px-1.5 py-0.5 bg-red-500/20 text-red-400 rounded text-[10px]">{{ $results['summary']['gitleaks_total'] }}</span>
                            @elseif ($tab === 'laravel_rules' && isset($results['summary']['laravel_rules_total']) && $results['summary']['laravel_rules_total'] > 0)
                                <span class="ml-1 px-1.5 py-0.5 bg-rose-500/20 text-rose-400 rounded text-[10px]">{{ $results['summary']['laravel_rules_total'] }}</span>
                            @elseif ($tab === 'env_audit' && isset($results['summary']['env_audit_total']) && $results['summary']['env_audit_total'] > 0)
                                <span class="ml-1 px-1.5 py-0.5 bg-cyan-500/20 text-cyan-400 rounded text-[10px]">{{ $results['summary']['env_audit_total'] }}</span>
                            @endif
                        </button>
                    @endforeach

                    @if ($results)
                        <div class="flex-1"></div>
                        <button wire:click="exportJson" class="px-3 py-1.5 mr-3 text-xs text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded transition-colors">
                            Export JSON
                        </button>
                    @endif
                </div>

                {{-- Tab Content --}}
                <div class="flex-1 overflow-y-auto">

                    {{-- Log Tab --}}
                    @if ($activeTab === 'log')
                        <div class="p-4 font-mono text-xs space-y-0.5" id="log-container">
                            @forelse ($logs as $log)
                                <div class="flex items-start gap-3 py-0.5">
                                    <span class="text-slate-600 shrink-0">{{ $log['time'] }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium shrink-0
                                        {{ match($log['scanner']) {
                                            'trivy' => 'bg-blue-500/10 text-blue-400',
                                            'semgrep' => 'bg-purple-500/10 text-purple-400',
                                            'gitleaks' => 'bg-amber-500/10 text-amber-400',
                                            'laravel_rules' => 'bg-rose-500/10 text-rose-400',
                                            default => 'bg-slate-500/10 text-slate-500'
                                        } }}">{{ $log['scanner'] }}</span>
                                    <span class="{{ $log['level'] === 'error' ? 'text-red-400' : 'text-slate-300' }} break-all">{{ $log['line'] }}</span>
                                </div>
                            @empty
                                <div class="flex items-center justify-center h-64 text-slate-600">
                                    <p>Select a folder and click Scan to begin.</p>
                                </div>
                            @endforelse
                        </div>

                    {{-- Summary Tab --}}
                    @elseif ($activeTab === 'summary')
                        @if ($results && isset($results['summary']))
                            <div class="p-6 space-y-6">

                                {{-- Security Grade + Scan Comparison Row --}}
                                @php $grade = $this->getSecurityGrade(); @endphp
                                <div class="flex items-start gap-6">
                                    {{-- Left: Scan Comparison --}}
                                    <div class="flex-1 min-w-0">
                                        {{-- Scan Comparison --}}
                                <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-sm font-semibold text-white">Compare with Previous Scan</h3>
                                        @if ($scanDiff)
                                            <button wire:click="clearComparison" class="text-xs text-slate-500 hover:text-slate-300">Clear</button>
                                        @endif
                                    </div>
                                    @if ($this->comparableScans->isNotEmpty())
                                        @if (!$scanDiff)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($this->comparableScans as $oldScan)
                                                    <button
                                                        wire:click="compareScan({{ $oldScan->id }})"
                                                        class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-xs text-slate-400 hover:text-white transition-colors"
                                                    >
                                                        {{ $oldScan->created_at->format('M j, H:i') }} ({{ $oldScan->findings_count }} findings)
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="space-y-3">
                                                <p class="text-xs text-slate-500">{{ $scanDiff['old_date'] }} &rarr; {{ $scanDiff['new_date'] }}</p>
                                                <div class="grid grid-cols-6 gap-3">
                                                    @foreach ([
                                                        'Total' => $scanDiff['total'],
                                                        'Trivy' => $scanDiff['trivy'],
                                                        'Semgrep' => $scanDiff['semgrep'],
                                                        'Gitleaks' => $scanDiff['gitleaks'],
                                                        'Laravel' => $scanDiff['laravel_rules'],
                                                        'Env' => $scanDiff['env_audit'],
                                                    ] as $label => $diff)
                                                        <div class="text-center">
                                                            <p class="text-[10px] text-slate-500 uppercase">{{ $label }}</p>
                                                            <p class="text-lg font-bold mt-0.5
                                                                {{ $diff < 0 ? 'text-emerald-400' : ($diff > 0 ? 'text-red-400' : 'text-slate-500') }}">
                                                                {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                                            </p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <p class="text-xs text-slate-600">No previous scans of this project to compare with.</p>
                                    @endif
                                </div>
                                    </div>

                                    {{-- Right: Security Grade Circle --}}
                                    <div class="shrink-0 flex flex-col items-center gap-2">
                                        <div class="w-24 h-24 rounded-full {{ $grade['bg'] }} border-2 flex items-center justify-center">
                                            <span class="text-4xl font-black {{ $grade['color'] }}">{{ $grade['grade'] }}</span>
                                        </div>
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Security Grade</p>
                                        <p class="text-[10px] text-slate-500 text-center max-w-[100px]">
                                            @if ($grade['grade'] === 'A')
                                                Excellent
                                            @elseif ($grade['grade'] === 'B')
                                                Good
                                            @elseif ($grade['grade'] === 'C')
                                                Fair
                                            @elseif ($grade['grade'] === 'D')
                                                Poor
                                            @elseif ($grade['grade'] === 'F')
                                                Critical
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                {{-- Grand Total --}}
                                <div class="flex flex-wrap gap-3">
                                    @php
                                        $grandTotal = ($results['summary']['trivy_total'] ?? 0) + ($results['summary']['semgrep_total'] ?? 0) + ($results['summary']['gitleaks_total'] ?? 0) + ($results['summary']['laravel_rules_total'] ?? 0) + ($results['summary']['env_audit_total'] ?? 0);
                                        $ignoredStats = $this->ignoredStats;
                                    @endphp
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex-1 min-w-0">
                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Total Findings</p>
                                        <p class="text-2xl font-bold mt-1 {{ $grandTotal > 0 ? 'text-amber-400' : 'text-emerald-400' }}">{{ $grandTotal }}</p>
                                    </div>
                                    @if ($ignoredStats['total'] > 0)
                                        <div class="bg-slate-900 border border-amber-800/30 rounded-xl p-3 flex-1 min-w-0">
                                            <p class="text-xs text-slate-500 uppercase tracking-wider">Ignored</p>
                                            <p class="text-2xl font-bold mt-1 text-amber-500/70">{{ $ignoredStats['total'] }}</p>
                                        </div>
                                    @endif
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex-1 min-w-0">
                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Dependencies (Trivy)</p>
                                        <p class="text-2xl font-bold mt-1 text-blue-400">{{ $results['summary']['trivy_total'] ?? 0 }}</p>
                                    </div>
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex-1 min-w-0">
                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Code (Semgrep)</p>
                                        <p class="text-2xl font-bold mt-1 text-purple-400">{{ $results['summary']['semgrep_total'] ?? 0 }}</p>
                                    </div>
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex-1 min-w-0">
                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Secrets (Gitleaks)</p>
                                        <p class="text-2xl font-bold mt-1 text-red-400">{{ $results['summary']['gitleaks_total'] ?? 0 }}</p>
                                    </div>
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex-1 min-w-0">
                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Laravel Rules</p>
                                        <p class="text-2xl font-bold mt-1 text-rose-400">{{ $results['summary']['laravel_rules_total'] ?? 0 }}</p>
                                    </div>
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex-1 min-w-0">
                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Env Audit</p>
                                        <p class="text-2xl font-bold mt-1 text-cyan-400">{{ $results['summary']['env_audit_total'] ?? 0 }}</p>
                                    </div>
                                </div>

                                {{-- Severity Breakdown --}}
                                @if (isset($results['summary']['trivy']))
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                        <h3 class="text-sm font-semibold text-white mb-3">Trivy Severity Breakdown</h3>
                                        <div class="grid grid-cols-4 gap-3">
                                            @foreach (['CRITICAL' => 'bg-red-500', 'HIGH' => 'bg-orange-500', 'MEDIUM' => 'bg-yellow-500', 'LOW' => 'bg-blue-500'] as $sev => $color)
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span>
                                                    <span class="text-xs text-slate-400">{{ $sev }}</span>
                                                    <span class="text-sm font-semibold text-white ml-auto">{{ $results['summary']['trivy'][$sev] ?? 0 }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (isset($results['summary']['semgrep']))
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                        <h3 class="text-sm font-semibold text-white mb-3">Semgrep Severity Breakdown</h3>
                                        <div class="grid grid-cols-3 gap-3">
                                            @foreach (['ERROR' => 'bg-red-500', 'WARNING' => 'bg-yellow-500', 'INFO' => 'bg-blue-500'] as $sev => $color)
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span>
                                                    <span class="text-xs text-slate-400">{{ $sev }}</span>
                                                    <span class="text-sm font-semibold text-white ml-auto">{{ $results['summary']['semgrep'][$sev] ?? 0 }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (isset($results['summary']['laravel_rules']))
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                        <h3 class="text-sm font-semibold text-white mb-3">Laravel Rules Severity Breakdown</h3>
                                        <div class="grid grid-cols-3 gap-3">
                                            @foreach (['CRITICAL' => 'bg-red-500', 'HIGH' => 'bg-orange-500', 'MEDIUM' => 'bg-yellow-500'] as $sev => $color)
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span>
                                                    <span class="text-xs text-slate-400">{{ $sev }}</span>
                                                    <span class="text-sm font-semibold text-white ml-auto">{{ $results['summary']['laravel_rules'][$sev] ?? 0 }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (isset($results['summary']['env_audit']))
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                        <h3 class="text-sm font-semibold text-white mb-3">Env Audit Severity Breakdown</h3>
                                        <div class="grid grid-cols-4 gap-3">
                                            @foreach (['CRITICAL' => 'bg-red-500', 'HIGH' => 'bg-orange-500', 'MEDIUM' => 'bg-yellow-500', 'INFO' => 'bg-blue-500'] as $sev => $color)
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span>
                                                    <span class="text-xs text-slate-400">{{ $sev }}</span>
                                                    <span class="text-sm font-semibold text-white ml-auto">{{ $results['summary']['env_audit'][$sev] ?? 0 }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Summary Text --}}
                                @if (isset($results['summary']['text']))
                                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                        <h3 class="text-sm font-semibold text-white mb-3">Raw Summary</h3>
                                        <pre class="text-xs text-slate-300 font-mono whitespace-pre-wrap">{{ $results['summary']['text'] }}</pre>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center justify-center h-64 text-slate-600">
                                <p>Run a scan to see the summary.</p>
                            </div>
                        @endif

                    {{-- Trivy Tab --}}
                    @elseif ($activeTab === 'trivy')
                        @if ($results && isset($results['trivy']))
                            <div class="p-4">
                                @foreach ($results['trivy']['Results'] ?? [] as $result)
                                    @if (!empty($result['Vulnerabilities']))
                                        <div class="mb-6">
                                            <div class="flex items-center gap-2 mb-3">
                                                <span class="text-sm font-semibold text-white">{{ $result['Target'] ?? 'Unknown' }}</span>
                                                <span class="text-xs text-slate-500">({{ $result['Type'] ?? '' }})</span>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="text-left text-slate-500 border-b border-slate-800">
                                                            <th class="pb-2 pr-4">Severity</th>
                                                            <th class="pb-2 pr-4">CVE</th>
                                                            <th class="pb-2 pr-4">Package</th>
                                                            <th class="pb-2 pr-4">Installed</th>
                                                            <th class="pb-2 pr-4">Fixed</th>
                                                            <th class="pb-2 pr-4">Title</th>
                                                            <th class="pb-2">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($result['Vulnerabilities'] as $vuln)
                                                            @php $trivyFp = ($vuln['VulnerabilityID'] ?? '') . ':' . ($vuln['PkgName'] ?? ''); @endphp
                                                            <tr class="border-b border-slate-800/50 hover:bg-slate-900/50 {{ $this->isIgnored('trivy', $trivyFp) ? 'opacity-40' : '' }}">
                                                                <td class="py-2 pr-4">
                                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold
                                                                        {{ match($vuln['Severity'] ?? '') {
                                                                            'CRITICAL' => 'bg-red-500/20 text-red-400',
                                                                            'HIGH' => 'bg-orange-500/20 text-orange-400',
                                                                            'MEDIUM' => 'bg-yellow-500/20 text-yellow-400',
                                                                            'LOW' => 'bg-blue-500/20 text-blue-400',
                                                                            default => 'bg-slate-500/20 text-slate-400'
                                                                        } }}">{{ $vuln['Severity'] ?? 'N/A' }}</span>
                                                                </td>
                                                                <td class="py-2 pr-4 font-mono text-slate-300">{{ $vuln['VulnerabilityID'] ?? '' }}</td>
                                                                <td class="py-2 pr-4 text-slate-300">{{ $vuln['PkgName'] ?? '' }}</td>
                                                                <td class="py-2 pr-4 font-mono text-slate-500">{{ $vuln['InstalledVersion'] ?? '' }}</td>
                                                                <td class="py-2 pr-4 font-mono text-emerald-400">{{ $vuln['FixedVersion'] ?? 'N/A' }}</td>
                                                                <td class="py-2 pr-4 text-slate-400 max-w-xs truncate">{{ $vuln['Title'] ?? $vuln['Description'] ?? '' }}</td>
                                                                <td class="py-2">
                                                                    @if ($this->isIgnored('trivy', $trivyFp))
                                                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-400 cursor-help" title="{{ $this->getIgnoreReason('trivy', $trivyFp) }}">IGNORED</span>
                                                                        <button wire:click="unignoreFinding('trivy', '{{ $trivyFp }}')" class="ml-1 text-[10px] text-slate-500 hover:text-red-400 transition-colors">&times;</button>
                                                                    @else
                                                                        <button wire:click="promptIgnore('trivy:{{ $trivyFp }}')" class="px-1.5 py-0.5 rounded text-[10px] text-slate-600 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">Ignore</button>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                                @if (empty($results['trivy']['Results'] ?? []) || collect($results['trivy']['Results'] ?? [])->every(fn ($r) => empty($r['Vulnerabilities'])))
                                    <div class="flex items-center justify-center h-64 text-emerald-400">
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p class="text-sm">No dependency vulnerabilities found.</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center justify-center h-64 text-slate-600">
                                <p>No Trivy results available.</p>
                            </div>
                        @endif

                    {{-- Semgrep Tab --}}
                    @elseif ($activeTab === 'semgrep')
                        @if ($results && isset($results['semgrep']))
                            <div class="p-4 space-y-2">
                                @forelse ($results['semgrep']['results'] ?? [] as $finding)
                                    @php $semgrepFp = ($finding['check_id'] ?? '') . ':' . ($finding['path'] ?? '') . ':' . ($finding['start']['line'] ?? ''); @endphp
                                    <div class="bg-slate-900 border border-slate-800 rounded-lg p-4 {{ $this->isIgnored('semgrep', $semgrepFp) ? 'opacity-40' : '' }}">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold
                                                    {{ match($finding['extra']['severity'] ?? '') {
                                                        'ERROR' => 'bg-red-500/20 text-red-400',
                                                        'WARNING' => 'bg-yellow-500/20 text-yellow-400',
                                                        default => 'bg-blue-500/20 text-blue-400'
                                                    } }}">{{ $finding['extra']['severity'] ?? 'INFO' }}</span>
                                                <span class="text-xs font-mono text-slate-400">{{ $finding['check_id'] ?? '' }}</span>
                                                @if ($this->isIgnored('semgrep', $semgrepFp))
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-400 cursor-help" title="{{ $this->getIgnoreReason('semgrep', $semgrepFp) }}">IGNORED</span>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($this->isIgnored('semgrep', $semgrepFp))
                                                    <button wire:click="unignoreFinding('semgrep', '{{ $semgrepFp }}')" class="px-2 py-1 rounded text-[10px] text-amber-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">Remove Ignore</button>
                                                @else
                                                    <button wire:click="promptIgnore('semgrep:{{ $semgrepFp }}')" class="px-2 py-1 rounded text-[10px] text-slate-600 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">Ignore</button>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-sm text-slate-300 mb-2">{{ $finding['extra']['message'] ?? '' }}</p>
                                        <div class="flex items-center gap-3 text-xs text-slate-500">
                                            <span class="font-mono">{{ $finding['path'] ?? '' }}:{{ $finding['start']['line'] ?? '' }}</span>
                                        </div>
                                        @if (isset($finding['extra']['lines']))
                                            <pre class="mt-2 p-2 bg-slate-950 rounded text-xs font-mono text-slate-400 overflow-x-auto">{{ $finding['extra']['lines'] }}</pre>
                                        @endif
                                    </div>
                                @empty
                                    <div class="flex items-center justify-center h-64 text-emerald-400">
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p class="text-sm">No static analysis findings.</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="flex items-center justify-center h-64 text-slate-600">
                                <p>No Semgrep results available.</p>
                            </div>
                        @endif

                    {{-- Gitleaks Tab --}}
                    @elseif ($activeTab === 'gitleaks')
                        @if ($results && isset($results['gitleaks']))
                            <div class="p-4 space-y-2">
                                @forelse ($results['gitleaks'] ?? [] as $secret)
                                    @php $gitleaksFp = ($secret['RuleID'] ?? '') . ':' . ($secret['File'] ?? '') . ':' . ($secret['StartLine'] ?? ''); @endphp
                                    <div class="bg-slate-900 border border-red-900/30 rounded-lg p-4 {{ $this->isIgnored('gitleaks', $gitleaksFp) ? 'opacity-40' : '' }}">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-500/20 text-red-400">SECRET</span>
                                                <span class="text-xs font-mono text-slate-400">{{ $secret['RuleID'] ?? '' }}</span>
                                                @if ($this->isIgnored('gitleaks', $gitleaksFp))
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-400 cursor-help" title="{{ $this->getIgnoreReason('gitleaks', $gitleaksFp) }}">IGNORED</span>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($this->isIgnored('gitleaks', $gitleaksFp))
                                                    <button wire:click="unignoreFinding('gitleaks', '{{ $gitleaksFp }}')" class="px-2 py-1 rounded text-[10px] text-amber-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">Remove Ignore</button>
                                                @else
                                                    <button wire:click="promptIgnore('gitleaks:{{ $gitleaksFp }}')" class="px-2 py-1 rounded text-[10px] text-slate-600 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">Ignore</button>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-sm text-slate-300 mb-2">{{ $secret['Description'] ?? '' }}</p>
                                        <div class="grid grid-cols-2 gap-2 text-xs text-slate-500 mt-3">
                                            <div>
                                                <span class="text-slate-600">File:</span>
                                                <span class="font-mono text-slate-400 ml-1">{{ $secret['File'] ?? '' }}:{{ $secret['StartLine'] ?? '' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-600">Author:</span>
                                                <span class="text-slate-400 ml-1">{{ $secret['Author'] ?? '' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-600">Commit:</span>
                                                <span class="font-mono text-slate-400 ml-1">{{ substr($secret['Commit'] ?? '', 0, 8) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-600">Date:</span>
                                                <span class="text-slate-400 ml-1">{{ $secret['Date'] ?? '' }}</span>
                                            </div>
                                        </div>
                                        <div class="mt-2 p-2 bg-red-950/30 border border-red-900/20 rounded text-xs font-mono text-red-300 overflow-x-auto">
                                            {{ str_repeat('*', min(strlen($secret['Secret'] ?? ''), 8)) }}{{ substr($secret['Secret'] ?? '', 8) }}
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex items-center justify-center h-64 text-emerald-400">
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p class="text-sm">No secrets detected.</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="flex items-center justify-center h-64 text-slate-600">
                                <p>No Gitleaks results available.</p>
                            </div>
                        @endif

                    {{-- Laravel Rules Tab --}}
                    @elseif ($activeTab === 'laravel_rules')
                        @if ($results && isset($results['laravel_rules']))
                            <div class="p-4 space-y-2">
                                @forelse ($results['laravel_rules']['findings'] ?? [] as $finding)
                                    @php $laravelFp = ($finding['rule_id'] ?? '') . ':' . ($finding['file_path'] ?? '') . ':' . ($finding['line_start'] ?? ''); @endphp
                                    <div class="bg-slate-900 border border-slate-800 rounded-lg p-4 {{ $this->isIgnored('laravel_rules', $laravelFp) ? 'opacity-40' : '' }}">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold
                                                    {{ match($finding['severity'] ?? 'MEDIUM') {
                                                        'CRITICAL' => 'bg-red-500/20 text-red-400',
                                                        'HIGH' => 'bg-orange-500/20 text-orange-400',
                                                        default => 'bg-yellow-500/20 text-yellow-400'
                                                    } }}">{{ $finding['severity'] ?? 'MEDIUM' }}</span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-500/10 text-rose-400">{{ $finding['category'] ?? '' }}</span>
                                                <span class="text-xs font-mono text-slate-500">{{ $finding['rule_id'] ?? '' }}</span>
                                                @if ($this->isIgnored('laravel_rules', $laravelFp))
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-400 cursor-help" title="{{ $this->getIgnoreReason('laravel_rules', $laravelFp) }}">IGNORED</span>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($this->isIgnored('laravel_rules', $laravelFp))
                                                    <button wire:click="unignoreFinding('laravel_rules', '{{ $laravelFp }}')" class="px-2 py-1 rounded text-[10px] text-amber-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">Remove Ignore</button>
                                                @else
                                                    <button wire:click="promptIgnore('laravel_rules:{{ $laravelFp }}')" class="px-2 py-1 rounded text-[10px] text-slate-600 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">Ignore</button>
                                                @endif
                                            </div>
                                        </div>
                                        <h4 class="text-sm font-semibold text-white mb-1">{{ $finding['title'] ?? '' }}</h4>
                                        <p class="text-xs text-slate-400 mb-3">{{ $finding['description'] ?? '' }}</p>
                                        <div class="grid grid-cols-1 gap-2 text-xs">
                                            <div>
                                                <span class="text-slate-600">File:</span>
                                                <span class="font-mono text-slate-400 ml-1">{{ $finding['file_path'] ?? '' }}:{{ $finding['line_start'] ?? '' }}</span>
                                            </div>
                                        </div>
                                        @if (!empty($finding['code_snippet']))
                                            <div class="mt-2 p-2 bg-slate-950 border border-slate-800 rounded text-xs font-mono text-slate-300 overflow-x-auto">
                                                <pre class="whitespace-pre-wrap">{{ $finding['code_snippet'] }}</pre>
                                            </div>
                                        @endif
                                        @if (!empty($finding['fix_guidance']))
                                            <div class="mt-2 p-2 bg-emerald-950/30 border border-emerald-900/20 rounded text-xs text-emerald-300">
                                                <span class="font-semibold">Fix:</span> {{ $finding['fix_guidance'] }}
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="flex items-center justify-center h-64 text-emerald-400">
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p class="text-sm">No Laravel-specific issues found.</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="flex items-center justify-center h-64 text-slate-600">
                                <p>No Laravel Rules results available.</p>
                            </div>
                        @endif

                    {{-- Env Audit Tab --}}
                    @elseif ($activeTab === 'env_audit')
                        @if ($results && isset($results['env_audit']))
                            <div class="p-4 space-y-2">
                                @forelse ($results['env_audit']['findings'] ?? [] as $finding)
                                    @php $envFp = ($finding['key'] ?? '') . ':' . ($finding['line'] ?? ''); @endphp
                                    <div class="bg-slate-900 border border-slate-800 rounded-lg p-4 {{ $this->isIgnored('env_audit', $envFp) ? 'opacity-40' : '' }}">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold
                                                    {{ match($finding['severity'] ?? 'INFO') {
                                                        'CRITICAL' => 'bg-red-500/20 text-red-400',
                                                        'HIGH' => 'bg-orange-500/20 text-orange-400',
                                                        'MEDIUM' => 'bg-yellow-500/20 text-yellow-400',
                                                        default => 'bg-blue-500/20 text-blue-400'
                                                    } }}">{{ $finding['severity'] }}</span>
                                                <span class="text-xs font-mono text-cyan-400">.env:{{ $finding['line'] }}</span>
                                                @if ($this->isIgnored('env_audit', $envFp))
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-400 cursor-help" title="{{ $this->getIgnoreReason('env_audit', $envFp) }}">IGNORED</span>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($this->isIgnored('env_audit', $envFp))
                                                    <button wire:click="unignoreFinding('env_audit', '{{ $envFp }}')" class="px-2 py-1 rounded text-[10px] text-amber-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">Remove Ignore</button>
                                                @else
                                                    <button wire:click="promptIgnore('env_audit:{{ $envFp }}')" class="px-2 py-1 rounded text-[10px] text-slate-600 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">Ignore</button>
                                                @endif
                                            </div>
                                        </div>
                                        <h4 class="text-sm font-semibold text-white mb-1">{{ $finding['key'] }}: {{ $finding['title'] }}</h4>
                                        <p class="text-xs text-slate-400 mb-3">{{ $finding['description'] }}</p>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div class="p-2 bg-red-950/20 border border-red-900/20 rounded">
                                                <span class="text-slate-500">Current:</span>
                                                <span class="font-mono text-red-300 ml-1">{{ $finding['current'] }}</span>
                                            </div>
                                            <div class="p-2 bg-emerald-950/20 border border-emerald-900/20 rounded">
                                                <span class="text-slate-500">Recommended:</span>
                                                <span class="font-mono text-emerald-300 ml-1">{{ $finding['recommended'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex items-center justify-center h-64 text-emerald-400">
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p class="text-sm">Environment configuration looks good!</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="flex items-center justify-center h-64 text-slate-600">
                                <p>No .env audit results available.</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </main>

    {{-- Ignore Reason Modal --}}
    @if ($pendingIgnoreKey)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-700 rounded-xl p-6 w-96 shadow-2xl">
                <h3 class="text-sm font-semibold text-white mb-3">Ignore this finding?</h3>
                <p class="text-xs text-slate-400 mb-4">This finding will still appear in future scans but will be labeled as ignored.</p>
                <div class="mb-4">
                    <label class="text-xs text-slate-500 mb-1 block">Reason (optional)</label>
                    <input
                        type="text"
                        wire:model="ignoreReason"
                        wire:keydown.enter="confirmIgnore"
                        placeholder="e.g. Test fixture, false positive, accepted risk..."
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:border-amber-500/50"
                        autofocus
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelIgnore" class="px-4 py-2 text-xs text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors">Cancel</button>
                    <button wire:click="confirmIgnore" class="px-4 py-2 text-xs text-white bg-amber-600 hover:bg-amber-500 rounded-lg transition-colors">Ignore Finding</button>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('scroll-log', () => {
        const container = document.getElementById('log-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });
</script>
@endscript
