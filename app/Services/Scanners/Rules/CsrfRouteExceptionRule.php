<?php

namespace App\Services\Scanners\Rules;

class CsrfRouteExceptionRule extends BaseRule
{
    public function id(): string { return 'laravel.csrf.route-exception'; }
    public function title(): string { return 'CSRF Protection Bypassed for Routes'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'Security'; }

    public function check(string $projectPath): array
    {
        $findings = [];

        $middlewareFile = $projectPath.'/app/Http/Middleware/VerifyCsrfToken.php';
        if (file_exists($middlewareFile)) {
            $content = $this->readFile($middlewareFile);
            if ($content && preg_match('/\$except\s*=\s*\[([^\]]+)\]/s', $content, $match)) {
                $entries = trim($match[1]);
                if (! empty($entries)) {
                    foreach (explode("\n", $content) as $i => $l) {
                        if (str_contains($l, '$except')) {
                            $findings[] = $this->makeFinding($middlewareFile, $projectPath, $i + 1,
                                "Routes excluded from CSRF verification: {$entries}. These routes are vulnerable to cross-site request forgery attacks.",
                                'Remove routes from the $except array unless they genuinely need to accept external POST requests (e.g., payment webhooks). For webhooks, use signature verification instead.',
                            );
                            break;
                        }
                    }
                }
            }
        }

        $bootstrapFile = $projectPath.'/bootstrap/app.php';
        if (file_exists($bootstrapFile)) {
            $content = $this->readFile($bootstrapFile);
            if ($content && preg_match('/validateCsrfTokens\s*\(\s*except\s*:\s*\[([^\]]+)\]/s', $content, $match)) {
                $entries = trim($match[1]);
                if (! empty($entries)) {
                    foreach (explode("\n", $content) as $i => $l) {
                        if (str_contains($l, 'validateCsrfTokens')) {
                            $findings[] = $this->makeFinding($bootstrapFile, $projectPath, $i + 1,
                                "Routes excluded from CSRF verification: {$entries}.",
                                'Remove CSRF exceptions unless required for external webhook endpoints. Use signature verification for webhooks.',
                            );
                            break;
                        }
                    }
                }
            }
        }
        return $findings;
    }
}
