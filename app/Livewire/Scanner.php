<?php

namespace App\Livewire;

use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\ScannerService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Native\Desktop\Dialog;

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
