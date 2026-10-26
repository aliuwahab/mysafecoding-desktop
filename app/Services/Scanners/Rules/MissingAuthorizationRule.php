<?php

namespace App\Services\Scanners\Rules;

class MissingAuthorizationRule extends BaseRule
{
    public function id(): string
    {
        return 'laravel.auth.missing-policy';
    }

    public function title(): string
    {
        return 'Controller Missing Authorization';
    }

    public function severity(): string
    {
        return 'MEDIUM';
    }

    public function category(): string
    {
        return 'AuthZ';
    }

    public function check(string $projectPath): array
    {
        $findings = [];
        $authPatterns = ['authorize(', 'authorizeResource(', 'Gate::', '->can(', 'can(', "middleware('auth", 'middleware("auth', "middleware('can:", 'middleware("can:', '#[Authorize', "#[Middleware('can:", 'Policy'];
        $mutating = '/public\s+function\s+(store|update|destroy|delete|create|edit)\s*\(([^)]*)\)/';

        foreach ($this->findPhpFiles($projectPath, 'app/Http/Controllers') as $file) {
            $content = $this->readFile($file);
            if (! $content) {
                continue;
            }
            if (preg_match('/abstract\s+class/', $content)) {
                continue;
            }
            if (! preg_match_all($mutating, $content, $methods)) {
                continue;
            }

            foreach ($authPatterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    continue 2;
                }
            }

            // Form Requests are where Laravel puts per-request authorization and checks.
            foreach ($this->formRequestsUsedBy($content, $projectPath) as $request) {
                if (preg_match('/function\s+authorize\s*\(/', $request)) {
                    continue 2;
                }
            }

            // Self-service controllers (profile, password, 2FA...) only act on the logged-in
            // user and take no route-bound model or id that could point at someone else.
            $actsOnCurrentUser = (bool) preg_match('/->user\(\)|auth\(\)->(user|id)\(\)|Auth::(user|id)\(\)|#\[CurrentUser\]/', $content);
            // Only route-bound Eloquent models or raw ids can point at someone else's records;
            // injected services/actions don't.
            $targetsOtherRecords = false;
            foreach ($methods[2] as $params) {
                preg_match_all('/\b([A-Z]\w*)\s+\$\w+/', $params, $types);
                $boundModels = array_filter($types[1], fn (string $type): bool => is_file($projectPath.'/app/Models/'.$type.'.php')
                    || preg_match('/^use\s+App\\\\Models\\\\(\w+\\\\)*'.$type.';/m', $content));
                if ($boundModels !== [] || preg_match('/\$id\b|int\s+\$\w+/', $params)) {
                    $targetsOtherRecords = true;
                    break;
                }
            }
            if ($actsOnCurrentUser && ! $targetsOtherRecords) {
                continue;
            }

            foreach (explode("\n", $content) as $i => $l) {
                if (preg_match('/class\s+\w+/', $l)) {
                    $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                        'Controller has state-changing methods (store/update/destroy) but no authorization check was found (no policy, Gate, can: middleware, or Form Request authorize()). Any user who can reach these routes may be able to change records that are not theirs.',
                        'Add $this->authorize() / Gate checks, can: middleware, or a Form Request with authorize(). If these routes are public by design or already protected by route middleware, mark this finding as ignored.',
                    );
                    break;
                }
            }
        }

        return $findings;
    }
}
