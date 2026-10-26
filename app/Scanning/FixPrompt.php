<?php

namespace App\Scanning;

/**
 * Builds a copy-paste prompt for a coding agent (Claude Code, Cursor,
 * Copilot, ...) that fixes the findings of one results tab without changing
 * existing functionality. Secret values are never included.
 */
class FixPrompt
{
    public const TABS = ['trivy', 'semgrep', 'gitleaks', 'rules', 'config', 'workflows', 'lifecycle', 'env_audit', 'code_quality', 'licenses'];

    protected const MAX_ITEMS = 150;

    /**
     * @param  array<string, string>  $ignored  "scanner:fingerprint" => reason
     */
    public static function forTab(string $tab, array $results, array $ignored = [], ?string $projectName = null): ?string
    {
        $items = static::items($tab, $results, $ignored);

        if ($items === []) {
            return null;
        }

        [$category, $guidance] = static::guidance($tab);
        $shown = array_slice($items, 0, self::MAX_ITEMS);
        $more = count($items) - count($shown);
        $project = trim(($projectName ? "\"{$projectName}\"" : 'this project').' ('.ProjectDetector::label($results['project_type'] ?? 'unknown').')');
        $reviewOnly = $tab === 'licenses';

        $lines = [
            "A CheckMyCode security scan of {$project} found the following {$category}. "
                .($reviewOnly ? 'Review them and report back. Do not change any code for this task.' : 'Fix them in this repository.'),
            '',
            '## Ground rules',
            '- Preserve existing functionality. Do not change public behaviour, APIs, routes, database schema, UI, configuration defaults or output formats, except where that is the only way to close the security issue.',
            '- Make the smallest safe change for each issue and follow the code style and patterns already used in the project. No unrelated refactors, renames, reformatting or dependency changes.',
            '- If a fix would change behaviour, needs a product decision, or requires a major-version upgrade, do not apply it. List it under "Needs review" with the reason and the options.',
            '- If you are confident an item is a false positive, leave the code unchanged and explain why.',
            '- Never print, log or commit secret values.',
            '- Before finishing, run the existing tests, linters and build. Everything that passed before must still pass. Add or adjust tests only where needed to cover a fix.',
            '',
            '## How to fix '.$category,
            ...array_map(static fn (string $line): string => '- '.$line, $guidance),
            '',
            '## Issues ('.count($items).')',
            ...$shown,
        ];

        if ($more > 0) {
            $lines[] = "- … and {$more} more. Fix the ones above first, then re-run the scan.";
        }

        array_push($lines,
            '',
            '## When you are done',
            'Reply with a table of every issue above: what you changed (file and a one-line summary), or why you did not change it (false positive / needs review). Then list the commands you ran (tests, build, linters) and their results.',
        );

        return implode("\n", $lines);
    }

