<?php

namespace App\Services;

class EnvAuditor
{
    /** Values that are template placeholders or well-known defaults, never real secrets. */
    protected const PLACEHOLDER_SECRETS = ['changeme', 'change-me', 'change_me', 'secret', 'password', 'your-secret', 'your_secret', 'yoursecret', 'mysecret', 'supersecret', 'keyboard cat', 'test', 'dev', 'development', 'xxx', 'todo'];

    public static function audit(string $projectPath, string $projectType = 'laravel'): array
    {
        $envPath = rtrim($projectPath, '/').'/.env';
        if (! file_exists($envPath)) {
            return ['findings' => [], 'summary' => ['total' => 0, 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'INFO' => 0]];
        }

        $content = file_get_contents($envPath);
        $lines = explode("\n", $content);
        $env = [];
        foreach ($lines as $num => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', preg_replace('/^export\s+/', '', $line), 2);
                $env[trim($key)] = ['value' => trim($value, " \t\n\r\0\x0B\"'"), 'line' => $num + 1];
            }
        }

        $findings = $projectType === 'laravel' ? static::laravelFindings($env) : [];

        // Stack-neutral checks fill in anything the framework checks did not report.
        $reported = array_column($findings, 'key');
        foreach (static::stackFindings($env) as $finding) {
            if (! in_array($finding['key'], $reported, true)) {
                $findings[] = $finding;
            }
        }

        $summary = ['total' => count($findings), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'INFO' => 0];
        foreach ($findings as $f) {
            $summary[$f['severity']] = ($summary[$f['severity']] ?? 0) + 1;
        }

