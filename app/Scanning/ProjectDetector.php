<?php

namespace App\Scanning;

class ProjectDetector
{
    public const LABELS = [
        'laravel' => 'Laravel', 'symfony' => 'Symfony', 'php' => 'PHP',
        'nextjs' => 'Next.js', 'nuxt' => 'Nuxt', 'react' => 'React', 'vue' => 'Vue', 'svelte' => 'Svelte',
        'angular' => 'Angular', 'nestjs' => 'NestJS', 'express' => 'Express', 'node' => 'Node',
        'django' => 'Django', 'flask' => 'Flask', 'fastapi' => 'FastAPI', 'python' => 'Python',
        'rails' => 'Rails', 'ruby' => 'Ruby', 'go' => 'Go', 'rust' => 'Rust',
        'phoenix' => 'Phoenix', 'elixir' => 'Elixir', 'spring' => 'Spring', 'java' => 'Java', 'dotnet' => '.NET',
        'unknown' => 'Project',
    ];

    public static function detect(string $path): string
    {
        $path = rtrim($path, '/');

        if (is_file($path)) {
            return static::detectFromExtension($path);
        }

        if (file_exists($path.'/composer.json')) {
            if (file_exists($path.'/artisan') || static::composerHasPackage($path, 'laravel/framework')) {
                return 'laravel';
            }
            if (static::composerHasPackage($path, 'symfony/framework-bundle')) {
                return 'symfony';
            }

            return 'php';
        }
        foreach (['requirements.txt', 'pyproject.toml', 'setup.py', 'Pipfile'] as $signature) {
            if (! file_exists($path.'/'.$signature)) {
                continue;
            }
            if (file_exists($path.'/manage.py') || static::pythonProjectMentions($path, 'django')) {
                return 'django';
            }
            if (static::pythonProjectMentions($path, 'fastapi')) {
                return 'fastapi';
            }
            if (static::pythonProjectMentions($path, 'flask')) {
                return 'flask';
            }

            return 'python';
        }
        if (file_exists($path.'/Gemfile')) {
            return static::fileContains($path.'/Gemfile', 'rails') ? 'rails' : 'ruby';
        }
        if (file_exists($path.'/go.mod')) {
            return 'go';
        }
        if (file_exists($path.'/Cargo.toml')) {
            return 'rust';
        }
        if (file_exists($path.'/mix.exs')) {
            return static::fileContains($path.'/mix.exs', 'phoenix') ? 'phoenix' : 'elixir';
        }
        if (file_exists($path.'/build.gradle') || file_exists($path.'/build.gradle.kts') || file_exists($path.'/pom.xml')) {
            return static::javaProjectMentionsSpring($path) ? 'spring' : 'java';
        }
        if (static::hasFileMatching($path, ['*.csproj', '*.sln'])) {
            return 'dotnet';
        }

        // Checked last: Rails, Django, .NET and other backends often carry a
        // package.json just for their frontend assets.
        if (file_exists($path.'/package.json')) {
            foreach ([
                'nextjs' => ['next'],
                'nuxt' => ['nuxt'],
                'angular' => ['@angular/core', 'angular'],
                'svelte' => ['svelte'],
                'vue' => ['vue'],
                'react' => ['react'],
                'nestjs' => ['@nestjs/core'],
                'express' => ['express'],
            ] as $type => $packages) {
                foreach ($packages as $package) {
                    if (static::packageJsonHasPackage($path, $package)) {
                        return $type;
                    }
                }
            }

            return 'node';
        }

        return 'unknown';
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? 'Project';
    }

    public static function isPhp(string $type): bool
    {
        return in_array($type, ['laravel', 'symfony', 'php'], true);
    }

    public static function isPython(string $type): bool
    {
        return in_array($type, ['django', 'flask', 'fastapi', 'python'], true);
    }

    public static function isJavaScript(string $type): bool
    {
        return in_array($type, ['nextjs', 'nuxt', 'react', 'vue', 'svelte', 'angular', 'nestjs', 'express', 'node'], true);
    }

    public static function isRuby(string $type): bool
    {
        return in_array($type, ['rails', 'ruby'], true);
    }

    public static function isJava(string $type): bool
    {
        return in_array($type, ['spring', 'java'], true);
    }

    public static function isElixir(string $type): bool
    {
        return in_array($type, ['phoenix', 'elixir'], true);
    }

    protected static function detectFromExtension(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'php' => 'php',
            'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx' => 'node',
            'py' => 'python',
            'rb' => 'ruby',
            'go' => 'go',
            'rs' => 'rust',
            'ex', 'exs' => 'elixir',
            'java', 'kt' => 'java',
            'cs' => 'dotnet',
            default => 'unknown',
        };
    }

    protected static function composerHasPackage(string $path, string $package): bool
    {
        $composer = static::readJsonFile($path.'/composer.json');

        return isset($composer['require'][$package]) || isset($composer['require-dev'][$package]);
    }

    protected static function packageJsonHasPackage(string $path, string $package): bool
    {
        $packageJson = static::readJsonFile($path.'/package.json');

        foreach (['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies'] as $section) {
            if (isset($packageJson[$section][$package])) {
                return true;
            }
        }

        return false;
    }

    protected static function pythonProjectMentions(string $path, string $package): bool
    {
        foreach (['requirements.txt', 'pyproject.toml', 'setup.py', 'Pipfile'] as $file) {
            if (static::fileContains($path.'/'.$file, $package)) {
                return true;
            }
        }

        return false;
    }

    protected static function javaProjectMentionsSpring(string $path): bool
    {
        foreach (['build.gradle', 'build.gradle.kts', 'pom.xml'] as $file) {
            if (static::fileContains($path.'/'.$file, 'spring-boot') || static::fileContains($path.'/'.$file, 'springframework')) {
                return true;
            }
        }

        return false;
    }

    protected static function fileContains(string $file, string $needle): bool
    {
        return file_exists($file) && str_contains(strtolower(file_get_contents($file) ?: ''), strtolower($needle));
    }

    protected static function readJsonFile(string $file): array
    {
        if (! file_exists($file)) {
            return [];
        }

        $json = json_decode(file_get_contents($file) ?: '', true);

        return is_array($json) ? $json : [];
    }

    /**
     * Look for matching files up to three directories deep, skipping
     * dependency folders, so huge trees do not stall detection.
     */
    protected static function hasFileMatching(string $path, array $patterns, int $maxDepth = 3): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $file): bool => ! ($file->isDir() && in_array($file->getFilename(), ['.git', 'node_modules', 'vendor', 'bin', 'obj'], true)),
            ),
        );
        $iterator->setMaxDepth($maxDepth);

        foreach ($iterator as $file) {
            foreach ($patterns as $pattern) {
                if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                    return true;
                }
            }
        }

        return false;
    }
}
