<?php

namespace App\Scanning;

/**
 * Classifies the smallest upgrade that fixes a vulnerable dependency, so
 * users and coding agents can tell safe patch bumps from breaking upgrades.
 */
class UpgradePath
{
    /**
     * @return array{type: 'patch'|'minor'|'major'|'none'|'unknown', target: ?string}
     */
    public static function classify(?string $installed, ?string $fixedVersions): array
    {
        $current = static::parse((string) $installed);
        $candidates = array_values(array_filter(array_map('trim', preg_split('/[,|]/', (string) $fixedVersions) ?: [])));

        if ($candidates === []) {
            return ['type' => 'none', 'target' => null];
        }
        if ($current === null) {
            return ['type' => 'unknown', 'target' => $candidates[0]];
        }

        // Smallest fixed version above the installed one; stable releases before pre-releases.
        $options = [];
        foreach ($candidates as $candidate) {
            $version = static::parse($candidate);
            $newer = $version !== null && ($version['numbers'] > $current['numbers']
                || ($version['numbers'] === $current['numbers'] && $current['pre'] && ! $version['pre']));
            if ($newer) {
                $options[] = ['raw' => $candidate] + $version;
            }
        }
        if ($options === []) {
            return ['type' => 'unknown', 'target' => $candidates[0]];
        }
        usort($options, fn (array $a, array $b): int => [$a['pre'], $a['numbers']] <=> [$b['pre'], $b['numbers']]);
        $target = $options[0];

        [$fromMajor, $fromMinor] = $current['numbers'];
        [$toMajor, $toMinor] = $target['numbers'];

        $type = match (true) {
            $toMajor !== $fromMajor => 'major',
            // Semver: below 1.0 a minor bump may break the API.
            $fromMajor === 0 && $toMinor !== $fromMinor => 'major',
            $toMinor !== $fromMinor => 'minor',
            default => 'patch',
        };

        return ['type' => $type, 'target' => $target['raw']];
    }

    /**
     * The higher of two version strings (null-safe), e.g. to find one upgrade
     * that fixes every advisory of a package.
     */
    public static function higher(?string $a, ?string $b): ?string
    {
        $pa = $a !== null ? static::parse($a) : null;
        $pb = $b !== null ? static::parse($b) : null;

        if ($pa === null) {
            return $pb !== null ? $b : $a;
        }
        if ($pb === null) {
            return $a;
        }

        return [$pb['numbers'], ! $pb['pre']] > [$pa['numbers'], ! $pa['pre']] ? $b : $a;
    }

    /**
     * @return array{numbers: array{int, int, int}, pre: bool}|null
     */
    protected static function parse(string $version): ?array
    {
        if (! preg_match('/(\d+)(?:\.(\d+))?(?:\.(\d+))?([-+~][0-9A-Za-z.\-]+)?/', ltrim($version, 'v=^~ '), $m)) {
            return null;
        }

        return [
            'numbers' => [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)],
            'pre' => isset($m[4]) && str_starts_with($m[4], '-'),
        ];
    }
}
