<?php

namespace App\Services\Scanners\Rules;

class InsecureCookieRule extends BaseRule
{
    public function id(): string { return 'laravel.cookie.insecure'; }
    public function title(): string { return 'Insecure Cookie Configuration'; }
    public function severity(): string { return 'MEDIUM'; }
    public function category(): string { return 'Config'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        $sessionFile = $projectPath.'/config/session.php';
        if (! file_exists($sessionFile)) return [];
        $content = $this->readFile($sessionFile);
        if (! $content) return [];

        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (preg_match("/'secure'\s*=>\s*false/", $line) || preg_match("/'secure'\s*=>\s*env\(\s*'SESSION_SECURE_COOKIE'\s*,\s*false\s*\)/", $line)) {
                $findings[] = $this->makeFinding($sessionFile, $projectPath, $i + 1,
                    'Session cookies are not marked as secure. They will be sent over plain HTTP connections, allowing attackers on the network to steal session tokens.',
                    "Set 'secure' => true in config/session.php or set SESSION_SECURE_COOKIE=true in .env for HTTPS-only cookies.",
                );
            }
            if (preg_match("/'http_only'\s*=>\s*false/", $line)) {
                $findings[] = $this->makeFinding($sessionFile, $projectPath, $i + 1,
                    'Session cookies have http_only set to false. JavaScript can access the cookie, enabling XSS-based session theft.',
                    "Set 'http_only' => true in config/session.php to prevent JavaScript access to session cookies.",
                );
            }
        }
        return $findings;
    }
}
