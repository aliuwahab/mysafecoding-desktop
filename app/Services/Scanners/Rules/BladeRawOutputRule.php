<?php

namespace App\Services\Scanners\Rules;

class BladeRawOutputRule extends BaseRule
{
    public function id(): string { return 'laravel.blade.raw-output'; }
    public function title(): string { return 'Unescaped Blade Output'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'XSS'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        foreach ($this->findBladeFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) continue;
            foreach ($lines as $i => $line) {
                if (preg_match('/\{!!\s*.+?\s*!!\}/', $line)) {
                    $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                        'Unescaped Blade output {!! !!} renders raw HTML. If the variable contains user-supplied data, this is a cross-site scripting (XSS) vulnerability.',
                        'Use escaped output {{ }} instead. If raw HTML is intentional (e.g., rendering markdown), ensure the data is sanitized with a library like HTMLPurifier.',
                    );
                }
            }
        }
        return $findings;
    }
}
