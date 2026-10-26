<?php

namespace App\Services;

class SarifExporter
{
    public static function fromResults(array $results): array
    {
        $rules = [];
        $sarifResults = [];

        foreach (static::trivyFindings($results) as [$rule, $result]) {
            static::addRule($rules, $rule);
            $sarifResults[] = $result;
        }

        foreach (static::semgrepFindings($results, 'semgrep') as [$rule, $result]) {
            static::addRule($rules, $rule);
            $sarifResults[] = $result;
        }

        foreach (static::semgrepFindings($results, 'code_quality') as [$rule, $result]) {
            static::addRule($rules, $rule);
            $sarifResults[] = $result;
        }

        foreach (static::gitleaksFindings($results) as [$rule, $result]) {
            static::addRule($rules, $rule);
            $sarifResults[] = $result;
        }

        foreach ([
            'laravel_rules' => ['framework', 'Framework Rules'],
            'custom_rules' => ['custom', 'Custom Rules'],
            'trivy_config' => ['config', 'Trivy Config'],
            'workflows' => ['workflow', 'GitHub Actions (zizmor)'],
            'endoflife' => ['lifecycle', 'Lifecycle Support'],
        ] as $key => [$prefix, $label]) {
            foreach (static::ruleFindings($results, $key, $prefix, $label) as [$rule, $result]) {
                static::addRule($rules, $rule);
                $sarifResults[] = $result;
            }
        }

        foreach (static::envAuditFindings($results) as [$rule, $result]) {
            static::addRule($rules, $rule);
            $sarifResults[] = $result;
        }

        return [
            'version' => '2.1.0',
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'runs' => [[
                'tool' => [
                    'driver' => [
                        'name' => 'CheckMyCode',
                        'informationUri' => 'https://checkmycode.dev/',
                        'rules' => array_values($rules),
                    ],
                ],
                'results' => $sarifResults,
                'properties' => [
                    'projectType' => $results['project_type'] ?? 'unknown',
                    'generatedBy' => 'checkmycode',
                ],
            ]],
        ];
    }

    private static function addRule(array &$rules, array $rule): void
    {
        $rules[$rule['id']] = $rule;
    }

    private static function trivyFindings(array $results): array
    {
        $findings = [];

        foreach ($results['trivy']['Results'] ?? [] as $target) {
            foreach ($target['Vulnerabilities'] ?? [] as $vulnerability) {
                $id = 'trivy.'.static::slug($vulnerability['VulnerabilityID'] ?? $vulnerability['PkgName'] ?? 'vulnerability');
                $package = $vulnerability['PkgName'] ?? 'unknown package';
                $message = trim(($vulnerability['Title'] ?? $vulnerability['Description'] ?? 'Vulnerable dependency').' Package: '.$package);

                $findings[] = [
                    static::rule($id, $vulnerability['VulnerabilityID'] ?? 'Dependency vulnerability', $message, 'Trivy'),
                    static::result(
                        $id,
                        static::level($vulnerability['Severity'] ?? 'LOW'),
                        $message,
                        $target['Target'] ?? 'dependency-manifest',
                        1,
                        [
                            'scanner' => 'trivy',
                            'package' => $package,
                            'installedVersion' => $vulnerability['InstalledVersion'] ?? null,
                            'fixedVersion' => $vulnerability['FixedVersion'] ?? null,
                        ],
                    ),
                ];
            }
        }

        return $findings;
    }

    private static function semgrepFindings(array $results, string $key): array
    {
        $findings = [];
        $scanner = $key === 'code_quality' ? 'Code Quality' : 'Semgrep';

        foreach ($results[$key]['results'] ?? [] as $finding) {
            $id = $key.'.'.static::slug($finding['check_id'] ?? 'finding');
            $message = $finding['extra']['message'] ?? $finding['check_id'] ?? 'Static analysis finding';

            $findings[] = [
                static::rule($id, $finding['check_id'] ?? $scanner, $message, $scanner),
                static::result(
                    $id,
                    static::level($finding['extra']['severity'] ?? 'INFO'),
                    $message,
                    $finding['path'] ?? 'unknown',
                    (int) ($finding['start']['line'] ?? 1),
                    ['scanner' => $key],
                ),
            ];
        }

        return $findings;
    }

