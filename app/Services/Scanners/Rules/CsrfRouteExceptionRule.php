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

        // Check Laravel 10- style: VerifyCsrfToken middleware $except
        $middlewareFile = $projectPath.'/app/Http/Middleware/VerifyCsrfToken.php';
        if (file_exists($middlewareFile)) {
            $content = $this->readFile($middlewareFile);
            if ($content && preg_match('/\$except\s*=\s*\[([^\]]+)\]/s', $content, $match)) {
                $entries = trim($match[1]);
                if (! empty($entries) && $entries !== '/') {
                    $lines = explode("\n", $content);
                    $line = 1;
                    foreach ($lines as $i => $l) {
                        if (str_contains($l, '$except')) {
                            $line = $i + 1;
                            break;
                        }
                    }

                    $findings[] = $this->makeFinding(
                        $middlewareFile, $projectPath, $line,
                        "Routes are excluded from CSRF verification: {$entries}. These routes are vulnerable to cross-site request forgery attacks.",
                        'Remove routes from the $except array unless they genuinely need to accept external POST requests (e.g., payment webhooks). For webhooks, use signature verification instead.',
                    );
                }
            }
        }

        // Check Laravel 11+ style: bootstrap/app.php withMiddleware
        $bootstrapFile = $projectPath.'/bootstrap/app.php';
        if (file_exists($bootstrapFile)) {
            $content = $this->readFile($bootstrapFile);
            if ($content && preg_match('/validateCsrfTokens\s*\(\s*except\s*:\s*\[([^\]]+)\]/s', $content, $match)) {
                $entries = trim($match[1]);
                if (! empty($entries)) {
                    $lines = explode("\n", $content);
                    $line = 1;
                    foreach ($lines as $i => $l) {
                        if (str_contains($l, 'validateCsrfTokens')) {
                            $line = $i + 1;
                            break;
                        }
                    }

                    $findings[] = $this->makeFinding(
                        $bootstrapFile, $projectPath, $line,
                        "Routes are excluded from CSRF verification: {$entries}.",
                        'Remove CSRF exceptions unless required for external webhook endpoints. Use signature verification for webhooks.',
                    );
                }
            }
        }

        return $findings;
    }
}
