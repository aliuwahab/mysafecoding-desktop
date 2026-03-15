<?php

namespace App\Services\Scanners\Rules;

class EvalDynamicRule extends BaseRule
{
    public function id(): string { return 'laravel.eval.dynamic'; }
    public function title(): string { return 'Dynamic eval() Usage'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'RCE'; }

    public function check(string $projectPath): array
    {
        $findings = [];

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;

            foreach ($lines as $i => $line) {
                if (preg_match('/\beval\s*\(\s*.*\$/', $line)) {
                    $findings[] = $this->makeFinding(
                        $file, $projectPath, $i + 1,
                        'eval() called with a dynamic expression. If any part of the evaluated string comes from user input, an attacker can execute arbitrary PHP code on the server.',
                        'Remove eval() entirely. Replace with specific logic, a template engine, or a sandboxed expression evaluator.',
                    );
                }
            }
        }

        return $findings;
    }
}
