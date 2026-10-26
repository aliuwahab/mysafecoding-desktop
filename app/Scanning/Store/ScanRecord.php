<?php

namespace App\Scanning\Store;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A scan as the UI sees it, whether it lives in SQLite (desktop) or in the
 * short-lived cache (web).
 */
final class ScanRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectPath,
        public readonly string $projectName,
        public readonly string $status,
        public readonly ?array $results,
        public readonly int $findingsCount,
        public readonly array $exclusions,
        public readonly CarbonInterface $createdAt,
        public readonly ?CarbonInterface $startedAt = null,
        public readonly ?CarbonInterface $completedAt = null,
        public readonly ?CarbonInterface $codeDeletedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $date = static fn (?string $value): ?CarbonInterface => $value ? Carbon::parse($value) : null;

        return new self(
            id: (string) $data['id'],
            projectPath: (string) ($data['project_path'] ?? $data['project_name']),
            projectName: (string) $data['project_name'],
            status: (string) $data['status'],
            results: $data['results'] ?? null,
            findingsCount: (int) ($data['findings_count'] ?? 0),
            exclusions: $data['exclusions'] ?? [],
            createdAt: $date($data['created_at']) ?? Carbon::now(),
            startedAt: $date($data['started_at'] ?? null),
            completedAt: $date($data['completed_at'] ?? null),
            codeDeletedAt: $date($data['code_deleted_at'] ?? null),
        );
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