    /**
     * @return list<string> one markdown bullet per open finding
     */
    protected static function items(string $tab, array $results, array $ignored): array
    {
        $isIgnored = static fn (string $scanner, string $fingerprint): bool => isset($ignored["{$scanner}:{$fingerprint}"]);
        $location = static fn (?string $file, mixed $line): string => '`'.($file ?: 'unknown').(filled($line) ? ':'.$line : '').'`';
        $clip = static fn (?string $text, int $length = 220): string => trim(preg_replace('/\s+/', ' ', mb_strimwidth((string) $text, 0, $length, '…')) ?? '');
        // Code excerpts are masked again here, whatever the scan did, and never taken from .env files.
        $code = static fn (?string $file, ?string $snippet): string => str_starts_with(basename((string) $file), '.env')
            ? ''
            : $clip(ScanEngine::maskSecretValues((string) $snippet), 160);
        $items = [];

        switch ($tab) {
            case 'trivy':
                foreach ($results['trivy']['Results'] ?? [] as $target) {
                    foreach ($target['Vulnerabilities'] ?? [] as $vuln) {
                        if ($isIgnored('trivy', ($vuln['VulnerabilityID'] ?? '').':'.($vuln['PkgName'] ?? ''))) {
                            continue;
                        }
                        $items[] = sprintf(
                            '- [%s] `%s` %s in `%s`%s: %s (%s). Fixed in: %s.',
                            $vuln['Severity'] ?? 'UNKNOWN',
                            $vuln['PkgName'] ?? '?',
                            $vuln['InstalledVersion'] ?? '?',
                            $target['Target'] ?? 'lockfile',
                            ! empty($vuln['DevDependency']) ? ' (dev dependency)' : '',
                            $vuln['VulnerabilityID'] ?? '',
                            $clip($vuln['Title'] ?? '', 140),
                            ($vuln['FixedVersion'] ?? '') !== '' ? $vuln['FixedVersion'] : 'no fixed version published yet',
                        );
                    }
                }
                break;

            case 'semgrep':
            case 'code_quality':
                $scanner = $tab === 'semgrep' ? 'semgrep' : 'code_quality';
                foreach ($results[$tab]['results'] ?? [] as $finding) {
                    $fingerprint = ($finding['check_id'] ?? '').':'.($finding['path'] ?? '').':'.($finding['start']['line'] ?? '');
                    if ($isIgnored($scanner, $fingerprint)) {
                        continue;
                    }
                    $snippet = $code($finding['path'] ?? null, $finding['extra']['lines'] ?? '');
                    $items[] = sprintf(
                        '- [%s] %s `%s`: %s%s',
                        $finding['extra']['severity'] ?? 'INFO',
                        $location($finding['path'] ?? null, $finding['start']['line'] ?? null),
                        $finding['check_id'] ?? 'rule',
                        $clip($finding['extra']['message'] ?? ''),
                        $snippet !== '' && ! str_contains(strtolower($finding['check_id'] ?? ''), 'secret') ? " Code: `{$snippet}`" : '',
                    );
                }
                break;

            case 'gitleaks':
                foreach ($results['gitleaks'] ?? [] as $secret) {
                    $fingerprint = ($secret['RuleID'] ?? '').':'.($secret['File'] ?? '').':'.($secret['StartLine'] ?? '');
                    if ($isIgnored('gitleaks', $fingerprint)) {
                        continue;
                    }
                    // Deliberately no Match/Secret: the value must never end up in an AI prompt.
                    $items[] = sprintf(
                        '- %s %s%s',
                        $location($secret['File'] ?? null, $secret['StartLine'] ?? null),
                        $secret['Description'] ?? $secret['RuleID'] ?? 'Secret',
                        ! empty($secret['Commit']) ? ' (also present in git history, commit '.substr($secret['Commit'], 0, 8).')' : '',
                    );
                }
                break;

            case 'rules':
            case 'config':
            case 'workflows':
            case 'lifecycle':
                $packs = match ($tab) {
                    'rules' => ['laravel_rules', 'custom_rules'],
                    'config' => ['trivy_config'],
                    'workflows' => ['workflows'],
                    'lifecycle' => ['endoflife'],
                };
                foreach ($packs as $pack) {
                    foreach ($results[$pack]['findings'] ?? [] as $finding) {
                        $fingerprint = $finding['fingerprint'] ?? (($finding['rule_id'] ?? '').':'.($finding['file_path'] ?? '').':'.($finding['line_start'] ?? ''));
                        if ($isIgnored($pack, $fingerprint)) {
                            continue;
                        }
                        $isSecret = ($finding['category'] ?? '') === 'secret' || preg_match('/secret|credential|password|token/i', $finding['rule_id'] ?? '');
                        $item = sprintf(
                            '- [%s] %s `%s` %s: %s',
                            $finding['severity'] ?? 'MEDIUM',
                            $location($finding['file_path'] ?? null, $finding['line_start'] ?? null),
                            $finding['rule_id'] ?? 'rule',
                            $finding['title'] ?? '',
                            $clip($finding['description'] ?? ''),
                        );
                        if (! empty($finding['fix_guidance'])) {
                            $item .= ' Suggested fix: '.$clip($finding['fix_guidance']);
                        }
                        if (! empty($finding['link'])) {
                            $item .= ' Reference: '.$finding['link'];
                        }
                        $snippet = $code($finding['file_path'] ?? null, $finding['code_snippet'] ?? '');
                        if (! $isSecret && $tab !== 'lifecycle' && $snippet !== '') {
                            $item .= ' Code: `'.$snippet.'`';
                        }
                        $items[] = $item;
                    }
                }
                break;

            case 'env_audit':
                foreach ($results['env_audit']['findings'] ?? [] as $row) {
                    if (($row['ok'] ?? false) || $isIgnored('env_audit', ($row['key'] ?? '').':'.($row['line'] ?? ''))) {
                        continue;
                    }
                    $items[] = sprintf(
                        '- [%s] `%s`: %s%s',
                        $row['severity'] ?? 'INFO',
                        $row['key'] ?? '?',
                        $clip($row['description'] ?? $row['title'] ?? ''),
                        ! empty($row['recommended']) ? ' Recommended: '.$clip($row['recommended'], 120) : '',
                    );
                }
                break;

            case 'licenses':
                foreach ($results['licenses']['findings'] ?? [] as $license) {
                    $items[] = sprintf('- `%s` is licensed %s (%s), declared in `%s`.', $license['package'] ?? '?', $license['license'] ?? '?', $license['category'] ?? 'restricted', $license['file_path'] ?? 'lockfile');
                }
                break;
        }

        return $items;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    protected static function guidance(string $tab): array
    {
        return match ($tab) {
            'trivy' => ['vulnerable dependencies', [
                'Upgrade each package to the lowest version that fixes all of its listed vulnerabilities, preferring a patch or minor upgrade within the same major version.',
                'Use the project\'s package manager (e.g. composer update <pkg> --with-dependencies, npm install <pkg>@<version>, pip, bundle update --conservative) so lockfiles stay consistent. Do not edit lockfiles by hand.',
                'If a package is only vulnerable through another dependency, upgrade the direct dependency that pulls it in, or use the package manager\'s override/resolution mechanism.',
                'If only a new major version fixes it, or no fix exists, do not upgrade: put it under "Needs review" with the breaking changes to expect.',
                'Dev dependencies matter less for production, but fix them the same way when the upgrade is safe.',
            ]],
            'semgrep' => ['code security issues', [
                'Fix the root cause, not the symptom: parameterised queries instead of string-built SQL, escaping/encoding at output, allow-lists for redirects and file paths, safe parsers instead of eval/unserialize/pickle, strict TLS verification.',
                'Keep function signatures and return values the same so callers are unaffected.',
            ]],
            'code_quality' => ['code quality suggestions', [
                'These are best-practice improvements, not vulnerabilities. Apply only those that are low-risk and keep behaviour identical; skip the rest with a short reason.',
            ]],
            'gitleaks' => ['hardcoded secrets', [
                'Remove each secret from the code and read it from configuration or an environment variable instead, following how the project already loads config.',
                'Add the variable name with an empty or placeholder value to the example env file (e.g. .env.example) and make sure the real env file is gitignored. Do not put the real value anywhere.',
                'The value itself is intentionally not included here. Tell me which credentials must be rotated: removing them from code does not make a leaked key safe again.',
                'If a secret is also in git history, say so in your summary but do not rewrite git history yourself.',
            ]],
            'rules' => ['framework and security rule findings', [
                'These come from pattern-based rules that read one file at a time. They cannot see protections that live elsewhere (Form Requests, route middleware, policies, model attributes such as #[Fillable], or upstream HTML sanitizers), so verify each one in context before changing anything.',
                'Apply the suggested fix where one is given, adapted to the surrounding code.',
                'For mass-assignment, authorisation, CSRF and rate-limiting findings, add the missing protection without changing what legitimate users can do.',
            ]],
            'config' => ['configuration and infrastructure-as-code misconfigurations', [
                'Fix Dockerfiles, Kubernetes manifests, Terraform and other config as suggested (e.g. run as a non-root user, pin base images, restrict permissions/network exposure).',
                'Do not change ports, environment variable names, volumes or resource names that the application or deployment depends on.',
            ]],
            'workflows' => ['GitHub Actions workflow security issues', [
                'Pin third-party actions to a full commit SHA, keeping the version as a trailing comment (e.g. uses: actions/checkout@<sha> # v4.2.2).',
                'Add an explicit minimal permissions: block at workflow or job level.',
                'Never interpolate ${{ github.event.* }} or other attacker-controllable values directly inside run: scripts; pass them through env: variables and quote them.',
                'Avoid checking out or running pull-request code in pull_request_target workflows; set persist-credentials: false on actions/checkout unless the job pushes.',
                'Keep triggers, job names and outputs the same so required checks and branch protection keep working. Each issue has a reference link with details.',
            ]],
            'lifecycle' => ['end-of-life or soon-unsupported runtimes and frameworks', [
                'Update version declarations (.nvmrc, .python-version, composer.json "php", go.mod, Dockerfile base images, CI matrices) to a supported release line.',
                'A minor or patch upgrade within the same release line can be applied. A major framework or runtime upgrade can break behaviour: list the required steps and known breaking changes under "Needs review" instead of doing it.',
            ]],
            'env_audit' => ['environment configuration issues', [
                'These come from the local .env file, which must not be committed. Do not edit or commit .env.',
                'Instead, update the example env file and any deployment or documentation config so production uses the recommended values, and make sure the code reads them from configuration.',
            ]],
            'licenses' => ['dependencies with copyleft or restricted licenses', [
                'For each package, explain what the license requires in this project\'s context (e.g. distributed app vs. hosted service).',
                'Suggest permissively licensed alternatives where they exist, with the effort to switch. Do not replace any dependency in this task.',
            ]],
            default => ['issues', []],
        };
    }
}
