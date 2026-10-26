<?php

namespace App\Scanning\Store;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Web mode: no database and no accounts. A scan and its log live in the cache
 * for a short TTL; the visitor's session only holds the ids of their own scans,
 * which is also what authorizes access to them. Ignored findings stay in the
 * session.
 */
class EphemeralScanStore implements ScanStore
{
    protected const SESSION_SCANS = 'web_scans';

    protected const SESSION_IGNORED = 'web_ignored';

    // ── Writes used by the upload controller and the scan job ──────────────

    public static function create(string $projectName, array $exclusions): string
    {
        $id = Str::random(32);

        static::put($id, [
            'id' => $id,
            'project_name' => $projectName,
            'status' => 'pending',
            'exclusions' => $exclusions,
            'results' => null,
            'findings_count' => 0,
            'created_at' => now()->toIso8601String(),
            'started_at' => null,
            'completed_at' => null,
        ]);
        Cache::put(static::logsKey($id), [], static::ttl());

        return $id;
    }

    public static function update(string $id, array $changes): void
    {
        $scan = Cache::get(static::key($id));
        if (is_array($scan)) {
            static::put($id, array_merge($scan, $changes));
        }
    }

    public static function appendLog(string $id, string $line, string $scanner = 'system', string $level = 'info'): void
    {
        $logs = Cache::get(static::logsKey($id), []);
        $logs[] = [
            'id' => count($logs) + 1,
            'line' => $line,
            'scanner' => $scanner,
            'level' => $level,
            'time' => now()->format('H:i:s'),
        ];
        Cache::put(static::logsKey($id), $logs, static::ttl());
    }

    public static function raw(string $id): ?array
    {
        $scan = Cache::get(static::key($id));

        return is_array($scan) ? $scan : null;
    }

    // ── Session-scoped reads used by the UI ─────────────────────────────────

    public function remember(string $id): void
    {
        $ids = array_values(array_unique([$id, ...session(self::SESSION_SCANS, [])]));
        session([self::SESSION_SCANS => array_slice($ids, 0, (int) config('checkmycode.web.history_limit', 20))]);
    }

    public function owns(string $id): bool
    {
        return in_array($id, session(self::SESSION_SCANS, []), true);
    }

    public function history(): Collection
    {
        $records = collect(session(self::SESSION_SCANS, []))
            ->map(fn (string $id): ?ScanRecord => ($scan = static::raw($id)) ? ScanRecord::fromArray($scan) : null)
            ->filter()
            ->values();

        // Forget scans whose cache entry expired.
        session([self::SESSION_SCANS => $records->map(fn (ScanRecord $scan): string => $scan->id)->all()]);

        return $records;
    }

    public function find(string $id): ?ScanRecord
    {
        if (! $this->owns($id)) {
            return null;
        }

        $scan = static::raw($id);

        return $scan ? ScanRecord::fromArray($scan) : null;
    }

    public function comparable(ScanRecord $scan, int $limit = 10): Collection
    {
        return $this->history()
            ->filter(fn (ScanRecord $other): bool => $other->id !== $scan->id
                && $other->status === 'completed'
                && $other->projectName === $scan->projectName)
            ->take($limit)
            ->values();
    }

    public function logsSince(string $id, int $afterLogId = 0): array
    {
        if (! $this->owns($id)) {
            return [];
        }

        return array_values(array_filter(
            Cache::get(static::logsKey($id), []),
            fn (array $log): bool => $log['id'] > $afterLogId,
        ));
    }

    public function delete(string $id): void
    {
        if (! $this->owns($id)) {
            return;
        }

        Cache::forget(static::key($id));
        Cache::forget(static::logsKey($id));
        session([self::SESSION_SCANS => array_values(array_diff(session(self::SESSION_SCANS, []), [$id]))]);
    }

    public function ignoredMap(string $projectPath): array
    {
        return session(self::SESSION_IGNORED.'.'.md5($projectPath), []);
    }

    public function ignore(string $projectPath, string $scanner, string $fingerprint, ?string $reason): void
    {
        $key = self::SESSION_IGNORED.'.'.md5($projectPath);
        session([$key => array_merge(session($key, []), ["{$scanner}:{$fingerprint}" => $reason ?? 'No reason provided'])]);
    }

    public function unignore(string $projectPath, string $scanner, string $fingerprint): void
    {
        $key = self::SESSION_IGNORED.'.'.md5($projectPath);
        $map = session($key, []);
        unset($map["{$scanner}:{$fingerprint}"]);
        session([$key => $map]);
    }

    protected static function put(string $id, array $scan): void
    {
        Cache::put(static::key($id), $scan, static::ttl());
    }

    protected static function key(string $id): string
    {
        return 'web-scan:'.$id;
    }

    protected static function logsKey(string $id): string
    {
        return 'web-scan:'.$id.':logs';
    }

    protected static function ttl(): \DateTimeInterface
    {
        return now()->addMinutes((int) config('checkmycode.web.results_ttl_minutes', 60));
    }
}
