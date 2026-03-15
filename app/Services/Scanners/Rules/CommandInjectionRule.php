<?php

namespace App\Services\Scanners\Rules;

class CommandInjectionRule extends BaseRule
{
    public function id(): string { return 'laravel.command.injection'; }
    public function title(): string { return 'OS Command Injection'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'RCE'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $functions = 'shell_exec|exec|system|passthru|popen|proc_open';
        $pattern = '/\b('.$functions.')\s*\([^)]*(\$request|\$_GET|\$_POST|\$_REQUEST|request\(\))/';

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;
            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                        'OS command execution function called with user input. An attacker can inject shell commands to gain full control of the server.',
                        "Use Laravel's Process facade with explicit arguments instead of string concatenation. If shell commands are unavoidable, use escapeshellarg() on every user-provided value.",
                    );
                }
            }
        }
        return $findings;
    }
}
