<?php

namespace App\Scanning;

class SemgrepRulesets
{
    /**
     * Security rulesets: Semgrep registry packs for the detected stack plus
     * the MySafeCoding custom rules shipped in resources/scanners.
     *
     * @return list<string>
     */
    public static function security(string $projectType): array
    {
        $configs = ['p/secrets'];

        if (ProjectDetector::isPhp($projectType)) {
            array_push($configs, 'p/php', resource_path('scanners/php.yml'));
        } elseif (ProjectDetector::isJavaScript($projectType)) {
            array_push($configs, 'p/javascript', 'p/typescript', resource_path('scanners/javascript.yml'));
        } elseif (ProjectDetector::isPython($projectType)) {
            array_push($configs, 'p/python', resource_path('scanners/python.yml'));
        } elseif (ProjectDetector::isRuby($projectType)) {
            array_push($configs, 'p/ruby', resource_path('scanners/ruby.yml'));
        } elseif (ProjectDetector::isJava($projectType)) {
            $configs[] = 'p/java';
        } else {
            $configs = [...$configs, ...match ($projectType) {
                'go' => ['p/golang'],
                'rust' => ['p/rust'],
                'dotnet' => ['p/csharp'],
                default => [],
            }];
        }

        return $configs;
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
            'react' => ['p/react', 'p/react-best-practices', 'p/typescript'],
            'nextjs' => ['p/react', 'p/react-best-practices', 'p/typescript'],
            'vue', 'nuxt', 'svelte', 'angular' => ['p/javascript', 'p/typescript'],
            'node', 'express', 'nestjs' => ['p/nodejs', 'p/typescript'],
            'django' => ['p/django', 'p/bandit'],
            'flask' => ['p/flask', 'p/bandit'],
            'fastapi', 'python' => ['p/bandit'],
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
        return str_starts_with($config, 'p/') ? $config : 'mysafecoding/'.basename($config, '.yml');
    }
}
