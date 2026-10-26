<?php

namespace App\Scanning;

class ScanGrade
{
    /**
     * @return array{critical:int, high:int, medium:int, secrets:int}
     */
    public static function severityCounts(array $summary): array
    {
        $sum = static fn (array $paths): int => array_sum(array_map(
            static fn (string $path): int => (int) data_get($summary, $path, 0),
            $paths,
        ));

        $packs = ['laravel_rules', 'custom_rules', 'env_audit', 'endoflife', 'trivy_config'];

        return [
            'critical' => $sum(['trivy.CRITICAL', ...array_map(fn ($p) => "{$p}.CRITICAL", $packs)]),
            'high' => $sum(['trivy.HIGH', 'semgrep.ERROR', ...array_map(fn ($p) => "{$p}.HIGH", $packs)]),
            'medium' => $sum(['trivy.MEDIUM', 'semgrep.WARNING', ...array_map(fn ($p) => "{$p}.MEDIUM", $packs)]),
            'secrets' => (int) ($summary['gitleaks_total'] ?? 0),
        ];
    }

    public static function fromSummary(array $summary): string
    {
        $counts = static::severityCounts($summary);

        return match (true) {
            $counts['secrets'] > 0 || $counts['critical'] > 0 => 'F',
            $counts['high'] > 3 => 'D',
            $counts['high'] > 0 => 'C',
            $counts['medium'] > 0 => 'B',
            default => 'A',
        };
    }
}
