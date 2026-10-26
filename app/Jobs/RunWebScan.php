<?php

namespace App\Jobs;

use App\Scanning\ScanEngine;
use App\Scanning\Store\EphemeralScanStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Scans an uploaded project and deletes the uploaded code as soon as the scan
 * finishes, whatever the outcome. Only the findings stay, in the cache, until
 * the TTL expires.
 */
class RunWebScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public string $scanId,
        public string $workspace,
        public array $exclusions,
    ) {}

    public function handle(ScanEngine $engine, Filesystem $files): void
    {
        $id = $this->scanId;

        EphemeralScanStore::update($id, ['status' => 'running', 'started_at' => now()->toIso8601String()]);
        EphemeralScanStore::appendLog($id, 'Upload received. Starting security scan...');

        try {
            $results = $engine->run(
                projectPath: $this->workspace.'/src',
                outputDir: $this->workspace.'/out',
                exclusions: $this->exclusions,
                log: fn (string $line, string $scanner, string $level) => EphemeralScanStore::appendLog($id, $line, $scanner, $level),
                options: ['git_history' => false, 'redact_secrets' => true, 'respect_gitignore' => false],
            );

            EphemeralScanStore::update($id, [
                'status' => 'completed',
                'results' => $results,
                'findings_count' => $results['summary']['findings_total'] ?? 0,
                'completed_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            EphemeralScanStore::appendLog($id, 'Scan failed: '.$e->getMessage(), 'system', 'error');
            EphemeralScanStore::update($id, ['status' => 'failed', 'completed_at' => now()->toIso8601String()]);
        } finally {
            $files->deleteDirectory($this->workspace);
            EphemeralScanStore::appendLog($id, 'Uploaded code deleted from the server.');
        }
    }

    public function failed(?\Throwable $exception): void
    {
        (new Filesystem)->deleteDirectory($this->workspace);
        EphemeralScanStore::update($this->scanId, ['status' => 'failed', 'completed_at' => now()->toIso8601String()]);
    }
}
