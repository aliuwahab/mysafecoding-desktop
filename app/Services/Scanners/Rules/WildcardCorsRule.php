<?php

namespace App\Services\Scanners\Rules;

class WildcardCorsRule extends BaseRule
{
    public function id(): string { return 'laravel.cors.wildcard'; }
    public function title(): string { return 'CORS Allows All Origins'; }
    public function severity(): string { return 'MEDIUM'; }
    public function category(): string { return 'Config'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $corsFile = $projectPath.'/config/cors.php';
        if (! file_exists($corsFile)) return [];
        $content = $this->readFile($corsFile);
        if (! $content) return [];

        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (preg_match("/'allowed_origins'\s*=>\s*\[\s*'\*'\s*\]/", $line)) {
                $findings[] = $this->makeFinding($corsFile, $projectPath, $i + 1,
                    "CORS allowed_origins is set to wildcard '*'. Any website can make cross-origin requests to your API, potentially accessing user data.",
                    "Replace '*' with specific trusted domains: 'allowed_origins' => ['https://yourapp.com'].",
                );
            }
        }
        return $findings;
    }
}
