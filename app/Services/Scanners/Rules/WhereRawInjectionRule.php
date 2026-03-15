<?php

namespace App\Services\Scanners\Rules;

class WhereRawInjectionRule extends BaseRule
{
    public function id(): string { return 'laravel.sql-injection.whereraw'; }
    public function title(): string { return 'SQL Injection via Raw Query'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'SQLi'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $pattern = '/(whereRaw|DB::statement|DB::select|DB::raw|selectRaw|havingRaw|orderByRaw)\s*\([^)]*(\$request|\$_GET|\$_POST|\$_REQUEST|request\(\))/';

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;

            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $findings[] = $this->makeFinding(
                        $file, $projectPath, $i + 1,
                        'User input is concatenated or interpolated directly into a raw SQL query. This allows an attacker to inject arbitrary SQL and read, modify, or delete data.',
                        'Use parameterized bindings: whereRaw("column = ?", [$request->input("value")]). Never concatenate user input into raw SQL strings.',
                    );
                }
            }
        }

        return $findings;
    }
}
