<?php

namespace App\Scanning;

use App\Scanning\Lifecycle\EndOfLifeScanner;
use App\Scanning\Rules\PatternRuleScanner;
use App\Services\EnvAuditor;
use App\Services\Scanners\LaravelRuleEngine;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

/**
 * The one scan pipeline behind the desktop app, the hosted web scanner and
 * the CLI. Runs every engine against a local directory and returns a single
 * results array (also written to <outputDir>/results.json).
 */
class ScanEngine
{
    /** Totals that count toward the security grade and findings count. */
    public const SECURITY_TOTALS = [
        'trivy_total',
        'semgrep_total',
        'gitleaks_total',
        'laravel_rules_total',
        'custom_rules_total',
        'env_audit_total',
        'endoflife_total',
        'trivy_config_total',
    ];

    /** @var callable(string, string, string): void */
    protected $logger;

    protected string $projectPath;

    protected string $outputDir;

    /** @var list<string> */
    protected array $exclusions = [];

    protected array $options = [];

    public function __construct(
        protected Filesystem $files = new Filesystem,
    ) {}

    /**
     * Tools the engine shells out to that are not installed.
     *
     * @return list<string>
     */
    public static function missingTools(): array
    {
        $missing = [];

        foreach (config('checkmycode.binaries') as $tool => $binary) {
            $found = str_contains($binary, '/')
                ? is_executable($binary)
                : Process::env(static::processEnvironment())->run(['which', $binary])->successful();

            if (! $found) {
                $missing[] = $tool;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $exclusions
     * @param  callable(string $line, string $scanner, string $level): void|null  $log
     * @param  array{git_history?: bool, redact_secrets?: bool, lifecycle?: bool, respect_gitignore?: bool}  $options
     */
    public function run(string $projectPath, string $outputDir, array $exclusions = [], ?callable $log = null, array $options = []): array
    {
        $this->logger = $log ?? static function (): void {};
        $this->exclusions = array_values(array_unique(array_filter(array_map(
            static fn (string $exclusion): string => trim($exclusion, " \t/"),
            $exclusions,
        ))));
        $this->options = $options + [
            'git_history' => true,
            'redact_secrets' => false,
            'lifecycle' => (bool) config('checkmycode.endoflife.enabled', true),
            'respect_gitignore' => true,
        ];

        $startedAt = microtime(true);
        $singleFileWorkspace = null;

        if (is_file($projectPath)) {
            // Scan a single file by giving every engine a one-file directory.
            $singleFileWorkspace = sys_get_temp_dir().'/checkmycode-file-'.bin2hex(random_bytes(6));
            $this->files->ensureDirectoryExists($singleFileWorkspace);
            $this->files->copy($projectPath, $singleFileWorkspace.'/'.basename($projectPath));
            $projectPath = $singleFileWorkspace;
        }

        if (! is_dir($projectPath)) {
            throw new \InvalidArgumentException("Path not found: {$projectPath}");
        }

        $this->projectPath = rtrim((string) realpath($projectPath), '/');
        $this->outputDir = rtrim($outputDir, '/');
        $this->files->ensureDirectoryExists($this->outputDir);

        try {
            $results = $this->scan();
        } finally {
            if ($singleFileWorkspace) {
                $this->files->deleteDirectory($singleFileWorkspace);
            }
        }

        $results['meta']['duration_seconds'] = (int) round(microtime(true) - $startedAt);

        $this->files->put(
            $this->outputDir.'/results.json',
            json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        );

        return $results;
    }

    protected function scan(): array
    {
        $projectType = ProjectDetector::detect($this->projectPath);
        $this->files->put($this->outputDir.'/project-type.txt', $projectType);
        $this->log('Detected project type: '.ProjectDetector::label($projectType)." ({$projectType})");

        if ($this->exclusions !== []) {
            $this->log('Excluding: '.implode(', ', $this->exclusions));
        }

        $missing = static::missingTools();
        foreach ($missing as $tool) {
            $this->log("{$tool} is not installed, skipping that engine.", 'system', 'warn');
        }

        $results = [
            'project_type' => $projectType,
            'trivy' => null,
            'trivy_config' => null,
            'licenses' => null,
            'semgrep' => null,
            'gitleaks' => null,
            'code_quality' => null,
            'laravel_rules' => null,
            'custom_rules' => null,
            'env_audit' => null,
            'endoflife' => null,
            'summary' => [],
            'meta' => [
                'engine_version' => 2,
                'git_history' => false,
            ],
        ];

        if (! in_array('trivy', $missing, true)) {
            $results = $this->runTrivy($results);
        }

        if (! in_array('semgrep', $missing, true)) {
            $results = $this->runSemgrep($results, $projectType);
        }

        if (! in_array('gitleaks', $missing, true)) {
            $results = $this->runGitleaks($results);
        }

        $results = $this->runRulePacks($results, $projectType);
        $results = $this->runEnvAudit($results);

        if ($this->options['lifecycle']) {
            $results = $this->runLifecycle($results);
        }

        if (! in_array('semgrep', $missing, true)) {
            $results = $this->runCodeQuality($results, $projectType);
        }

        if ($this->options['redact_secrets']) {
            $results = static::redactSecrets($results);
        }

        $results['summary']['findings_total'] = static::findingsCount($results);
        $results['summary']['grade'] = ScanGrade::fromSummary($results['summary']);

        $this->log(sprintf(
            'Scan complete: %d security findings, %d quality suggestions. Grade %s.',
            $results['summary']['findings_total'],
            $results['summary']['code_quality_total'] ?? 0,
            $results['summary']['grade'],
        ));

        return $results;
    }

    public static function findingsCount(array $results): int
    {
        $total = 0;
        foreach (static::SECURITY_TOTALS as $key) {
            $total += (int) ($results['summary'][$key] ?? 0);
        }

        return $total;
    }

    // ── Trivy: dependency CVEs, IaC misconfiguration, licenses ──────────────

    protected function runTrivy(array $results): array
    {
        $this->log('Trivy: scanning dependency manifests and lockfiles against public CVE databases (NVD, GitHub Advisories, OSV)...', 'trivy');
        $this->log('Trivy: checking Dockerfiles, Kubernetes, Terraform and other IaC for misconfiguration, and package licenses...', 'trivy');

        $output = $this->outputDir.'/trivy-raw.json';
        // Never pick up a trivy.yaml from the scanned project (it could swap the DB or settings).
        $trivyConfig = $this->outputDir.'/trivy.yaml';
        $this->files->put($trivyConfig, "# CheckMyCode passes all Trivy options on the command line.\n");
        $command = [
            config('checkmycode.binaries.trivy'), 'fs',
            '--config', $trivyConfig,
            '--scanners', 'vuln,misconfig,license',
            '--format', 'json',
            '--output', $output,
            '--severity', 'CRITICAL,HIGH,MEDIUM,LOW',
            '--quiet',
        ];
        foreach ($this->exclusions as $exclusion) {
            array_push($command, '--skip-dirs', $exclusion, '--skip-dirs', '**/'.$exclusion);
        }
        $command[] = '.';

        $process = $this->runTool($command, 'trivy');
        $trivy = $this->readJson($output);

        if ($trivy === null) {
            $this->log('Trivy produced no output: '.$this->firstLine($process->errorOutput()), 'trivy', 'warn');

            return $results;
        }

        $vulnCounts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        $configCounts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        $misconfigurations = [];
        $licenses = [];
        $flagged = config('checkmycode.license_policy.flagged_licenses', []);

        foreach ($trivy['Results'] ?? [] as $index => $target) {
            foreach ($target['Vulnerabilities'] ?? [] as $vuln) {
                $severity = $vuln['Severity'] ?? 'LOW';
                $vulnCounts[$severity] = ($vulnCounts[$severity] ?? 0) + 1;
            }

            foreach ($target['Misconfigurations'] ?? [] as $misconfig) {
                if (($misconfig['Status'] ?? 'FAIL') !== 'FAIL') {
                    continue;
                }
                $severity = $misconfig['Severity'] ?? 'LOW';
                $configCounts[$severity] = ($configCounts[$severity] ?? 0) + 1;
                $misconfigurations[] = [
                    'rule_id' => $misconfig['AVDID'] ?? $misconfig['ID'] ?? 'misconfig',
                    'title' => $misconfig['Title'] ?? 'Misconfiguration',
                    'severity' => $severity,
                    'category' => $target['Type'] ?? 'config',
                    'description' => $misconfig['Message'] ?? $misconfig['Description'] ?? '',
                    'file_path' => $target['Target'] ?? '',
                    'line_start' => $misconfig['CauseMetadata']['StartLine'] ?? null,
                    'code_snippet' => null,
                    'fix_guidance' => $misconfig['Resolution'] ?? null,
                    'link' => $misconfig['PrimaryURL'] ?? null,
                    'fingerprint' => sha1(($misconfig['AVDID'] ?? '').'|'.($target['Target'] ?? '').'|'.($misconfig['CauseMetadata']['StartLine'] ?? '')),
                ];
            }

            foreach ($target['Licenses'] ?? [] as $license) {
                $name = $license['Name'] ?? '';
                if (! in_array($license['Severity'] ?? '', ['CRITICAL', 'HIGH'], true) && ! in_array($name, $flagged, true)) {
                    continue;
                }
                $licenses[] = [
                    'package' => $license['PkgName'] ?? '',
                    'license' => $name,
                    'category' => $license['Category'] ?? 'unknown',
                    'severity' => $license['Severity'] ?? 'HIGH',
                    'file_path' => $license['FilePath'] ?? ($target['Target'] ?? ''),
                ];
            }

            // Keep the stored raw output focused on vulnerabilities.
            unset($trivy['Results'][$index]['Misconfigurations'], $trivy['Results'][$index]['MisconfSummary'], $trivy['Results'][$index]['Licenses']);
        }

        $trivy['Results'] = array_values(array_filter(
            $trivy['Results'] ?? [],
            static fn (array $target): bool => ! empty($target['Vulnerabilities']),
        ));

        $results['trivy'] = $trivy;
        $results['summary']['trivy'] = $vulnCounts;
        $results['summary']['trivy_total'] = array_sum($vulnCounts);

        $results['trivy_config'] = ['findings' => $misconfigurations, 'summary' => ['total' => count($misconfigurations)] + $configCounts];
        $results['summary']['trivy_config'] = $configCounts;
        $results['summary']['trivy_config_total'] = count($misconfigurations);

        $results['licenses'] = ['findings' => $licenses];
        $results['summary']['licenses_total'] = count($licenses);

        $this->log(sprintf(
            'Trivy: %d vulnerable dependencies (%d critical, %d high), %d misconfigurations, %d flagged licenses.',
            $results['summary']['trivy_total'], $vulnCounts['CRITICAL'], $vulnCounts['HIGH'], count($misconfigurations), count($licenses),
        ), 'trivy');

        return $results;
    }

    // ── Semgrep: SAST with registry packs + CheckMyCode custom rules ───────

    protected function runSemgrep(array $results, string $projectType): array
    {
        $configs = SemgrepRulesets::security($projectType);
        $this->log('Semgrep: static analysis with '.implode(', ', array_map(SemgrepRulesets::displayName(...), $configs)).'...', 'semgrep');

        $output = $this->outputDir.'/semgrep-raw.json';
        $process = $this->runTool($this->semgrepCommand($configs, $output), 'semgrep');
        $semgrep = $this->readJson($output);

        if ($semgrep === null) {
            $this->log('Semgrep produced no output: '.$this->firstLine($process->errorOutput()), 'semgrep', 'warn');

            return $results;
        }

        $semgrep = $this->trimSemgrep($semgrep);
        $counts = $this->semgrepCounts($semgrep);

        $results['semgrep'] = $semgrep;
        $results['summary']['semgrep'] = $counts;
        $results['summary']['semgrep_total'] = array_sum($counts);

        $this->log("Semgrep: {$results['summary']['semgrep_total']} findings ({$counts['ERROR']} errors, {$counts['WARNING']} warnings).", 'semgrep');

        return $results;
    }

    protected function runCodeQuality(array $results, string $projectType): array
    {
        $configs = SemgrepRulesets::quality($projectType);

        if ($configs === []) {
            $this->log('Code quality: no best-practice ruleset for this project type, skipping.', 'code_quality');

            return $results;
        }

        $this->log('Code quality: checking best practices with '.implode(', ', array_map(SemgrepRulesets::displayName(...), $configs)).'...', 'code_quality');

        $output = $this->outputDir.'/quality-raw.json';
        $process = $this->runTool($this->semgrepCommand($configs, $output), 'code_quality');
        $quality = $this->readJson($output);

        if ($quality === null) {
            $this->log('Code quality produced no output: '.$this->firstLine($process->errorOutput()), 'code_quality', 'warn');

            return $results;
        }

        // Anything the security pass already reported is not repeated here.
        $securityIds = array_unique(array_column($results['semgrep']['results'] ?? [], 'check_id'));
        $quality = $this->trimSemgrep($quality);
        $quality['results'] = array_values(array_filter(
            $quality['results'] ?? [],
            static fn (array $finding): bool => ! in_array($finding['check_id'] ?? '', $securityIds, true),
        ));
        $counts = $this->semgrepCounts($quality);

        $results['code_quality'] = $quality;
        $results['summary']['code_quality'] = $counts;
        $results['summary']['code_quality_total'] = array_sum($counts);

        $this->log("Code quality: {$results['summary']['code_quality_total']} suggestions.", 'code_quality');

        return $results;
    }

    /**
     * @param  list<string>  $configs
     * @return list<string>
     */
    protected function semgrepCommand(array $configs, string $output): array
    {
        $command = [config('checkmycode.binaries.semgrep'), 'scan'];
        foreach ($configs as $config) {
            array_push($command, '--config', $config);
        }
        array_push($command, '--json', '--output', $output, '--metrics', 'off', '--disable-version-check', '--quiet');
        if (! $this->options['respect_gitignore']) {
            $command[] = '--no-git-ignore';
        }
        foreach ($this->exclusions as $exclusion) {
            array_push($command, '--exclude', $exclusion);
        }
        $command[] = '.';

        return $command;
    }

    protected function trimSemgrep(array $semgrep): array
    {
        return [
            'results' => array_map(static function (array $finding): array {
                unset($finding['extra']['fingerprint'], $finding['extra']['validation_state'], $finding['extra']['engine_kind']);
                // Semgrep prefixes local rule files with their path: "resources.scanners.checkmycode.x" -> "checkmycode.x"
                $finding['check_id'] = preg_replace('/^.*?\b(checkmycode\.)/', '$1', $finding['check_id'] ?? '');

                return $finding;
            }, $semgrep['results'] ?? []),
            'errors' => array_slice($semgrep['errors'] ?? [], 0, 20),
        ];
    }

    /**
     * @return array{ERROR:int, WARNING:int, INFO:int}
     */
    protected function semgrepCounts(array $semgrep): array
    {
        $counts = ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0];
        foreach ($semgrep['results'] ?? [] as $finding) {
            $severity = $finding['extra']['severity'] ?? 'INFO';
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        return $counts;
    }

    // ── Gitleaks: secrets in files and git history ──────────────────────────

    protected function runGitleaks(array $results): array
    {
        $useHistory = $this->options['git_history'] && is_dir($this->projectPath.'/.git');
        $results['meta']['git_history'] = $useHistory;

        $this->log($useHistory
            ? 'Gitleaks: scanning current files and full git history for secrets...'
            : 'Gitleaks: scanning files for secrets (no git history available)...', 'gitleaks');

        $output = $this->outputDir.'/gitleaks-raw.json';
        $config = $this->outputDir.'/gitleaks.toml';
        $this->files->put($config, $this->gitleaksConfig());

        $command = [
            config('checkmycode.binaries.gitleaks'),
            $useHistory ? 'git' : 'dir',
            '--config', $config,
            '--report-format', 'json',
            '--report-path', $output,
            '--exit-code', '0',
            '--no-banner',
            '--log-level', 'error',
        ];
        if ($this->options['redact_secrets']) {
            $command[] = '--redact=80';
        }
        $command[] = '.';

        $process = $this->runTool($command, 'gitleaks');
        $gitleaks = $this->readJson($output);

        if ($gitleaks === null) {
            $this->log('Gitleaks produced no output: '.$this->firstLine($process->errorOutput()), 'gitleaks', 'warn');

            return $results;
        }

        $results['gitleaks'] = array_values($gitleaks);
        $results['summary']['gitleaks_total'] = count($gitleaks);

        $this->log('Gitleaks: '.count($gitleaks).' potential secrets found.', 'gitleaks');

        return $results;
    }

    protected function gitleaksConfig(): string
    {
        $paths = array_map(
            static fn (string $exclusion): string => "  '''(^|/)".preg_quote($exclusion, null)."(/|$)''',",
            $this->exclusions,
        );

        return "[extend]\nuseDefault = true\n\n[allowlist]\ndescription = \"CheckMyCode exclusions\"\npaths = [\n".implode("\n", $paths)."\n]\n";
    }

    // ── Built-in rule packs, .env audit, lifecycle ──────────────────────────

    protected function runRulePacks(array $results, string $projectType): array
    {
        if ($projectType === 'laravel') {
            $this->log('Framework rules: running the Laravel rule pack...', 'laravel_rules');
            $laravel = (new LaravelRuleEngine)->analyze($this->projectPath);
            $results['laravel_rules'] = $laravel;
            $results['summary']['laravel_rules'] = $laravel['summary'];
            $results['summary']['laravel_rules_total'] = $laravel['summary']['total'];
            $this->log("Framework rules: {$laravel['summary']['total']} issues.", 'laravel_rules');
        }

        $this->log('Custom rules: running CheckMyCode pattern rules for '.ProjectDetector::label($projectType).'...', 'custom_rules');
        $custom = (new PatternRuleScanner)->scan($this->projectPath, $projectType, $this->exclusions);
        $results['custom_rules'] = $custom;
        $results['summary']['custom_rules'] = $custom['summary'];
        $results['summary']['custom_rules_total'] = $custom['summary']['total'];
        $this->log("Custom rules: {$custom['summary']['total']} issues.", 'custom_rules');

        return $results;
    }

    protected function runEnvAudit(array $results): array
    {
        if (! file_exists($this->projectPath.'/.env')) {
            return $results;
        }

        $this->log('.env audit: checking environment configuration...', 'env_audit');
        $env = EnvAuditor::audit($this->projectPath);
        $results['env_audit'] = $env;
        $results['summary']['env_audit'] = $env['summary'];
        $results['summary']['env_audit_total'] = $env['summary']['total'];
        $this->log(".env audit: {$env['summary']['total']} issues.", 'env_audit');

        return $results;
    }

    protected function runLifecycle(array $results): array
    {
        $this->log('Lifecycle: checking runtime and framework versions against endoflife.date...', 'endoflife');
        $lifecycle = (new EndOfLifeScanner)->scan($this->projectPath);
        $results['endoflife'] = $lifecycle;
        $results['summary']['endoflife'] = $lifecycle['summary'];
        $results['summary']['endoflife_total'] = $lifecycle['summary']['total'];
        $this->log("Lifecycle: {$lifecycle['summary']['components']} components checked, {$lifecycle['summary']['total']} support issues.", 'endoflife');

        return $results;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Mask secret values in code snippets so hosted results never hold a
     * usable credential (Gitleaks redacts its own output via --redact).
     */
    public static function redactSecrets(array $results): array
    {
        $isSecret = static fn (string $id, string $category = ''): bool => $category === 'secret'
            || (bool) preg_match('/secret|credential|password|api[-_]?key|token/i', $id);

        foreach (['laravel_rules', 'custom_rules'] as $pack) {
            foreach ($results[$pack]['findings'] ?? [] as $i => $finding) {
                if ($isSecret($finding['rule_id'] ?? '', $finding['category'] ?? '')) {
                    $results[$pack]['findings'][$i]['code_snippet'] = static::maskSecretValues((string) ($finding['code_snippet'] ?? ''));
                }
            }
        }

        foreach (['semgrep', 'code_quality'] as $engine) {
            foreach ($results[$engine]['results'] ?? [] as $i => $finding) {
                if ($isSecret($finding['check_id'] ?? '')) {
                    $results[$engine]['results'][$i]['extra']['lines'] = static::maskSecretValues((string) ($finding['extra']['lines'] ?? ''));
                }
            }
        }

        return $results;
    }

    public static function maskSecretValues(string $code): string
    {
        // Quoted literals keep their first 4 characters: "sk_live_abc..." -> "sk_l********"
        $code = preg_replace('/(["\'`])([^"\'`\s]{0,4})[^"\'`]{4,}\1/', '$1$2********$1', $code) ?? $code;

        // Unquoted assignments such as KEY=value in config files.
        return preg_replace('/^(\s*(?:\d+:\s*)?[\w.-]*(?:KEY|SECRET|PASSWORD|TOKEN|PASS)[\w.-]*\s*[=:]\s*)([^\s"\'`]{4})\S{4,}/im', '$1$2********', $code) ?? $code;
    }

    /**
     * @param  list<string>  $command
     */
    protected function runTool(array $command, string $scanner): \Illuminate\Contracts\Process\ProcessResult
    {
        try {
            return Process::path($this->projectPath)
                ->env(static::processEnvironment())
                ->timeout((int) config('checkmycode.scanner_timeout_seconds', 600))
                ->run($command);
        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            $this->log('Timed out after '.config('checkmycode.scanner_timeout_seconds').'s.', $scanner, 'error');

            return $e->result;
        }
    }

    public static function processEnvironment(): array
    {
        $path = (string) (getenv('PATH') ?: '/usr/bin:/bin');
        foreach (['/opt/homebrew/bin', '/usr/local/bin'] as $dir) {
            if (! str_contains($path, $dir)) {
                $path .= ':'.$dir;
            }
        }

        return [
            'PATH' => $path,
            'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
            'SEMGREP_SEND_METRICS' => 'off',
            'SEMGREP_ENABLE_VERSION_CHECK' => '0',
        ];
    }

    protected function readJson(string $path): ?array
    {
        if (! is_file($path) || filesize($path) === 0) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function firstLine(string $output): string
    {
        $line = trim(strtok(trim($output), "\n") ?: '');

        return $line !== '' ? mb_strimwidth($line, 0, 200, '…') : 'no error output';
    }

    protected function log(string $line, string $scanner = 'system', string $level = 'info'): void
    {
        ($this->logger)($line, $scanner, $level);
    }
}
