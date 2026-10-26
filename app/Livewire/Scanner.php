<?php

namespace App\Livewire;

use App\Models\Scan;
use App\Providers\AppServiceProvider;
use App\Scanning\ScanCounter;
use App\Scanning\ScanEngine;
use App\Scanning\ScanGrade;
use App\Scanning\Store\ScanRecord;
use App\Scanning\Store\ScanStore;
use App\Services\GitHubActionsWorkflowGenerator;
use App\Services\SarifExporter;
use App\Services\ScannerService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Native\Desktop\Dialog;
use Native\Desktop\Facades\Shell;

class Scanner extends Component
{
    public bool $webMode = false;

    public ?string $projectPath = null;

    public string $newExclusion = '';

    public array $exclusions = [];

    public ?string $activeScanId = null;

    public string $activeTab = 'log';

    public array $logs = [];

    public ?array $results = null;

    public ?string $selectedHistoryId = null;

    public string $status = 'idle'; // idle, running, completed, failed

    public int $lastLogId = 0;

    public array $missingTools = [];

    public bool $showSetup = false;

    public ?string $compareWithScanId = null;

    public ?array $scanDiff = null;

    public array $ignoredFindings = [];

    public string $ignoreReason = '';

    public ?string $pendingIgnoreKey = null;

    public function mount(): void
    {
        $this->webMode = AppServiceProvider::isWebMode();
        $this->exclusions = config('checkmycode.default_exclusions');
        $this->missingTools = ScanEngine::missingTools();
        $this->showSetup = ! empty($this->missingTools);

        $lastScan = $this->store()->history()->first();
        if ($lastScan) {
            $this->loadScan($lastScan->id);
        }
    }

    protected function store(): ScanStore
    {
        return app(ScanStore::class);
    }

    public function loadIgnoredFindings(): void
    {
        $this->ignoredFindings = $this->projectPath ? $this->store()->ignoredMap($this->projectPath) : [];
    }

    public function isIgnored(string $scanner, string $fingerprint): bool
    {
        return isset($this->ignoredFindings["{$scanner}:{$fingerprint}"]);
    }

    public function getIgnoreReason(string $scanner, string $fingerprint): string
    {
        return $this->ignoredFindings["{$scanner}:{$fingerprint}"] ?? '';
    }

    public function promptIgnore(string $key): void
    {
        $this->pendingIgnoreKey = $key;
        $this->ignoreReason = '';
    }

    public function cancelIgnore(): void
    {
        $this->pendingIgnoreKey = null;
        $this->ignoreReason = '';
    }

    public function confirmIgnore(): void
    {
        if (! $this->pendingIgnoreKey || ! $this->projectPath) {
            return;
        }

        [$scanner, $fingerprint] = explode(':', $this->pendingIgnoreKey, 2);

        $this->store()->ignore($this->projectPath, $scanner, $fingerprint, $this->ignoreReason ?: null);

        $this->loadIgnoredFindings();
        $this->pendingIgnoreKey = null;
        $this->ignoreReason = '';
    }

    public function unignoreFinding(string $scanner, string $fingerprint): void
    {
        if (! $this->projectPath) {
            return;
        }

        $this->store()->unignore($this->projectPath, $scanner, $fingerprint);

        $this->loadIgnoredFindings();
    }

    #[Computed]
    public function ignoredStats(): array
    {
        $stats = ['total' => 0];
        foreach (array_keys($this->ignoredFindings) as $key) {
            $scanner = explode(':', $key, 2)[0];
            $stats[$scanner] = ($stats[$scanner] ?? 0) + 1;
            $stats['total']++;
        }

        return $stats;
    }

    public function newScan(): void
    {
        $this->resetScanState();

        if ($this->webMode) {
            $this->projectPath = null;
            $this->dispatch('web-selection-cleared');
        }
    }

    protected function resetScanState(): void
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
        if ($this->webMode) {
            return; // The browser picker is handled client-side.
        }

        $path = Dialog::new()
            ->title('Select Project Folder')
            ->folders()
            ->open();

