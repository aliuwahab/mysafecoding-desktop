<?php

namespace App\Services\Scanners\Rules;

class OrderByInjectionRule extends BaseRule
{
    public function id(): string { return 'laravel.sql-injection.orderby'; }
    public function title(): string { return 'SQL Injection via User-Controlled orderBy'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'SQLi'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $pattern = '/->orderBy\s*\(\s*(\$request|\$_GET|\$_POST|\$_REQUEST|request\(\))/';

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;
            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                        'User-controlled column name passed directly to orderBy(). An attacker can inject SQL via the column parameter since orderBy does not use parameterized bindings for column names.',
                        'Validate the sort column against an allowlist: $allowed = ["name", "created_at"]; $column = in_array($input, $allowed) ? $input : "created_at";',
                    );
                }
            }
        }
        return $findings;
    }
}
