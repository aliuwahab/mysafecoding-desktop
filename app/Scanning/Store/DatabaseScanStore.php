<?php

namespace App\Scanning\Store;

use App\Models\IgnoredFinding;
use App\Models\Scan;
use App\Models\ScanLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Desktop mode: scan history, logs and ignored findings persist in SQLite.
 */
class DatabaseScanStore implements ScanStore
{
    public function history(): Collection
    {
        if (! Schema::hasTable('scans')) {
            return collect();
        }

        return Scan::latestFirst()->take(20)->get()->map($this->toRecord(...));
    }

    public function find(string $id): ?ScanRecord
    {
        if (! Schema::hasTable('scans') || ! ctype_digit($id)) {
            return null;
        }

        $scan = Scan::find((int) $id);

        return $scan ? $this->toRecord($scan) : null;
    }

    public function comparable(ScanRecord $scan, int $limit = 10): Collection
    {
        return Scan::where('project_path', $scan->projectPath)
            ->where('status', 'completed')
            ->where('id', '!=', (int) $scan->id)
            ->latestFirst()
            ->take($limit)
            ->get()
            ->map($this->toRecord(...));
    }

    public function logsSince(string $id, int $afterLogId = 0): array
    {
        if (! Schema::hasTable('scan_logs')) {
            return [];
        }

        return ScanLog::where('scan_id', (int) $id)
            ->when($afterLogId > 0, fn ($query) => $query->where('id', '>', $afterLogId))
            ->orderBy('id')
            ->get()
            ->map(fn (ScanLog $log): array => [
                'id' => $log->id,
                'line' => $log->line,
                'scanner' => $log->scanner,
                'level' => $log->level,
                'time' => $log->created_at->format('H:i:s'),
            ])
            ->all();
    }

    public function delete(string $id): void
    {
        ScanLog::where('scan_id', (int) $id)->delete();
        Scan::whereKey((int) $id)->delete();
    }

    public function ignoredMap(string $projectPath): array
    {
        return Schema::hasTable('ignored_findings') ? IgnoredFinding::getIgnoredMap($projectPath) : [];
    }

    public function ignore(string $projectPath, string $scanner, string $fingerprint, ?string $reason): void
    {
        IgnoredFinding::updateOrCreate(
            ['project_path' => $projectPath, 'scanner' => $scanner, 'fingerprint' => $fingerprint],
            ['reason' => $reason],
        );
    }

    public function unignore(string $projectPath, string $scanner, string $fingerprint): void
    {
        IgnoredFinding::where('project_path', $projectPath)
            ->where('scanner', $scanner)
            ->where('fingerprint', $fingerprint)
            ->delete();
    }

    protected function toRecord(Scan $scan): ScanRecord
    {
        return new ScanRecord(
            id: (string) $scan->id,
            projectPath: $scan->project_path,
            projectName: basename($scan->project_path),
            status: $scan->status,
            results: $scan->results,
            findingsCount: (int) $scan->findings_count,
            exclusions: $scan->exclusions ?? [],
            createdAt: $scan->created_at,
            startedAt: $scan->started_at,
            completedAt: $scan->completed_at,
        );
    }
}
