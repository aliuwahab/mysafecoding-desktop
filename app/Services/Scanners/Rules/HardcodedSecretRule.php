<?php

namespace App\Services\Scanners\Rules;

class HardcodedSecretRule extends BaseRule
{
    public function id(): string { return 'laravel.secrets.hardcoded-key'; }
    public function title(): string { return 'Hardcoded Secret in Source Code'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'Secrets'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $patterns = [
            '/["\']sk_live_[a-zA-Z0-9]{20,}["\']/' => 'Stripe live secret key',
            '/["\']sk_test_[a-zA-Z0-9]{20,}["\']/' => 'Stripe test secret key',
            '/["\']AKIA[0-9A-Z]{16}["\']/' => 'AWS Access Key ID',
            '/["\']ghp_[a-zA-Z0-9]{36}["\']/' => 'GitHub personal access token',
            '/["\']glpat-[a-zA-Z0-9\-_]{20,}["\']/' => 'GitLab personal access token',
        ];

        foreach (['app', 'config', 'routes'] as $dir) {
            foreach ($this->findPhpFiles($projectPath, $dir) as $file) {
                $lines = $this->readLines($file);
                if (! $lines) continue;
                foreach ($lines as $i => $line) {
                    foreach ($patterns as $pattern => $label) {
                        if (preg_match($pattern, $line)) {
                            $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                                "Hardcoded {$label} found in source code. Secrets in source code are exposed to anyone with repository access and persist in git history.",
                                'Move the secret to .env and reference it via env() or config(). Rotate the exposed key immediately.',
                            );
                        }
                    }
                }
            }
        }
        return $findings;
    }
}