        if ($path) {
            $this->projectPath = is_array($path) ? $path[0] : $path;
        }
    }

    public function selectFile(): void
    {
        if ($this->webMode) {
            return;
        }

        $path = Dialog::new()
            ->title('Select File to Scan')
            ->files()
            ->open();

        if ($path) {
            $this->projectPath = is_array($path) ? $path[0] : $path;
        }
    }

    /**
     * Web mode: the browser picked a folder or files and keeps them in memory
     * until Scan is pressed.
     */
    public function webSelectionChanged(?string $name): void
    {
        if (! $this->webMode) {
            return;
        }

        $this->resetScanState();
        $this->projectPath = $name ? mb_substr($name, 0, 120) : null;
    }

    public function addExclusion(): void
    {
        $exclusion = trim($this->newExclusion);
        if ($exclusion && ! in_array($exclusion, $this->exclusions)) {
            $this->exclusions[] = $exclusion;
        }
        $this->newExclusion = '';
        $this->dispatch('exclusions-changed', exclusions: $this->exclusions);
    }

    public function removeExclusion(int $index): void
    {
        unset($this->exclusions[$index]);
        $this->exclusions = array_values($this->exclusions);
        $this->dispatch('exclusions-changed', exclusions: $this->exclusions);
    }

    public function startScan(): void
    {
        if ($this->webMode) {
            // Uploading happens in the browser, which then calls webScanStarted().
            $this->dispatch('web-scan-requested');

            return;
        }

        if (! $this->projectPath || $this->status === 'running') {
            return;
        }

        $scan = Scan::create([
            'project_path' => $this->projectPath,
            'status' => 'pending',
            'exclusions' => $this->exclusions,
        ]);

        $this->beginTracking((string) $scan->id);

        ScannerService::startScan($scan);
    }

    public function webScanStarted(string $scanId): void
    {
        if (! $this->webMode || ! $this->store()->find($scanId)) {
            return;
        }

        $this->beginTracking($scanId);
    }

    protected function beginTracking(string $scanId): void
    {
        $this->activeScanId = $scanId;
        $this->selectedHistoryId = $scanId;
        $this->status = 'running';
        $this->activeTab = 'log';
        $this->logs = [];
        $this->results = null;
        $this->lastLogId = 0;
        $this->compareWithScanId = null;
        $this->scanDiff = null;
    }

    public function stopScan(): void
    {
        if (! $this->activeScanId) {
            return;
        }

        if (! $this->webMode && ($scan = Scan::find((int) $this->activeScanId))) {
            ScannerService::stopScan($scan);
        }

        $this->pollLogs();
    }

    public function pollLogs(): void
    {
        if (! $this->activeScanId) {
            return;
        }

        $scan = $this->store()->find($this->activeScanId);
        if (! $scan) {
            return;
        }

        $newLogs = $this->store()->logsSince($scan->id, $this->lastLogId);

        if ($newLogs !== []) {
            $this->logs = array_merge($this->logs, $newLogs);
            $this->lastLogId = (int) end($newLogs)['id'];
            $this->dispatch('scroll-log');
        }

        if ($scan->isFinished()) {
            $this->status = $scan->status;
            $this->results = $scan->results;
            $this->activeScanId = null;

            if ($scan->status === 'completed') {
                $this->activeTab = 'summary';
                $this->loadIgnoredFindings();
            }
        }
    }

    public function loadScan(string $scanId): void
    {
        $scan = $this->store()->find($scanId);
        if (! $scan) {
            return;
        }

        $this->selectedHistoryId = $scan->id;
        $this->projectPath = $scan->projectPath;
        $this->exclusions = $scan->exclusions ?: config('checkmycode.default_exclusions');
        $this->status = $scan->status === 'pending' ? 'running' : $scan->status;
        $this->results = $scan->results;
        $this->compareWithScanId = null;
        $this->scanDiff = null;
        $this->activeScanId = $scan->isFinished() ? null : $scan->id;

        $this->logs = $this->store()->logsSince($scan->id);
        $this->lastLogId = $this->logs === [] ? 0 : (int) end($this->logs)['id'];
        $this->activeTab = $scan->status === 'completed' ? 'summary' : 'log';
        $this->loadIgnoredFindings();
    }

    public function exportJson()
    {
        if (! $this->results) {
            return null;
        }

        return $this->saveFile(
            'checkmycode-report.json',
            json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'Save Scan Report',
        );
    }

    public function exportSarif()
    {
        if (! $this->results) {
            return null;
        }

        return $this->saveFile(
            'checkmycode-report.sarif',
            json_encode(SarifExporter::fromResults($this->results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'Save SARIF Report',
        );
    }

    public function exportGithubActionsWorkflow()
    {
        if (! config('checkmycode.ci_workflow')) {
            return null;
        }

        return $this->saveFile(
            'checkmycode.yml',
            GitHubActionsWorkflowGenerator::generate($this->exclusions, config('checkmycode.docker_image')),
            'Save GitHub Actions Workflow',
        );
    }

    /**
     * Desktop: native save dialog. Web: browser download.
     */
    protected function saveFile(string $filename, string $contents, string $title)
    {
        if ($this->webMode) {
            return response()->streamDownload(fn () => print ($contents), $filename);
        }

        $path = Dialog::new()
            ->title($title)
            ->defaultPath($filename)
            ->save();

        if ($path) {
            file_put_contents($path, $contents);
        }

        return null;
    }

    public function openLink(string $which): void
    {
        $url = match ($which) {
            'github' => config('checkmycode.github_url'),
            'sponsor' => config('checkmycode.sponsor_url'),
            default => null,
        };

        if ($url) {
            Shell::openExternal($url);
        }
    }

    public function getSecurityGrade(): array
    {
        if (! $this->results || ! isset($this->results['summary'])) {
            return ['grade' => '-'];
        }

        return ['grade' => $this->results['summary']['grade'] ?? ScanGrade::fromSummary($this->results['summary'])];
    }

    public function compareScan(string $scanId): void
    {
        if (! $this->results || ! $this->selectedHistoryId) {
            return;
        }

        $oldScan = $this->store()->find($scanId);
        if (! $oldScan || ! $oldScan->results) {
            return;
        }

        $oldSummary = $oldScan->results['summary'] ?? [];
        $newSummary = $this->results['summary'] ?? [];
        $delta = fn (string $key): int => ($newSummary[$key] ?? 0) - ($oldSummary[$key] ?? 0);

        $this->compareWithScanId = $scanId;
        $this->scanDiff = [
            'old_date' => $oldScan->createdAt->format('M j, H:i'),
            'new_date' => $this->store()->find($this->selectedHistoryId)?->createdAt->format('M j, H:i') ?? 'Current',
            'trivy' => $delta('trivy_total'),
            'semgrep' => $delta('semgrep_total'),
            'gitleaks' => $delta('gitleaks_total'),
            'laravel_rules' => $delta('laravel_rules_total'),
            'custom_rules' => $delta('custom_rules_total'),
            'trivy_config' => $delta('trivy_config_total'),
            'endoflife' => $delta('endoflife_total'),
            'env_audit' => $delta('env_audit_total'),
            'code_quality' => $delta('code_quality_total'),
            'total' => ScanEngine::findingsCount($this->results) - ScanEngine::findingsCount($oldScan->results),
        ];
    }

    public function clearComparison(): void
    {
        $this->compareWithScanId = null;
        $this->scanDiff = null;
    }

    #[Computed]
    public function comparableScans(): Collection
    {
        $current = $this->selectedHistoryId ? $this->store()->find($this->selectedHistoryId) : null;

        return $current ? $this->store()->comparable($current) : collect();
    }

    #[Computed]
    public function selectedScan(): ?ScanRecord
    {
        return $this->selectedHistoryId ? $this->store()->find($this->selectedHistoryId) : null;
    }

    public function installTools(): void
    {
        if (! $this->webMode) {
            ScannerService::installDependencies();
        }
    }

    public function recheckTools(): void
    {
        $this->missingTools = ScanEngine::missingTools();
        $this->showSetup = ! empty($this->missingTools);
    }

    public function deleteScan(string $scanId): void
    {
        $this->store()->delete($scanId);

        if ($this->selectedHistoryId === $scanId) {
            $this->newScan();
        }
    }

    #[Computed]
    public function scanStats(): array
    {
        return ScanCounter::stats();
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->store()->history();
    }

    public function render()
    {
        return view('livewire.scanner', [
            'webLimits' => $this->webMode ? [
                'maxUploadMb' => (int) config('checkmycode.web.max_upload_mb'),
                'maxFiles' => (int) config('checkmycode.web.max_files'),
                'ttlMinutes' => (int) config('checkmycode.web.results_ttl_minutes'),
                'uploadUrl' => route('web-scans.store'),
            ] : null,
        ])->layout('layouts.app');
    }
}
