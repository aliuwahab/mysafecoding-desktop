<?php

namespace App\Services\Scanners\Rules;

class EmptyGuardedRule extends BaseRule
{
    public function id(): string { return 'laravel.mass-assignment.empty-guarded'; }
    public function title(): string { return 'Mass Assignment: Empty $guarded'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'Code'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        foreach ($this->findPhpFiles($projectPath, 'app/Models') as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;
            foreach ($lines as $i => $line) {
                if (preg_match('/\$guarded\s*=\s*\[\s*\]/', $line)) {
                    $findings[] = $this->makeFinding(
                        $file, $projectPath, $i + 1,
                        'Model has $guarded set to an empty array, disabling all mass assignment protection. An attacker controlling the request body can overwrite any database column.',
                        'Replace $guarded = [] with an explicit $fillable array listing only the columns that should be mass-assignable.',
                    );
                }
            }
        }
        return $findings;
    }
}
