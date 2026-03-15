<?php

namespace App\Services\Scanners\Rules;

class UnserializeInputRule extends BaseRule
{
    public function id(): string { return 'laravel.unserialize.user-input'; }
    public function title(): string { return 'Unsafe Deserialization of User Input'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'RCE'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $pattern = '/unserialize\s*\(\s*(\$request|\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|request\(\)|Input::)/';

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;
            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                        'unserialize() called on user-controlled data. An attacker can craft a serialized payload that triggers arbitrary code execution via PHP magic methods.',
                        "Use json_decode() instead of unserialize() for user data. If serialization is required, use signed/encrypted values via Laravel's encrypt()/decrypt().",
                    );
                }
            }
        }
        return $findings;
    }
}