        return ['findings' => $findings, 'summary' => $summary];
    }

    /**
     * Laravel settings: APP_KEY, APP_DEBUG, SESSION_DRIVER, ...
     *
     * @param  array<string, array{value: string, line: int}>  $env
     * @return list<array<string, mixed>>
     */
    protected static function laravelFindings(array $env): array
    {
        $findings = [];

        // APP_DEBUG check
        if (isset($env['APP_DEBUG']) && strtolower($env['APP_DEBUG']['value']) === 'true') {
            $findings[] = [
                'key' => 'APP_DEBUG',
                'severity' => 'CRITICAL',
                'title' => 'Debug mode is enabled',
                'description' => 'APP_DEBUG=true exposes sensitive error details and stack traces. Must be false in production.',
                'current' => $env['APP_DEBUG']['value'],
                'recommended' => 'false',
                'line' => $env['APP_DEBUG']['line'],
            ];
        }

        // APP_ENV check
        if (isset($env['APP_ENV']) && $env['APP_ENV']['value'] === 'local') {
            $findings[] = [
                'key' => 'APP_ENV',
                'severity' => 'MEDIUM',
                'title' => 'Environment set to local',
                'description' => 'APP_ENV=local may enable development features. Use "production" for deployed apps.',
                'current' => $env['APP_ENV']['value'],
                'recommended' => 'production',
                'line' => $env['APP_ENV']['line'],
            ];
        }

        // APP_KEY check
        if (! isset($env['APP_KEY']) || strlen($env['APP_KEY']['value']) < 10) {
            $findings[] = [
                'key' => 'APP_KEY',
                'severity' => 'CRITICAL',
                'title' => 'Application key is missing or weak',
                'description' => 'APP_KEY is used for encryption. A missing or weak key compromises all encrypted data.',
                'current' => isset($env['APP_KEY']) ? $env['APP_KEY']['value'] : '(not set)',
                'recommended' => 'Run: php artisan key:generate',
                'line' => $env['APP_KEY']['line'] ?? 0,
            ];
        }

        // APP_URL check
        if (isset($env['APP_URL']) && str_starts_with($env['APP_URL']['value'], 'http://')) {
            $findings[] = [
                'key' => 'APP_URL',
                'severity' => 'HIGH',
                'title' => 'App URL uses HTTP instead of HTTPS',
                'description' => 'APP_URL should use HTTPS to ensure secure cookies, redirects, and asset URLs.',
                'current' => $env['APP_URL']['value'],
                'recommended' => str_replace('http://', 'https://', $env['APP_URL']['value']),
                'line' => $env['APP_URL']['line'],
            ];
        }

        // DB_PASSWORD check
        if (isset($env['DB_PASSWORD'])) {
            $pw = $env['DB_PASSWORD']['value'];
            if (strlen($pw) < 8 || in_array($pw, ['password', 'secret', 'root', ''])) {
                $findings[] = [
                    'key' => 'DB_PASSWORD',
                    'severity' => 'HIGH',
                    'title' => 'Weak or empty database password',
                    'description' => 'The database password is empty, too short, or a common default. Use a strong unique password.',
                    'current' => '(hidden)',
                    'recommended' => 'Strong unique password (16+ chars)',
                    'line' => $env['DB_PASSWORD']['line'],
                ];
            }
        }

        // SESSION_DRIVER check
        if (isset($env['SESSION_DRIVER']) && $env['SESSION_DRIVER']['value'] === 'file') {
            $findings[] = [
                'key' => 'SESSION_DRIVER',
                'severity' => 'MEDIUM',
                'title' => 'File-based sessions',
                'description' => 'File sessions are not suitable for multi-server deployments. Consider database, redis, or memcached.',
                'current' => $env['SESSION_DRIVER']['value'],
                'recommended' => 'database or redis',
                'line' => $env['SESSION_DRIVER']['line'],
            ];
        }

        // MAIL_MAILER check
        if (isset($env['MAIL_MAILER']) && $env['MAIL_MAILER']['value'] === 'log') {
            $findings[] = [
                'key' => 'MAIL_MAILER',
                'severity' => 'MEDIUM',
                'title' => 'Mail driver set to log',
                'description' => 'Emails are being written to log files instead of being sent. Not suitable for production.',
                'current' => $env['MAIL_MAILER']['value'],
                'recommended' => 'smtp, ses, or mailgun',
                'line' => $env['MAIL_MAILER']['line'],
            ];
        }

        // DEBUGBAR_ENABLED check
        if (isset($env['DEBUGBAR_ENABLED']) && strtolower($env['DEBUGBAR_ENABLED']['value']) === 'true') {
            $findings[] = [
                'key' => 'DEBUGBAR_ENABLED',
                'severity' => 'HIGH',
                'title' => 'Debug bar is enabled',
                'description' => 'Laravel Debugbar exposes queries, routes, and session data. Disable in production.',
                'current' => $env['DEBUGBAR_ENABLED']['value'],
                'recommended' => 'false',
                'line' => $env['DEBUGBAR_ENABLED']['line'],
            ];
        }

        // LOG_LEVEL check
        if (isset($env['LOG_LEVEL']) && $env['LOG_LEVEL']['value'] === 'debug') {
            $findings[] = [
                'key' => 'LOG_LEVEL',
                'severity' => 'MEDIUM',
                'title' => 'Verbose logging enabled',
                'description' => 'Debug-level logging can leak sensitive data to log files. Use "warning" or "error" in production.',
                'current' => $env['LOG_LEVEL']['value'],
                'recommended' => 'warning or error',
                'line' => $env['LOG_LEVEL']['line'],
            ];
        }

        // CACHE_STORE check
        if (isset($env['CACHE_STORE']) && $env['CACHE_STORE']['value'] === 'array') {
            $findings[] = [
                'key' => 'CACHE_STORE',
                'severity' => 'MEDIUM',
                'title' => 'Cache driver set to array',
                'description' => 'Array cache is non-persistent and resets every request. Not suitable for production.',
                'current' => $env['CACHE_STORE']['value'],
                'recommended' => 'redis, memcached, or database',
                'line' => $env['CACHE_STORE']['line'],
            ];
        }

        // QUEUE_CONNECTION check
        if (isset($env['QUEUE_CONNECTION']) && $env['QUEUE_CONNECTION']['value'] === 'sync') {
            $findings[] = [
                'key' => 'QUEUE_CONNECTION',
                'severity' => 'INFO',
                'title' => 'Queue running synchronously',
                'description' => 'Sync queue processes jobs in the request cycle, which can cause timeouts for heavy tasks.',
                'current' => $env['QUEUE_CONNECTION']['value'],
                'recommended' => 'redis, database, or sqs',
                'line' => $env['QUEUE_CONNECTION']['line'],
            ];
        }

        // BCRYPT_ROUNDS check
        if (isset($env['BCRYPT_ROUNDS']) && (int) $env['BCRYPT_ROUNDS']['value'] < 10) {
            $findings[] = [
                'key' => 'BCRYPT_ROUNDS',
                'severity' => 'MEDIUM',
                'title' => 'Low bcrypt cost factor',
                'description' => 'Bcrypt rounds below 10 make password hashes easier to crack.',
                'current' => $env['BCRYPT_ROUNDS']['value'],
                'recommended' => '12',
                'line' => $env['BCRYPT_ROUNDS']['line'],
            ];
        }

        return $findings;
    }

    /**
     * Checks for every stack: debug and development modes (Node, Django,
     * Flask, Rails, Gin, ASP.NET Core, Spring, Phoenix, Symfony), weak
     * secrets, weak database passwords, secrets in browser-exposed variables,
     * disabled TLS verification, wildcard hosts/CORS and plain-HTTP URLs.
     *
     * @param  array<string, array{value: string, line: int}>  $env
     * @return list<array<string, mixed>>
     */
    protected static function stackFindings(array $env): array
    {
        $findings = [];
        $add = static function (string $key, string $severity, string $title, string $description, string $current, string $recommended) use (&$findings, $env): void {
            $findings[] = compact('key', 'severity', 'title', 'description', 'current', 'recommended') + ['line' => $env[$key]['line']];
        };

        foreach ($env as $key => ['value' => $value]) {
            $lower = strtolower($value);

            if (in_array($key, ['DEBUG', 'APP_DEBUG', 'DJANGO_DEBUG', 'FLASK_DEBUG'], true) && in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                $add($key, 'CRITICAL', 'Debug mode is enabled', 'Debug mode shows stack traces, settings and sometimes an interactive console to anyone who triggers an error.', $value, 'false');
            }

            $developmentModes = [
                'NODE_ENV' => ['development', 'dev'], 'FLASK_ENV' => ['development'], 'RAILS_ENV' => ['development'], 'RACK_ENV' => ['development'],
                'GIN_MODE' => ['debug'], 'ASPNETCORE_ENVIRONMENT' => ['development'], 'DOTNET_ENVIRONMENT' => ['development'],
                'MIX_ENV' => ['dev'], 'APP_ENV' => ['dev', 'local', 'development'],
            ];
            if (in_array($lower, $developmentModes[$key] ?? [], true)) {
                $add($key, 'MEDIUM', 'Environment set to development', "{$key}={$value} turns on development behaviour such as verbose errors and unminified, unoptimised code. Use the production value for deployed apps.", $value, $key === 'GIN_MODE' ? 'release' : 'production');
            }
            if ($key === 'SPRING_PROFILES_ACTIVE' && preg_match('/\b(dev|local)\b/', $lower)) {
                $add($key, 'MEDIUM', 'Development Spring profile active', 'Development profiles often enable H2 consoles, debug logging and relaxed security.', $value, 'prod');
            }

            if (preg_match('/^(NEXT_PUBLIC|VITE|REACT_APP|NUXT_PUBLIC|PUBLIC|EXPO_PUBLIC|GATSBY)_\w*(SECRET|PRIVATE|PASSWORD|SERVICE_ROLE)/', $key) && $value !== '') {
                $add($key, 'HIGH', 'Secret in a browser-exposed variable', 'Variables with this prefix are bundled into the JavaScript every visitor downloads, so this secret is public.', '(hidden)', 'Drop the public prefix and use it only on the server');

                continue;
            }

            if (preg_match('/(^|_)(SECRET|SECRET_KEY|SECRET_KEY_BASE|JWT_SECRET|SESSION_SECRET|APP_SECRET|COOKIE_SECRET|AUTH_SECRET|NEXTAUTH_SECRET|SIGNING_KEY|ENCRYPTION_KEY)$/', $key) && $key !== 'APP_KEY') {
                if (strlen($value) < 32 || in_array($lower, self::PLACEHOLDER_SECRETS, true) || str_starts_with($lower, 'django-insecure-')) {
                    $add($key, 'HIGH', 'Weak or placeholder secret', 'This secret signs sessions or tokens. A short or default value can be guessed, letting attackers forge logins.', $value === '' ? '(empty)' : '(hidden)', '32+ random bytes, e.g. openssl rand -hex 32');
                }
            }

            if (preg_match('/^(DB|DATABASE|POSTGRES|POSTGRESQL|MYSQL|MARIADB|MONGO|MONGODB|REDIS)_PASS(WORD)?$/', $key)
                && (strlen($value) < 8 || in_array($lower, ['password', 'secret', 'root', 'postgres', 'admin', 'mysql', 'changeme'], true))) {
                $add($key, 'HIGH', 'Weak or empty database password', 'The database password is empty, too short, or a common default.', '(hidden)', 'Strong unique password (16+ chars)');
            }
            if (preg_match('/^(DATABASE_URL|DB_URL|MONGO_URL|MONGODB_URI|REDIS_URL)$/', $key)
                && preg_match('#://[^:/@]+:(password|secret|root|postgres|admin|mysql|changeme)@#i', $value)) {
                $add($key, 'HIGH', 'Default password in connection URL', 'The connection URL uses a well-known default password.', '(hidden)', 'Strong unique password (16+ chars)');
            }

            if (($key === 'NODE_TLS_REJECT_UNAUTHORIZED' && $value === '0') || (in_array($key, ['PYTHONHTTPSVERIFY', 'CURL_INSECURE'], true) && in_array($lower, ['0', 'false'], true))) {
                $add($key, 'CRITICAL', 'TLS certificate verification disabled', 'Every outgoing HTTPS request accepts any certificate, so traffic can be intercepted.', $value, 'Remove it and trust the right CA instead');
            }

            if (in_array($key, ['ALLOWED_HOSTS', 'DJANGO_ALLOWED_HOSTS'], true) && preg_match('/(^|,)\s*\*\s*(,|$)/', $value)) {
                $add($key, 'HIGH', 'Any Host header accepted', 'A wildcard ALLOWED_HOSTS disables Host header validation, enabling cache poisoning and password-reset link hijacking.', $value, 'Your real domain names');
            }
            if (preg_match('/^(CORS_ORIGINS?|CORS_ALLOWED_ORIGINS|ALLOWED_ORIGINS|CORS_ALLOW_ORIGIN)$/', $key) && trim($value) === '*') {
                $add($key, 'MEDIUM', 'CORS allows every origin', 'Any website can call your API from a visitor\'s browser and read the responses.', $value, 'Your frontend origin(s)');
            }

            if (preg_match('/^(APP_URL|BASE_URL|SITE_URL|PUBLIC_URL|NEXTAUTH_URL|ORIGIN|FRONTEND_URL)$/', $key)
                && str_starts_with($lower, 'http://') && ! preg_match('#^http://(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:|/|$)#', $lower)) {
                $add($key, 'MEDIUM', 'URL uses HTTP instead of HTTPS', 'Plain HTTP URLs lead to insecure redirects, cookies without the Secure flag and mixed content.', $value, str_replace('http://', 'https://', $value));
            }

            if (in_array($key, ['LOG_LEVEL', 'RUST_LOG', 'RAILS_LOG_LEVEL', 'LOGGING_LEVEL_ROOT'], true) && in_array($lower, ['debug', 'trace'], true)) {
                $add($key, 'MEDIUM', 'Verbose logging enabled', 'Debug and trace logs often contain request bodies, tokens and personal data.', $value, 'info or warn');
            }
        }

        return $findings;
    }
}
