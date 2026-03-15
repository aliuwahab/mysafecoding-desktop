<?php

namespace App\Services\Scanners\Rules;

class WeakHashRule extends BaseRule
{
    public function id(): string { return 'laravel.hash.weak'; }
    public function title(): string { return 'Weak Hashing Algorithm for Passwords'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'Crypto'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;
            foreach ($lines as $i => $line) {
                if (preg_match('/(md5|sha1)\s*\(\s*\$/', $line)) {
                    $context = implode("\n", array_slice($lines, max(0, $i - 3), 7));
                    $lowerContext = strtolower($context);
                    if (str_contains($lowerContext, 'password') || str_contains($lowerContext, 'passwd') || str_contains($lowerContext, 'secret')) {
                        $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                            'md5() or sha1() used near password handling. These are fast hashing algorithms unsuitable for passwords — they can be brute-forced in seconds with modern hardware.',
                            'Use Hash::make() (bcrypt/argon2) for password hashing. Never use md5() or sha1() for passwords.',
                        );
                    }
                }
            }
        }
        return $findings;
    }
}