    private static function gitleaksFindings(array $results): array
    {
        $findings = [];

        foreach ($results['gitleaks'] ?? [] as $secret) {
            $id = 'gitleaks.'.static::slug($secret['RuleID'] ?? 'secret');
            $message = $secret['Description'] ?? 'Leaked secret detected';

            $findings[] = [
                static::rule($id, $secret['RuleID'] ?? 'Secret', $message, 'Gitleaks'),
                static::result(
                    $id,
                    'error',
                    $message,
                    $secret['File'] ?? 'unknown',
                    (int) ($secret['StartLine'] ?? 1),
                    ['scanner' => 'gitleaks', 'commit' => $secret['Commit'] ?? null],
                ),
            ];
        }

        return $findings;
    }

    /**
     * Rule-style findings share one shape: rule_id, title, severity, description, file_path, line_start.
     */
    private static function ruleFindings(array $results, string $key, string $prefix, string $label): array
    {
        $findings = [];

        foreach ($results[$key]['findings'] ?? [] as $finding) {
            $id = $prefix.'.'.static::slug($finding['rule_id'] ?? 'rule');
            $message = $finding['description'] ?? $finding['title'] ?? $label.' finding';

            $findings[] = [
                static::rule($id, $finding['title'] ?? $finding['rule_id'] ?? $label, $message, $label),
                static::result(
                    $id,
                    static::level($finding['severity'] ?? 'MEDIUM'),
                    $message,
                    $finding['file_path'] ?? 'unknown',
                    (int) ($finding['line_start'] ?? 1),
                    ['scanner' => $key, 'category' => $finding['category'] ?? null],
                ),
            ];
        }

        return $findings;
    }

    private static function envAuditFindings(array $results): array
    {
        $findings = [];

        foreach ($results['env_audit']['findings'] ?? [] as $finding) {
            $id = 'env.'.static::slug($finding['key'] ?? 'environment');
            $message = $finding['description'] ?? $finding['title'] ?? 'Environment configuration issue';

            $findings[] = [
                static::rule($id, $finding['title'] ?? $finding['key'] ?? 'Environment audit', $message, '.env Audit'),
                static::result(
                    $id,
                    static::level($finding['severity'] ?? 'INFO'),
                    $message,
                    '.env',
                    (int) ($finding['line'] ?? 1),
                    ['scanner' => 'env_audit', 'key' => $finding['key'] ?? null],
                ),
            ];
        }

        return $findings;
    }

    private static function rule(string $id, string $name, string $description, string $scanner): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'shortDescription' => ['text' => $name],
            'fullDescription' => ['text' => $description],
            'properties' => ['scanner' => $scanner],
        ];
    }

    private static function result(string $ruleId, string $level, string $message, string $path, int $line, array $properties = []): array
    {
        return [
            'ruleId' => $ruleId,
            'level' => $level,
            'message' => ['text' => $message],
            'locations' => [[
                'physicalLocation' => [
                    'artifactLocation' => ['uri' => static::normalizePath($path)],
                    'region' => ['startLine' => max(1, $line)],
                ],
            ]],
            'properties' => array_filter($properties, static fn ($value) => $value !== null),
        ];
    }

    private static function level(string $severity): string
    {
        return match (strtoupper($severity)) {
            'CRITICAL', 'HIGH', 'ERROR' => 'error',
            'MEDIUM', 'WARNING' => 'warning',
            default => 'note',
        };
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', ltrim($path, '/'));
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'finding');

        return trim($slug, '-') ?: 'finding';
    }
}
