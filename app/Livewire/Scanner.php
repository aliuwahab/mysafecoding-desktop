<?php

namespace App\Livewire;

use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\ScannerService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Native\Desktop\Dialog;
use Native\Desktop\Facades\Shell;

class Scanner extends Component
{
    private const DEFAULT_EXCLUSIONS = ['vendor', 'node_modules', 'storage', 'bootstrap/cache', 'public/build'];

    public ?string $projectPath = null;

    public string $newExclusion = '';

    public array $exclusions = [];

    public ?int $activeScanId = null;

    public string $activeTab = 'log';

    public array $logs = [];

    public ?array $results = null;

    public ?int $selectedHistoryId = null;

    public string $status = 'idle'; // idle, running, completed, failed

    public int $lastLogId = 0;

    public array $missingTools = [];

    public bool $showSetup = false;

    public ?int $compareWithScanId = null;

    public ?array $scanDiff = null;

    public function mount(): void
    {
        $this->exclusions = self::DEFAULT_EXCLUSIONS;
        $this->missingTools = ScannerService::checkDependencies();
        $this->showSetup = ! empty($this->missingTools);

        if (! Schema::hasTable('scans')) {
            return;
        }

        $lastScan = Scan::latestFirst()->first();
        if ($lastScan) {
            $this->loadScan($lastScan->id);
        }
    }

    public function newScan(): void
    {
        $this->selectedHistoryId = null;
        $this->activeScanId = null;
        $this->activeTab = 'log';
        $this->logs = [];
        $this->results = null;
        $this->status = 'idle';
        $this->lastLogId = 0;
        $this->compareWithScanId = null;
        $this->scanDiff = null;
    }

    public function selectFolder(): void
    {
        $path = Dialog::new()
            ->title('Select Laravel Project')
            ->folders()
            ->open();

        if ($path) {
            $this->projectPath = is_array($path) ? $path[0] : $path;
        }
    }

    public function addExclusion(): void
    {
        $exclusion = trim($this->newExclusion);
        if ($exclusion && ! in_array($exclusion, $this->exclusions)) {
            $this->exclusions[] = $exclusion;
        }
        $this->newExclusion = '';
    }

    public function removeExclusion(int $index): void
    {
        unset($this->exclusions[$index]);
        $this->exclusions = array_values($this->exclusions);
    }

    public function startScan(): void
    {
        if (! $this->projectPath || $this->status === 'running') {
            return;
        }

        $scan = Scan::create([
            'project_path' => $this->projectPath,
            'status' => 'pending',
            'exclusions' => $this->exclusions,
        ]);

        $this->activeScanId = $scan->id;
        $this->selectedHistoryId = $scan->id;
        $this->status = 'running';
        $this->activeTab = 'log';
        $this->logs = [];
        $this->results = null;
        $this->lastLogId = 0;

        ScannerService::startScan($scan);
    }

    public function pollLogs(): void
    {
        if (! $this->activeScanId || ! Schema::hasTable('scans') || ! Schema::hasTable('scan_logs')) {
            return;
        }

        $scan = Scan::find($this->activeScanId);
        if (! $scan) {
            return;
        }

        $newLogs = ScanLog::where('scan_id', $this->activeScanId)
            ->when($this->lastLogId > 0, fn ($query) => $query->where('id', '>', $this->lastLogId))
            ->orderBy('id')
            ->get();

        if ($newLogs->isNotEmpty()) {
            $this->logs = array_merge($this->logs, $newLogs->map(fn ($log) => [
                'line' => $log->line,
                'scanner' => $log->scanner,
                'level' => $log->level,
                'time' => $log->created_at->format('H:i:s'),
            ])->toArray());
            $this->lastLogId = $newLogs->last()->id;
            $this->dispatch('scroll-log');
        }

        if (in_array($scan->status, ['completed', 'failed'])) {
            $this->status = $scan->status;
            $this->results = $scan->results;
            $this->activeScanId = null;

            if ($scan->status === 'completed') {
                $this->activeTab = 'summary';
            }
        }
    }

    public function loadScan(int $scanId): void
    {
        if (! Schema::hasTable('scans')) {
            return;
        }

        $scan = Scan::find($scanId);
        if (! $scan) {
            return;
        }

        $this->selectedHistoryId = $scanId;
        $this->projectPath = $scan->project_path;
        $this->exclusions = $scan->exclusions ?? [];
        $this->status = $scan->status;
        $this->results = $scan->results;
        $this->compareWithScanId = null;
        $this->scanDiff = null;

        if ($scan->status === 'running') {
            $this->activeScanId = $scanId;
        } else {
            $this->activeScanId = null;
        }

        $logs = ScanLog::where('scan_id', $scanId)
            ->orderBy('id')
            ->get();

        $this->logs = $logs->map(fn ($log) => [
            'line' => $log->line,
            'scanner' => $log->scanner,
            'level' => $log->level,
            'time' => $log->created_at->format('H:i:s'),
        ])
            ->toArray();

        $this->lastLogId = $logs->last()?->id ?? 0;
        $this->activeTab = $scan->status === 'completed' ? 'summary' : 'log';
    }

