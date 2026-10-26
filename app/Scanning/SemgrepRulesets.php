<?php

namespace App\Scanning;

class SemgrepRulesets
{
    /**
     * Security rulesets: Semgrep registry packs for the detected stack plus
     * the CheckMyCode custom rules shipped in resources/scanners.
     *
     * @return list<string>
     */
    public static function security(string $projectType): array
    {
        $configs = ['p/secrets'];

        $configs = [...$configs, ...match (true) {
            ProjectDetector::isPhp($projectType) => ['p/php', resource_path('scanners/php.yml')],
            ProjectDetector::isJavaScript($projectType) => ['p/javascript', 'p/typescript', resource_path('scanners/javascript.yml')],
            ProjectDetector::isPython($projectType) => ['p/python', resource_path('scanners/python.yml')],
            ProjectDetector::isRuby($projectType) => ['p/ruby', resource_path('scanners/ruby.yml')],
            ProjectDetector::isJava($projectType) => ['p/java', 'p/kotlin'],
            $projectType === 'go' => ['p/golang'],
            $projectType === 'rust' => ['p/rust'],
            $projectType === 'dotnet' => ['p/csharp'],
            default => [],
        }];

        // Framework-specific packs.
        $configs = [...$configs, ...match ($projectType) {
            'django' => ['p/django'],
            'flask' => ['p/flask'],
            'fastapi' => ['p/fastapi'],
            'react', 'nextjs' => ['p/react'],
            'node', 'express', 'nestjs' => ['p/nodejsscan'],
            default => [],
        }];

        // Cross-language OWASP Top 10 coverage (injection, XSS, crypto, JWT, ...).
        if ($projectType !== 'unknown') {
            $configs[] = 'p/owasp-top-ten';
        }

        return array_values(array_unique($configs));
    }

    /**
     * Best-practice rulesets for the Quality tab.
     *
     * @return list<string>
     */
    public static function quality(string $projectType): array
    {
        return match ($projectType) {
            'laravel', 'symfony', 'php' => ['p/phpcs-security-audit'],
            'react', 'nextjs' => ['p/react-best-practices', 'p/typescript'],
            'vue', 'nuxt', 'svelte', 'angular' => ['p/javascript', 'p/typescript'],
            'node', 'express', 'nestjs' => ['p/nodejs', 'p/typescript'],
            'django', 'flask', 'fastapi', 'python' => ['p/bandit'],
            'rails' => ['p/brakeman'],
            'ruby' => ['p/ruby'],
            'go' => ['p/gosec'],
            'rust' => ['p/rust'],
            'spring' => ['p/findsecbugs', 'p/java'],
            'java' => ['p/java'],
            'dotnet' => ['p/csharp'],
            default => [],
        };
    }

    public static function displayName(string $config): string
    {
        return str_starts_with($config, 'p/') ? $config : 'checkmycode/'.basename($config, '.yml');
    }
}
