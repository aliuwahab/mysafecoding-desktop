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
            resource_path('cli/mysafecoding-scan.sh'),
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

            // Run the framework rule pack when the detected type is Laravel.
            if ($projectType === 'laravel') {
                static::createLog($scanId, 'Running framework rule analysis for Laravel...', 'laravel_rules');
                $engine = new LaravelRuleEngine;
                $laravelResults = $engine->analyze($scan->project_path);
                $results['laravel_rules'] = $laravelResults;
                $results['summary']['laravel_rules'] = $laravelResults['summary'];
                $results['summary']['laravel_rules_total'] = $laravelResults['summary']['total'];

                if ($laravelResults['summary']['total'] > 0) {
                    static::createLog($scanId, "Framework rule analysis found {$laravelResults['summary']['total']} issues ({$laravelResults['summary']['CRITICAL']} critical, {$laravelResults['summary']['HIGH']} high, {$laravelResults['summary']['MEDIUM']} medium).", 'laravel_rules');
                } else {
                    static::createLog($scanId, 'Framework rule analysis: no issues found.', 'laravel_rules');
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

            // Log code quality results (not counted as security findings)
            $qualityTotal = $results['summary']['code_quality_total'] ?? 0;
            if ($qualityTotal > 0) {
                static::createLog($scanId, "Code quality analysis found {$qualityTotal} suggestions.", 'code_quality');
            }

            $findingsCount = 0;
            if (isset($results['summary'])) {
                foreach (['trivy_total', 'semgrep_total', 'gitleaks_total', 'laravel_rules_total', 'env_audit_total'] as $key) {
                    $findingsCount += $results['summary'][$key] ?? 0;
                }
                // Note: code_quality_total is intentionally NOT counted as security findings
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
                    \Native\Desktop\Facades\Notification::title('MySafeCoding Scan Complete')
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
        if (str_contains($lower, 'framework rule') || str_contains($lower, 'laravel rule') || str_contains($lower, 'laravel-specific')) {
            return 'laravel_rules';
        }
        if (str_contains($lower, 'code quality') || str_contains($lower, 'best practice')) {
            return 'code_quality';
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
            'code_quality' => null,
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

        $qualityFile = $outputDir.'/quality-raw.json';
        if (file_exists($qualityFile)) {
            $quality = json_decode(file_get_contents($qualityFile), true);
            $results['code_quality'] = $quality;

            $counts = ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0];
            foreach ($quality['results'] ?? [] as $finding) {
                $sev = $finding['extra']['severity'] ?? 'INFO';
                $counts[$sev] = ($counts[$sev] ?? 0) + 1;
            }
            $results['summary']['code_quality'] = $counts;
            $results['summary']['code_quality_total'] = array_sum($counts);
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
        if (file_exists($path.'/composer.json')) {
            if (file_exists($path.'/artisan') || static::composerHasPackage($path, 'laravel/framework')) {
                return 'laravel';
            }
            if (static::composerHasPackage($path, 'symfony/framework-bundle')) {
                return 'symfony';
            }

            return 'php';
        }
        if (file_exists($path.'/package.json')) {
            foreach ([
                'nextjs' => ['next'],
                'nuxt' => ['nuxt'],
                'angular' => ['@angular/core', 'angular'],
                'svelte' => ['svelte'],
                'vue' => ['vue'],
                'react' => ['react'],
                'nestjs' => ['@nestjs/core'],
                'express' => ['express'],
            ] as $type => $packages) {
                foreach ($packages as $package) {
                    if (static::packageJsonHasPackage($path, $package)) {
                        return $type;
                    }
                }
            }

            return 'node';
        }
        if (file_exists($path.'/requirements.txt') || file_exists($path.'/pyproject.toml') || file_exists($path.'/setup.py')) {
            if (static::pythonProjectMentions($path, 'django')) {
                return 'django';
            }
            if (static::pythonProjectMentions($path, 'fastapi')) {
                return 'fastapi';
            }
            if (static::pythonProjectMentions($path, 'flask')) {
                return 'flask';
            }

            return 'python';
        }
        if (file_exists($path.'/Gemfile')) {
            return static::fileContains($path.'/Gemfile', 'rails') ? 'rails' : 'ruby';
        }
        if (file_exists($path.'/go.mod')) {
            return 'go';
        }
        if (file_exists($path.'/Cargo.toml')) {
            return 'rust';
        }
        if (file_exists($path.'/mix.exs')) {
            return static::fileContains($path.'/mix.exs', 'phoenix') ? 'phoenix' : 'elixir';
        }
        if (file_exists($path.'/build.gradle') || file_exists($path.'/build.gradle.kts') || file_exists($path.'/pom.xml')) {
            return static::javaProjectMentionsSpring($path) ? 'spring' : 'java';
        }
        if (static::hasFileMatching($path, '*.csproj')) {
            return 'dotnet';
        }

        return 'unknown';
    }

    protected static function composerHasPackage(string $path, string $package): bool
    {
        $composer = static::readJsonFile($path.'/composer.json');

        return isset($composer['require'][$package]) || isset($composer['require-dev'][$package]);
    }

    protected static function packageJsonHasPackage(string $path, string $package): bool
    {
        $packageJson = static::readJsonFile($path.'/package.json');

        foreach (['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies'] as $section) {
            if (isset($packageJson[$section][$package])) {
                return true;
            }
        }

        return false;
    }

    protected static function pythonProjectMentions(string $path, string $package): bool
    {
        foreach (['requirements.txt', 'pyproject.toml', 'setup.py'] as $file) {
            if (static::fileContains($path.'/'.$file, $package)) {
                return true;
            }
        }

        return false;
    }

    protected static function javaProjectMentionsSpring(string $path): bool
    {
        foreach (['build.gradle', 'build.gradle.kts', 'pom.xml'] as $file) {
            if (static::fileContains($path.'/'.$file, 'spring-boot') || static::fileContains($path.'/'.$file, 'springframework')) {
                return true;
            }
        }

        return false;
    }

    protected static function fileContains(string $file, string $needle): bool
    {
        return file_exists($file) && str_contains(strtolower(file_get_contents($file) ?: ''), strtolower($needle));
    }

    protected static function readJsonFile(string $file): array
    {
        if (! file_exists($file)) {
            return [];
        }

        $json = json_decode(file_get_contents($file) ?: '', true);

        return is_array($json) ? $json : [];
    }

    protected static function hasFileMatching(string $path, string $pattern): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                return true;
            }
        }

        return false;
    }

    public static function installDependencies(): void
    {
        ChildProcess::start(
            cmd: ['bash', resource_path('cli/mysafecoding-setup.sh')],
            alias: 'setup',
            cwd: resource_path('cli'),
            env: static::processEnvironment(),
        );
    }
}