    public function exportJson(): void
    {
        if (! $this->results) {
            return;
        }

        $path = Dialog::new()
            ->title('Save Scan Report')
            ->defaultPath('laraguard-report.json')
            ->save();

        if ($path) {
            file_put_contents($path, json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function openComprehensiveScan(): void
    {
        Shell::openExternal('https://laravelguard.com/');
    }

    public function getSecurityGrade(): array
    {
        if (! $this->results || ! isset($this->results['summary'])) {
            return ['grade' => '-', 'color' => 'text-slate-500', 'bg' => 'bg-slate-500/10 border-slate-500/20'];
        }

        $summary = $this->results['summary'];
        $criticals = ($summary['trivy']['CRITICAL'] ?? 0) + ($summary['laravel_rules']['CRITICAL'] ?? 0) + ($summary['env_audit']['CRITICAL'] ?? 0);
        $highs = ($summary['trivy']['HIGH'] ?? 0) + ($summary['semgrep']['ERROR'] ?? 0) + ($summary['laravel_rules']['HIGH'] ?? 0) + ($summary['env_audit']['HIGH'] ?? 0);
        $mediums = ($summary['trivy']['MEDIUM'] ?? 0) + ($summary['semgrep']['WARNING'] ?? 0) + ($summary['laravel_rules']['MEDIUM'] ?? 0) + ($summary['env_audit']['MEDIUM'] ?? 0);
        $secrets = $summary['gitleaks_total'] ?? 0;

        if ($secrets > 0 || $criticals > 0) {
            return ['grade' => 'F', 'color' => 'text-red-400', 'bg' => 'bg-red-500/10 border-red-500/20'];
        }
        if ($highs > 3) {
            return ['grade' => 'D', 'color' => 'text-orange-400', 'bg' => 'bg-orange-500/10 border-orange-500/20'];
        }
        if ($highs > 0) {
            return ['grade' => 'C', 'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500/10 border-yellow-500/20'];
        }
        if ($mediums > 0) {
            return ['grade' => 'B', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10 border-blue-500/20'];
        }

        return ['grade' => 'A', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10 border-emerald-500/20'];
    }

    public function compareScan(int $scanId): void
    {
        if (! $this->results || ! $this->selectedHistoryId) {
            return;
        }

        $oldScan = Scan::find($scanId);
        if (! $oldScan || ! $oldScan->results) {
            return;
        }

        $oldSummary = $oldScan->results['summary'] ?? [];
        $newSummary = $this->results['summary'] ?? [];

        $this->compareWithScanId = $scanId;
        $this->scanDiff = [
            'old_date' => $oldScan->created_at->format('M j, H:i'),
            'new_date' => Scan::find($this->selectedHistoryId)?->created_at->format('M j, H:i') ?? 'Current',
            'trivy' => ($newSummary['trivy_total'] ?? 0) - ($oldSummary['trivy_total'] ?? 0),
            'semgrep' => ($newSummary['semgrep_total'] ?? 0) - ($oldSummary['semgrep_total'] ?? 0),
            'gitleaks' => ($newSummary['gitleaks_total'] ?? 0) - ($oldSummary['gitleaks_total'] ?? 0),
            'laravel_rules' => ($newSummary['laravel_rules_total'] ?? 0) - ($oldSummary['laravel_rules_total'] ?? 0),
            'env_audit' => ($newSummary['env_audit_total'] ?? 0) - ($oldSummary['env_audit_total'] ?? 0),
            'total' => (($newSummary['trivy_total'] ?? 0) + ($newSummary['semgrep_total'] ?? 0) + ($newSummary['gitleaks_total'] ?? 0) + ($newSummary['laravel_rules_total'] ?? 0) + ($newSummary['env_audit_total'] ?? 0))
                - (($oldSummary['trivy_total'] ?? 0) + ($oldSummary['semgrep_total'] ?? 0) + ($oldSummary['gitleaks_total'] ?? 0) + ($oldSummary['laravel_rules_total'] ?? 0) + ($oldSummary['env_audit_total'] ?? 0)),
        ];
    }

    public function clearComparison(): void
    {
        $this->compareWithScanId = null;
        $this->scanDiff = null;
    }

    public function getComparableScansProperty()
    {
        if (! $this->projectPath || ! $this->selectedHistoryId || ! Schema::hasTable('scans')) {
            return collect();
        }

        return Scan::where('project_path', $this->projectPath)
            ->where('status', 'completed')
            ->where('id', '!=', $this->selectedHistoryId)
            ->latestFirst()
            ->take(10)
            ->get();
    }

    public function installTools(): void
    {
        ScannerService::installDependencies();
    }

    public function recheckTools(): void
    {
        $this->missingTools = ScannerService::checkDependencies();
        $this->showSetup = ! empty($this->missingTools);
    }

    public function deleteScan(int $scanId): void
    {
        if (! Schema::hasTable('scans')) {
            return;
        }

        ScanLog::where('scan_id', $scanId)->delete();
        Scan::where('id', $scanId)->delete();

        if ($this->selectedHistoryId === $scanId) {
            $this->newScan();
        }
    }

    public function getHistoryProperty()
    {
        if (! Schema::hasTable('scans')) {
            return collect();
        }

        return Scan::latestFirst()->take(20)->get();
    }

    public function render()
    {
        return view('livewire.scanner')
            ->layout('layouts.app');
    }
}
