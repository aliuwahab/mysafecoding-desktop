<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\Scanners\LaravelRuleEngine;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Events\ChildProcess\MessageReceived;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Events\ChildProcess\StartupError;
use Native\Desktop\Facades\ChildProcess;

class ScannerService
{
    public static function checkDependencies(): array
    {
        $tools = ['trivy', 'semgrep', 'gitleaks', 'jq'];
        $missing = [];

        foreach ($tools as $tool) {
            $result = trim(shell_exec("which {$tool} 2>/dev/null") ?? '');
            if (empty($result)) {
                $missing[] = $tool;
            }
        }

        return $missing;
    }

    public static function startScan(Scan $scan): void
    {
        $alias = 'scanner-'.$scan->id;
        $outputPath = resource_path('cli/ls-output/scan-'.$scan->id.'-'.now()->format('Ymd_His'));

        $scan->update([
            'status' => 'running',
            'output_path' => $outputPath,
            'started_at' => now(),
        ]);

        static::createLog($scan->id, 'Starting security scan...');

        $cmd = [
            'bash',
            resource_path('cli/laravelshield-scan.sh'),
            $scan->project_path,
            '--output-dir',
            $outputPath,
        ];

        $exclusions = array_values(array_unique($scan->exclusions ?? []));
        foreach ($exclusions as $exclusion) {
            $cmd[] = '--exclude';
            $cmd[] = $exclusion;
        }

        static::registerListeners();

        ChildProcess::start(
            cmd: $cmd,
            alias: $alias,
            cwd: resource_path('cli'),
            env: static::processEnvironment([
                'LS_NON_INTERACTIVE' => '1',
            ]),
        );
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
            if (! $scanId) {
                return;
            }

            $scan = Scan::find($scanId);
            if (! $scan) {
                return;
            }

            $results = static::parseResults($scan);

            // Detect project type from scan output
            $projectType = static::detectProjectType($scan);
            $results['project_type'] = $projectType;

            // Run Laravel-specific rule engine only for Laravel projects
            if ($projectType === 'laravel') {
                static::createLog($scanId, 'Running Laravel-specific rule analysis...', 'laravel_rules');
                $engine = new LaravelRuleEngine;
                $laravelResults = $engine->analyze($scan->project_path);
                $results['laravel_rules'] = $laravelResults;
                $results['summary']['laravel_rules'] = $laravelResults['summary'];
                $results['summary']['laravel_rules_total'] = $laravelResults['summary']['total'];

                if ($laravelResults['summary']['total'] > 0) {
                    static::createLog($scanId, "Laravel Rule Engine found {$laravelResults['summary']['total']} issues ({$laravelResults['summary']['CRITICAL']} critical, {$laravelResults['summary']['HIGH']} high, {$laravelResults['summary']['MEDIUM']} medium).", 'laravel_rules');
                } else {
                    static::createLog($scanId, 'Laravel Rule Engine: no issues found.', 'laravel_rules');
                }
            }

            // Run .env audit for any project with a .env file
            if (file_exists(rtrim($scan->project_path, '/').'/.env')) {
                static::createLog($scanId, 'Running .env security audit...', 'system');
                $envResults = EnvAuditor::audit($scan->project_path);
                $results['env_audit'] = $envResults;
                $results['summary']['env_audit'] = $envResults['summary'];
                $results['summary']['env_audit_total'] = $envResults['summary']['total'];

                if ($envResults['summary']['total'] > 0) {
                    static::createLog($scanId, "Env audit found {$envResults['summary']['total']} issues ({$envResults['summary']['CRITICAL']} critical, {$envResults['summary']['HIGH']} high, {$envResults['summary']['MEDIUM']} medium).", 'system');
                } else {
                    static::createLog($scanId, 'Env audit: no issues found.', 'system');
                }
            }

            $findingsCount = 0;
            if (isset($results['summary'])) {
                foreach (['trivy_total', 'semgrep_total', 'gitleaks_total', 'laravel_rules_total', 'env_audit_total'] as $key) {
                    $findingsCount += $results['summary'][$key] ?? 0;
                }
            }

            $scan->update([
                'status' => $event->code === 0 ? 'completed' : 'failed',
                'results' => $results,
                'findings_count' => $findingsCount,
                'completed_at' => now(),
            ]);

            // Send native notification
            if ($event->code === 0) {
                try {
                    \Native\Desktop\Facades\Notification::title('LaraGuard Scan Complete')
                        ->message($findingsCount > 0 ? "{$findingsCount} security findings detected in ".basename($scan->project_path) : 'No issues found in '.basename($scan->project_path))
                        ->show();
                } catch (\Throwable $e) {
                    // Notification is non-critical, ignore failures
                }
            }

            static::createLog(
                $scanId,
                $event->code === 0
                    ? "Scan completed. {$findingsCount} findings detected."
                    : "Scan failed with exit code {$event->code}.",
                'system',
                $event->code === 0 ? 'info' : 'error',
            );
        });
    }

    protected static function processEnvironment(array $overrides = []): array
    {
        return array_merge([
            'HOME' => env('HOME', '/Users/'.get_current_user()),
            'PATH' => env('PATH', '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'),
        ], $overrides);
    }

    protected static function persistEventOutput(string $alias, mixed $data, string $level): void
    {
        $scanId = static::scanIdFromAlias($alias);
        if (! $scanId) {
            return;
        }

        foreach (static::eventLines($data) as $line) {
            static::createLog(
                $scanId,
                $line,
                static::detectScanner($line),
                $level,
            );
        }
    }

    protected static function eventLines(mixed $data): array
    {
        $payload = is_string($data) ? $data : json_encode($data);
        $payload = trim((string) $payload);

        if ($payload === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split("/\r\n|\n|\r/", $payload) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));
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

    protected static function scanIdFromAlias(string $alias): ?int
    {
        if (! preg_match('/^scanner-(\d+)$/', $alias, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    protected static function detectScanner(string $line): string
    {
        $lower = strtolower($line);

        if (str_contains($lower, 'trivy') || str_contains($lower, 'sca')) {
            return 'trivy';
        }
        if (str_contains($lower, 'semgrep') || str_contains($lower, 'sast')) {
            return 'semgrep';
        }
        if (str_contains($lower, 'gitleaks') || str_contains($lower, 'secret')) {
            return 'gitleaks';
        }
        if (str_contains($lower, 'laravel rule') || str_contains($lower, 'laravel-specific')) {
            return 'laravel_rules';
        }

        return 'system';
    }

    protected static function parseResults(Scan $scan): array
    {
        $outputDir = static::resolveOutputDirectory($scan);
        if (! $outputDir) {
            return ['error' => 'No scan output found'];
        }

        $results = [
            'output_dir' => $outputDir,
            'trivy' => null,
            'semgrep' => null,
            'gitleaks' => null,
            'summary' => [],
        ];

        $trivyFile = $outputDir.'/trivy-raw.json';
        if (file_exists($trivyFile)) {
            $trivy = json_decode(file_get_contents($trivyFile), true);
            $results['trivy'] = $trivy;

            $counts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
            foreach ($trivy['Results'] ?? [] as $result) {
                foreach ($result['Vulnerabilities'] ?? [] as $vuln) {
                    $sev = $vuln['Severity'] ?? 'LOW';
                    $counts[$sev] = ($counts[$sev] ?? 0) + 1;
                }
            }
            $results['summary']['trivy'] = $counts;
            $results['summary']['trivy_total'] = array_sum($counts);
        }

        $semgrepFile = $outputDir.'/semgrep-raw.json';
        if (file_exists($semgrepFile)) {
            $semgrep = json_decode(file_get_contents($semgrepFile), true);
            $results['semgrep'] = $semgrep;

            $counts = ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0];
            foreach ($semgrep['results'] ?? [] as $finding) {
                $sev = $finding['extra']['severity'] ?? 'INFO';
                $counts[$sev] = ($counts[$sev] ?? 0) + 1;
            }
            $results['summary']['semgrep'] = $counts;
            $results['summary']['semgrep_total'] = array_sum($counts);
        }

        $gitleaksFile = $outputDir.'/gitleaks-raw.json';
        if (file_exists($gitleaksFile)) {
            $gitleaks = json_decode(file_get_contents($gitleaksFile), true);
            $results['gitleaks'] = $gitleaks;
            $results['summary']['gitleaks_total'] = is_array($gitleaks) ? count($gitleaks) : 0;
        }

        $summaryFile = $outputDir.'/summary.txt';
        if (file_exists($summaryFile)) {
            $results['summary']['text'] = file_get_contents($summaryFile);
        }

        return $results;
    }

    protected static function resolveOutputDirectory(Scan $scan): ?string
    {
        if ($scan->output_path && is_dir($scan->output_path)) {
            return $scan->output_path;
        }

        $legacyBasePath = resource_path('cli/ls-output');
        if (! is_dir($legacyBasePath)) {
            return null;
        }

        $dirs = glob($legacyBasePath.'/*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            return null;
        }

        usort($dirs, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $dirs[0];
    }

    protected static function detectProjectType(Scan $scan): string
    {
        $outputDir = static::resolveOutputDirectory($scan);
        if ($outputDir) {
            $typeFile = $outputDir.'/project-type.txt';
            if (file_exists($typeFile)) {
                return trim(file_get_contents($typeFile));
            }
        }

        // Fallback detection
        $path = rtrim($scan->project_path, '/');
        if (file_exists($path.'/artisan') && file_exists($path.'/composer.json')) {
            return 'laravel';
        }
        if (file_exists($path.'/composer.json')) {
            return 'php';
        }
        if (file_exists($path.'/package.json')) {
            return 'node';
        }
        if (file_exists($path.'/requirements.txt') || file_exists($path.'/pyproject.toml')) {
            return 'python';
        }
        if (file_exists($path.'/go.mod')) {
            return 'go';
        }
        if (file_exists($path.'/mix.exs')) {
            return 'elixir';
        }

        return 'unknown';
    }

    public static function installDependencies(): void
    {
        ChildProcess::start(
            cmd: ['bash', resource_path('cli/laravelshield-setup.sh')],
            alias: 'setup',
            cwd: resource_path('cli'),
            env: static::processEnvironment(),
        );
    }
}
