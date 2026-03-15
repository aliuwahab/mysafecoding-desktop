<?php

namespace App\Services\Scanners\Rules;

class OutdatedVersionRule extends BaseRule
{
    public function id(): string { return 'laravel.version.outdated'; }
    public function title(): string { return 'Outdated Laravel Version'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'Dependency'; }

    // Minimum security-supported versions as of March 2026
    private const MIN_SUPPORTED = [
        '12' => '12.0',
        '11' => '11.0',
    ];

    public function check(string $projectPath): array
    {
        $findings = [];

        $composerFile = $projectPath.'/composer.json';
        $content = $this->readFile($composerFile);
        if (! $content) return [];

        $composer = json_decode($content, true);
        if (! $composer) return [];

        $require = $composer['require'] ?? [];
        $laravelVersion = $require['laravel/framework'] ?? null;
        if (! $laravelVersion) return [];

        // Extract the major version from the constraint
        if (preg_match('/(\d+)/', $laravelVersion, $match)) {
            $major = $match[1];

            if (! isset(self::MIN_SUPPORTED[$major])) {
                $lines = explode("\n", $content);
                foreach ($lines as $i => $line) {
                    if (str_contains($line, 'laravel/framework')) {
                        $findings[] = $this->makeFinding(
                            $composerFile, $projectPath, $i + 1,
                            "Laravel {$laravelVersion} is no longer receiving security updates. Running an unsupported version means known vulnerabilities will never be patched.",
                            'Upgrade to a security-supported Laravel version (11.x or 12.x). See https://laravel.com/docs/releases for the support schedule.',
                        );
                        break;
                    }
                }
            }
        }

        return $findings;
    }
}
