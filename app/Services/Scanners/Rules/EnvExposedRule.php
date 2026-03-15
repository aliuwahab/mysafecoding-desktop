<?php

namespace App\Services\Scanners\Rules;

class EnvExposedRule extends BaseRule
{
    public function id(): string { return 'laravel.env.exposed'; }
    public function title(): string { return '.env File Exposed in Public Directory'; }
    public function severity(): string { return 'CRITICAL'; }
    public function category(): string { return 'Config'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $publicDir = $projectPath.'/public';
        if (! is_dir($publicDir)) return [];

        $envFiles = glob($publicDir.'/.env*');
        foreach ($envFiles as $envFile) {
            $findings[] = $this->makeFinding($envFile, $projectPath, 1,
                'A .env file was found in the public/ directory. This file contains database credentials, API keys, and application secrets. It is directly accessible via the web.',
                'Remove the .env file from public/ immediately. Ensure your web server blocks access to dotfiles. Rotate all secrets that were exposed.',
                '.env file found in web-accessible directory',
            );
        }
        return $findings;
    }
}
