<?php

namespace App\Services;

use App\Console\Commands\ScanCommand;
use App\Models\Scan;
use App\Models\ScanLog;
use App\Scanning\ScanEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Events\ChildProcess\MessageReceived;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Events\ChildProcess\StartupError;
use Native\Desktop\Facades\ChildProcess;

/**
 * Desktop mode: runs `php artisan mysafecoding:scan --machine` as a NativePHP
 * child process, streams its log lines into scan_logs and stores the final
 * results on the scan record.
 */
class ScannerService
{
    public static function checkDependencies(): array
    {
        return ScanEngine::missingTools();
    }

    public static function startScan(Scan $scan): void
    {
        $outputPath = storage_path('app/scans/scan-'.$scan->id.'-'.now()->format('Ymd_His'));

        $scan->update([
            'status' => 'running',
            'output_path' => $outputPath,
            'started_at' => now(),
        ]);

        static::createLog($scan->id, 'Starting security scan...');

        $cmd = [
            'mysafecoding:scan',
            $scan->project_path,
            '--machine',
            '--no-defaults',
            '--output-dir='.$outputPath,
        ];

        foreach (array_values(array_unique($scan->exclusions ?? [])) as $exclusion) {
            $cmd[] = '--exclude='.$exclusion;
        }

        static::registerListeners();

        ChildProcess::artisan(
            cmd: $cmd,
            alias: static::alias($scan->id),
            env: ScanEngine::processEnvironment(),
        );
    }

    public static function stopScan(Scan $scan): void
    {
        ChildProcess::stop(static::alias($scan->id));

        $scan->update(['status' => 'failed', 'completed_at' => now()]);
        static::createLog($scan->id, 'Scan stopped.', 'system', 'warn');
    }

    public static function registerListeners(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        Event::listen(MessageReceived::class, function (MessageReceived $event) {
            static::persistEventOutput($event->alias, $event->data, 'info');
        });

        Event::listen(ErrorReceived::class, function (ErrorReceived $event) {
            static::persistEventOutput($event->alias, $event->data, 'error');
        });

        Event::listen(StartupError::class, function (StartupError $event) {
            $scanId = static::scanIdFromAlias($event->alias);
            if (! $scanId) {
                return;
            }

            Scan::whereKey($scanId)->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            static::createLog($scanId, 'Unable to start scan process: '.$event->error, 'system', 'error');
        });

        Event::listen(ProcessExited::class, function (ProcessExited $event) {
            $scanId = static::scanIdFromAlias($event->alias);
            $scan = $scanId ? Scan::find($scanId) : null;
            if (! $scan || $scan->status !== 'running') {
                return;
            }

            static::completeScan($scan, $event->code);
        });
    }

    public static function completeScan(Scan $scan, int $exitCode): void
    {
        $resultsFile = $scan->output_path.'/results.json';
        $results = is_file($resultsFile) ? json_decode((string) file_get_contents($resultsFile), true) : null;
        $succeeded = $exitCode === 0 && is_array($results);
        $findingsCount = $succeeded ? ScanEngine::findingsCount($results) : 0;

        $scan->update([
            'status' => $succeeded ? 'completed' : 'failed',
            'results' => $results,
            'findings_count' => $findingsCount,
            'completed_at' => now(),
        ]);

        if ($succeeded) {
            try {
                \Native\Desktop\Facades\Notification::title('MySafeCoding Scan Complete')
                    ->message($findingsCount > 0 ? "{$findingsCount} security findings detected in ".basename($scan->project_path) : 'No issues found in '.basename($scan->project_path))
                    ->show();
            } catch (\Throwable) {
                // Notification is non-critical, ignore failures
            }
        } else {
            static::createLog($scan->id, "Scan failed with exit code {$exitCode}.", 'system', 'error');
        }
    }

    protected static function persistEventOutput(string $alias, mixed $data, string $level): void
    {
        $scanId = static::scanIdFromAlias($alias);
        if (! $scanId) {
            return;
        }

        foreach (static::completeLines($alias, $data) as $line) {
            if (str_starts_with($line, ScanCommand::MACHINE_PREFIX)) {
                $payload = json_decode(substr($line, strlen(ScanCommand::MACHINE_PREFIX)), true);

                if (isset($payload['line'])) {
                    static::createLog($scanId, $payload['line'], $payload['scanner'] ?? 'system', $payload['level'] ?? $level);
                }

                continue;
            }

            // Anything else is raw stderr/stdout from PHP itself.
            static::createLog($scanId, $line, 'system', $level);
        }
    }

    /**
     * Child-process output arrives in arbitrary chunks, each as its own
     * request, so a trailing partial line is parked in the cache until the
     * rest of it arrives.
     *
     * @return list<string>
     */
    protected static function completeLines(string $alias, mixed $data): array
    {
        $chunk = is_string($data) ? $data : json_encode($data);
        $buffered = Cache::pull('scan-output-buffer:'.$alias, '').$chunk;

        $lines = preg_split("/\r\n|\n|\r/", $buffered) ?: [];
        $partial = array_pop($lines);

        // A machine line without its newline yet is incomplete; plain output is flushed as-is.
        if ($partial !== null && $partial !== '') {
            if (str_starts_with($partial, ScanCommand::MACHINE_PREFIX) && json_decode(substr($partial, strlen(ScanCommand::MACHINE_PREFIX))) === null) {
                Cache::put('scan-output-buffer:'.$alias, $partial, now()->addMinutes(10));
            } elseif (str_starts_with(ScanCommand::MACHINE_PREFIX, $partial)) {
                Cache::put('scan-output-buffer:'.$alias, $partial, now()->addMinutes(10));
            } else {
                $lines[] = $partial;
            }
        }

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    protected static function createLog(int $scanId, string $line, string $scanner = 'system', string $level = 'info'): void
    {
        ScanLog::create([
            'scan_id' => $scanId,
            'line' => $line,
            'scanner' => $scanner,
            'level' => $level,
        ]);
    }

    protected static function alias(int $scanId): string
    {
        return 'scanner-'.$scanId;
    }

    protected static function scanIdFromAlias(string $alias): ?int
    {
        if (! preg_match('/^scanner-(\d+)$/', $alias, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function installDependencies(): void
    {
        ChildProcess::start(
            cmd: ['bash', resource_path('cli/mysafecoding-setup.sh')],
            alias: 'setup',
            cwd: resource_path('cli'),
            env: ScanEngine::processEnvironment(),
        );
    }
}
