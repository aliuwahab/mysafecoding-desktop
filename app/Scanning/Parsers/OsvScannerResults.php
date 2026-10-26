<?php

namespace App\Scanning\Parsers;

/**
 * Merges OSV-Scanner findings into the Trivy-shaped dependency results, so
 * both vulnerability databases feed one Dependencies list without duplicates.
 */
class OsvScannerResults
{
    /** OSV ecosystem → the Trivy target type the UI already understands. */
    protected const ECOSYSTEM_TYPES = [
        'npm' => 'npm', 'PyPI' => 'pip', 'Packagist' => 'composer', 'Go' => 'gomod',
        'crates.io' => 'cargo', 'RubyGems' => 'bundler', 'Maven' => 'pom', 'NuGet' => 'nuget',
        'Hex' => 'hex', 'Pub' => 'pub', 'SwiftURL' => 'swift', 'ConanCenter' => 'conan', 'CRAN' => 'cran',
    ];

    /**
     * @param  array|null  $trivy  Trivy JSON (or null when Trivy did not run)
     * @param  string  $projectPath  used to make OSV's absolute paths relative
     * @return array{trivy: array, added: int}
     */
    public static function merge(?array $trivy, array $osv, string $projectPath): array
    {
        $trivy ??= ['Results' => []];
        $trivy['Results'] ??= [];

        $known = [];
        foreach ($trivy['Results'] as $target) {
            foreach ($target['Vulnerabilities'] ?? [] as $vuln) {
                $known[static::key($vuln['PkgName'] ?? '', $vuln['VulnerabilityID'] ?? '')] = true;
                foreach ($vuln['VendorIDs'] ?? [] as $alias) {
                    $known[static::key($vuln['PkgName'] ?? '', $alias)] = true;
                }
            }
        }

        $added = 0;
        foreach ($osv['results'] ?? [] as $source) {
            $targetName = ltrim(str_replace(rtrim($projectPath, '/'), '', (string) ($source['source']['path'] ?? '')), '/');
            $vulnerabilities = [];
            $ecosystem = null;

            foreach ($source['packages'] ?? [] as $package) {
                $name = (string) ($package['package']['name'] ?? '');
                // Build and test tooling: reported, but not graded (Trivy skips these entirely).
                $isDev = in_array('dev', $package['dependency_groups'] ?? [], true);
                $scores = static::groupScores($package['groups'] ?? []);

                foreach ($package['vulnerabilities'] ?? [] as $vuln) {
                    $ids = array_values(array_unique([$vuln['id'] ?? '', ...($vuln['aliases'] ?? [])]));
                    $alreadyReported = false;
                    foreach ($ids as $id) {
                        if (isset($known[static::key($name, $id)])) {
                            $alreadyReported = true;
                            break;
                        }
                    }
                    if ($alreadyReported) {
                        continue;
                    }
                    foreach ($ids as $id) {
                        $known[static::key($name, $id)] = true;
                    }

                    $ecosystem ??= (string) ($package['package']['ecosystem'] ?? '');
                    $cve = collect($ids)->first(fn (string $id): bool => str_starts_with($id, 'CVE-'));
                    $vulnerabilities[] = [
                        'VulnerabilityID' => $cve ?? $vuln['id'],
                        'VendorIDs' => $ids,
                        'PkgName' => $name,
                        'InstalledVersion' => (string) ($package['package']['version'] ?? ''),
                        'FixedVersion' => static::fixedVersions($vuln, $name),
                        'Severity' => static::severity($vuln, $scores[$vuln['id']] ?? null),
                        'Title' => $vuln['summary'] ?? mb_strimwidth((string) ($vuln['details'] ?? ''), 0, 160, '…'),
                        'PrimaryURL' => 'https://osv.dev/vulnerability/'.$vuln['id'],
                        'DataSource' => ['ID' => 'osv', 'Name' => 'OSV-Scanner'],
                        'DevDependency' => $isDev,
                    ];
                }
            }

            if ($vulnerabilities === []) {
                continue;
            }

            $added += count($vulnerabilities);
            $trivy['Results'][] = [
                'Target' => $targetName,
                'Class' => 'lang-pkgs',
                'Type' => static::ECOSYSTEM_TYPES[$ecosystem] ?? strtolower((string) $ecosystem),
                'Vulnerabilities' => $vulnerabilities,
            ];
        }

        return ['trivy' => $trivy, 'added' => $added];
    }

    protected static function key(string $package, string $id): string
    {
        return strtolower($package).'|'.strtoupper($id);
    }

    /**
     * @return array<string, float> vulnerability id => highest CVSS score of its group
     */
    protected static function groupScores(array $groups): array
    {
        $scores = [];
        foreach ($groups as $group) {
            if (! is_numeric($group['max_severity'] ?? null)) {
                continue;
            }
            foreach ($group['ids'] ?? [] as $id) {
                $scores[$id] = (float) $group['max_severity'];
            }
        }

        return $scores;
    }

    protected static function severity(array $vuln, ?float $score): string
    {
        $label = strtoupper((string) ($vuln['database_specific']['severity'] ?? ''));
        if (in_array($label, ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'], true)) {
            return $label;
        }
        if ($label === 'MODERATE') {
            return 'MEDIUM';
        }

        return match (true) {
            $score === null => 'MEDIUM',
            $score >= 9.0 => 'CRITICAL',
            $score >= 7.0 => 'HIGH',
            $score >= 4.0 => 'MEDIUM',
            default => 'LOW',
        };
    }

    protected static function fixedVersions(array $vuln, string $package): string
    {
        $fixed = [];
        foreach ($vuln['affected'] ?? [] as $affected) {
            if (($affected['package']['name'] ?? $package) !== $package) {
                continue;
            }
            foreach ($affected['ranges'] ?? [] as $range) {
                foreach ($range['events'] ?? [] as $event) {
                    if (isset($event['fixed'])) {
                        $fixed[] = $event['fixed'];
                    }
                }
            }
        }

        return implode(', ', array_unique($fixed));
    }
}
