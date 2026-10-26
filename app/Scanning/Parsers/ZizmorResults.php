<?php

namespace App\Scanning\Parsers;

/**
 * Turns zizmor's GitHub Actions audit output into rule-style findings.
 */
class ZizmorResults
{
    /**
     * @return array{findings: array<int, array<string, mixed>>, summary: array{total:int, CRITICAL:int, HIGH:int, MEDIUM:int, LOW:int, INFO:int}}
     */
    public static function parse(array $zizmor): array
    {
        $findings = [];

        foreach ($zizmor as $finding) {
            if (($finding['ignored'] ?? false) === true) {
                continue;
            }

            $primary = collect($finding['locations'] ?? [])
                ->first(fn (array $location): bool => ($location['symbolic']['kind'] ?? '') === 'Primary')
                ?? ($finding['locations'][0] ?? []);

            $path = (string) ($primary['symbolic']['key']['Local']['given_path']
                ?? $primary['symbolic']['key']['Local']['verbatim_path']
                ?? '');
            $line = isset($primary['concrete']['location']['start_point']['row'])
                ? (int) $primary['concrete']['location']['start_point']['row'] + 1
                : null;
            $ident = (string) ($finding['ident'] ?? 'workflow');

            $findings[] = [
                'rule_id' => 'zizmor.'.$ident,
                'title' => ucfirst((string) ($finding['desc'] ?? $ident)),
                'severity' => static::severity((string) ($finding['determinations']['severity'] ?? '')),
                'confidence' => strtolower((string) ($finding['determinations']['confidence'] ?? '')),
                'category' => 'ci-workflow',
                'description' => (string) ($primary['symbolic']['annotation'] ?? ''),
                'file_path' => preg_replace('#^\./#', '', $path),
                'line_start' => $line,
                'code_snippet' => isset($primary['concrete']['feature']) ? mb_strimwidth((string) $primary['concrete']['feature'], 0, 400, '…') : null,
                'fix_guidance' => null,
                'link' => $finding['url'] ?? null,
                'fingerprint' => sha1($ident.'|'.$path.'|'.$line),
            ];
        }

        $summary = ['total' => count($findings), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0, 'INFO' => 0];
        foreach ($findings as $finding) {
            $summary[$finding['severity']]++;
        }

        return ['findings' => $findings, 'summary' => $summary];
    }

    protected static function severity(string $severity): string
    {
        return match (strtolower($severity)) {
            'high' => 'HIGH',
            'medium' => 'MEDIUM',
            'low' => 'LOW',
            default => 'INFO',
        };
    }
}
