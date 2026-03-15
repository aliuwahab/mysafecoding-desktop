<?php

namespace App\Services\Scanners\Rules;

class DebugModeRule extends BaseRule
{
    public function id(): string { return 'laravel.debug-mode'; }
    public function title(): string { return 'Debug Mode Enabled'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'Config'; }

    public function check(string $projectPath): array
    {
        $findings = [];

        $envFile = $projectPath.'/.env';
        $lines = $this->readLines($envFile);
        if (! $lines) {
            return [];
        }

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*APP_DEBUG\s*=\s*true\s*$/i', $line)) {
                $findings[] = $this->makeFinding(
                    $envFile, $projectPath, $i + 1,
                    'APP_DEBUG is set to true. In production, this exposes detailed error pages with stack traces, environment variables, and database queries to attackers.',
                    'Set APP_DEBUG=false in your production .env file. Use proper logging instead of debug mode.',
                );
            }
        }

        return $findings;
    }
}
