<?php

namespace App\Console\Commands;

use App\Scanning\ProjectDetector;
use App\Scanning\ScanEngine;
use App\Scanning\ScanGrade;
use App\Services\SarifExporter;
use Illuminate\Console\Command;

class ScanCommand extends Command
{
    /** Prefix for machine-readable log lines consumed by the desktop app. */
    public const MACHINE_PREFIX = '@@checkmycode ';

    protected $signature = 'checkmycode:scan
        {path=. : Project folder or single file to scan}
        {--exclude=* : Folder or file to exclude (repeatable)}
        {--no-defaults : Do not apply the default exclusions (vendor, node_modules, ...)}
        {--output-dir= : Directory for raw scanner artifacts and results.json (default: a temp dir)}
        {--format=text : Report format printed at the end: text, json or sarif}
        {--output= : Write the json/sarif report to this file instead of stdout}
        {--fail-on=none : Exit with code 1 when findings reach this severity: critical, high, medium, any or none}
        {--no-history : Skip scanning git history for secrets}
        {--offline : Skip checks that call external APIs (endoflife.date)}
        {--machine : Emit machine-readable log lines (used by the desktop app)}';

    protected $description = 'Scan a project for vulnerable dependencies, insecure code, leaked secrets, misconfiguration and end-of-life software';

