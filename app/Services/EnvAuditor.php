<?php

namespace App\Services;

class EnvAuditor
{
    public static function audit(string $projectPath): array
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
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = ['value' => trim($value, " \t\n\r\0\x0B\"'"), 'line' => $num + 1];
            }
        }

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

        $summary = ['total' => count($findings), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'INFO' => 0];
        foreach ($findings as $f) {
            $summary[$f['severity']] = ($summary[$f['severity']] ?? 0) + 1;
        }

        return ['findings' => $findings, 'summary' => $summary];
    }
}
