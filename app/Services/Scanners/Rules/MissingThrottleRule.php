<?php

namespace App\Services\Scanners\Rules;

class MissingThrottleRule extends BaseRule
{
    public function id(): string { return 'laravel.auth.missing-throttle'; }
    public function title(): string { return 'Login Route Missing Rate Limiting'; }
    public function severity(): string { return 'MEDIUM'; }
    public function category(): string { return 'BruteForce'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        foreach ($this->findPhpFiles($projectPath, 'routes') as $file) {
            $content = $this->readFile($file);
            if (! $content) continue;
            $lines = explode("\n", $content);
            foreach ($lines as $i => $line) {
                if (preg_match("/(login|register|password)/i", $line) && preg_match("/Route::(post|any)\s*\(/", $line)) {
                    $contextStart = max(0, $i - 10);
                    $context = implode("\n", array_slice($lines, $contextStart, 20));
                    if (! str_contains($context, 'throttle')) {
                        $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                            'Authentication route (login/register/password) found without throttle middleware. This allows brute-force attacks against user credentials.',
                            "Add rate limiting: Route::post('/login', ...)->middleware('throttle:5,1') to limit to 5 attempts per minute.",
                        );
                    }
                }
            }
        }
        return $findings;
    }
}