    public function handle(ScanEngine $engine): int
    {
        $path = (string) $this->argument('path');
        $realPath = realpath($path);

        if ($realPath === false) {
            $this->components->error("Path not found: {$path}");

            return 2;
        }

        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['text', 'json', 'sarif'], true)) {
            $this->components->error('--format must be one of: text, json, sarif');

            return 2;
        }

        $outputDir = $this->option('output-dir')
            ?: sys_get_temp_dir().'/checkmycode-'.date('Ymd_His').'-'.bin2hex(random_bytes(3));
        $outputDir = $this->absolutePath($outputDir);

        $exclusions = array_merge(
            $this->option('no-defaults') ? [] : config('checkmycode.default_exclusions'),
            (array) $this->option('exclude'),
        );

        // Never scan our own artifacts when they are written inside the project.
        if (is_dir($realPath) && str_starts_with($outputDir.'/', rtrim($realPath, '/').'/')) {
            $exclusions[] = ltrim(substr($outputDir, strlen(rtrim($realPath, '/'))), '/');
        }

        $machine = (bool) $this->option('machine');
        // Keep stdout clean for json/sarif reports piped to other tools.
        $quietLogs = ! $machine && $format !== 'text' && ! $this->option('output');

        if (! $machine && ! $quietLogs) {
            $this->newLine();
            $this->line('  <options=bold>CheckMyCode</> security scan');
            $this->line('  <fg=gray>Target:</> '.$realPath);
            $this->newLine();
        }

        $log = function (string $line, string $scanner, string $level) use ($machine, $quietLogs): void {
            if ($machine) {
                $this->output->writeln(self::MACHINE_PREFIX.json_encode(compact('line', 'scanner', 'level')), \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

                return;
            }
            if ($quietLogs) {
                return;
            }
            $icon = match ($level) {
                'error' => '<fg=red>✘</>',
                'warn' => '<fg=yellow>⚠</>',
                default => '<fg=blue>→</>',
            };
            $this->line(sprintf('  %s <fg=gray>%-13s</> %s', $icon, $scanner, $line));
        };

        try {
            $results = $engine->run($realPath, $outputDir, $exclusions, $log, [
                'git_history' => ! $this->option('no-history'),
                'lifecycle' => ! $this->option('offline') && config('checkmycode.endoflife.enabled', true),
            ]);
        } catch (\Throwable $e) {
            $log('Scan failed: '.$e->getMessage(), 'system', 'error');

            return 2;
        }

        if ($machine) {
            $this->output->writeln(self::MACHINE_PREFIX.json_encode(['results_path' => $outputDir.'/results.json']), \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
        } elseif ($format === 'text') {
            $this->renderSummary($results, $outputDir);
        } else {
            $report = $format === 'sarif' ? SarifExporter::fromResults($results) : $results;
            $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if ($this->option('output')) {
                file_put_contents($this->absolutePath((string) $this->option('output')), $json);
                $this->components->info('Report written to '.$this->option('output'));
            } else {
                $this->output->writeln($json, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
            }
        }

        return $this->thresholdReached($results) ? 1 : 0;
    }

    protected function renderSummary(array $results, string $outputDir): void
    {
        $summary = $results['summary'];
        $rows = [
            ['Dependencies (Trivy + OSV)', $summary['trivy_total'] ?? 0, $this->breakdown($summary['trivy'] ?? [])],
            ['Code (Semgrep SAST)', $summary['semgrep_total'] ?? 0, $this->breakdown($summary['semgrep'] ?? [])],
            ['Secrets (Gitleaks)', $summary['gitleaks_total'] ?? 0, ($results['meta']['git_history'] ?? false) ? 'files + git history' : 'files only'],
            ['Misconfiguration (IaC)', $summary['trivy_config_total'] ?? 0, $this->breakdown($summary['trivy_config'] ?? [])],
            ['GitHub Actions (zizmor)', $summary['workflows_total'] ?? 0, isset($results['workflows']) ? $this->breakdown($summary['workflows'] ?? []) : 'no workflows'],
            ['Custom rules', $summary['custom_rules_total'] ?? 0, $this->breakdown($summary['custom_rules'] ?? [])],
        ];
        if (isset($summary['laravel_rules_total'])) {
            $rows[] = ['Laravel rules', $summary['laravel_rules_total'], $this->breakdown($summary['laravel_rules'] ?? [])];
        }
        if (isset($summary['env_audit_total'])) {
            $rows[] = ['.env audit', $summary['env_audit_total'], $this->breakdown($summary['env_audit'] ?? [])];
        }
        if (isset($summary['endoflife_total'])) {
            $rows[] = ['Lifecycle (end-of-life)', $summary['endoflife_total'], $this->breakdown($summary['endoflife'] ?? [])];
        }
        if (($summary['dev_dependencies_total'] ?? 0) > 0) {
            $rows[] = ['Dev dependencies (not graded)', $summary['dev_dependencies_total'], 'build/test tooling'];
        }
        $rows[] = ['Licenses (not graded)', $summary['licenses_total'] ?? 0, 'flagged copyleft / restricted'];
        $rows[] = ['Code quality (not graded)', $summary['code_quality_total'] ?? 0, $this->breakdown($summary['code_quality'] ?? [])];

        $this->newLine();
        $this->table(['Engine', 'Findings', 'Breakdown'], $rows);

        $grade = $summary['grade'] ?? ScanGrade::fromSummary($summary);
        $color = match ($grade) {
            'A' => 'green', 'B' => 'blue', 'C' => 'yellow', default => 'red',
        };
        $this->line(sprintf(
            '  Project type: <options=bold>%s</>   Security grade: <fg=%s;options=bold>%s</>   Findings: <options=bold>%d</>   Took %ds',
            ProjectDetector::label($results['project_type'] ?? 'unknown'),
            $color,
            $grade,
            $summary['findings_total'] ?? 0,
            $results['meta']['duration_seconds'] ?? 0,
        ));
        $this->line('  <fg=gray>Full results:</> '.$outputDir.'/results.json'
            .($this->option('output-dir') ? '' : ' <fg=gray>(temporary; pass --output-dir to keep them)</>'));
        $this->newLine();
    }

    protected function breakdown(array $counts): string
    {
        $parts = [];
        foreach ($counts as $severity => $count) {
            if (is_int($count) && $count > 0 && $severity !== 'total' && $severity !== 'components') {
                $parts[] = "{$count} ".strtolower((string) $severity);
            }
        }

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    protected function thresholdReached(array $results): bool
    {
        $failOn = strtolower((string) $this->option('fail-on'));
        $counts = ScanGrade::severityCounts($results['summary']);

        return match ($failOn) {
            'critical' => $counts['critical'] + $counts['secrets'] > 0,
            'high' => $counts['critical'] + $counts['secrets'] + $counts['high'] > 0,
            'medium' => $counts['critical'] + $counts['secrets'] + $counts['high'] + $counts['medium'] > 0,
            'any' => ($results['summary']['findings_total'] ?? 0) > 0,
            default => false,
        };
    }

    protected function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? rtrim($path, '/') : rtrim(getcwd().'/'.$path, '/');
    }
}
