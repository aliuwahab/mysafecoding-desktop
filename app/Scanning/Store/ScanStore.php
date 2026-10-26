<?php

namespace App\Scanning\Store;

use Illuminate\Support\Collection;

interface ScanStore
{
    /** @return Collection<int, ScanRecord> */
    public function history(): Collection;

    public function find(string $id): ?ScanRecord;

    /** @return Collection<int, ScanRecord> Completed scans of the same project, newest first. */
    public function comparable(ScanRecord $scan, int $limit = 10): Collection;

    /** @return list<array{id:int, line:string, scanner:string, level:string, time:string}> */
    public function logsSince(string $id, int $afterLogId = 0): array;

    public function delete(string $id): void;

    /** @return array<string, string> "scanner:fingerprint" => reason */
    public function ignoredMap(string $projectPath): array;

    public function ignore(string $projectPath, string $scanner, string $fingerprint, ?string $reason): void;

    public function unignore(string $projectPath, string $scanner, string $fingerprint): void;
}
