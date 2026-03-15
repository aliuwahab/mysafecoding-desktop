<?php

namespace App\Services\Scanners\Rules;

class OpenRedirectRule extends BaseRule
{
    public function id(): string { return 'laravel.open-redirect'; }
    public function title(): string { return 'Open Redirect Vulnerability'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'Redirect'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $pattern = '/(redirect|Redirect::to|redirect\(\))\s*\(\s*(\$request|\$_GET|\$_POST|request\(\))/';

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;

            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $findings[] = $this->makeFinding(
                        $file, $projectPath, $i + 1,
                        'redirect() receives a user-supplied URL without validation. An attacker can craft a link that redirects users to a malicious site after authentication.',
                        'Validate redirect URLs against an allowlist of trusted domains, or use redirect()->intended() for post-login redirects.',
                    );
                }
            }
        }

        return $findings;
    }
}
