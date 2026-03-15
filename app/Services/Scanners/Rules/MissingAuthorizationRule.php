<?php

namespace App\Services\Scanners\Rules;

class MissingAuthorizationRule extends BaseRule
{
    public function id(): string { return 'laravel.auth.missing-policy'; }
    public function title(): string { return 'Controller Missing Authorization'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'AuthZ'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $authPatterns = ['authorize(', '$this->authorize(', 'Gate::check(', 'Gate::allows(', 'Gate::denies(', 'Gate::authorize(', 'can(', '->can(', "middleware('auth", "middleware('can:", 'Policy'];

        foreach ($this->findPhpFiles($projectPath, 'app/Http/Controllers') as $file) {
            $content = $this->readFile($file);
            if (! $content) continue;
            if (preg_match('/abstract\s+class/', $content)) continue;

            if (preg_match_all('/public\s+function\s+(store|update|destroy|delete|create|edit)\s*\(/', $content)) {
                $hasAuth = false;
                foreach ($authPatterns as $pattern) {
                    if (str_contains($content, $pattern)) { $hasAuth = true; break; }
                }
                if (! $hasAuth) {
                    foreach (explode("\n", $content) as $i => $l) {
                        if (preg_match('/class\s+\w+/', $l)) {
                            $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                                'Controller has state-changing methods (store/update/destroy) but no authorization checks found. Any authenticated user may be able to perform privileged actions.',
                                'Add $this->authorize() calls, Gate checks, or policy-based middleware to protect state-changing actions.',
                            );
                            break;
                        }
                    }
                }
            }
        }
        return $findings;
    }
}
