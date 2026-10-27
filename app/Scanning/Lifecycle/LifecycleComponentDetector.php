<?php

namespace App\Scanning\Lifecycle;

class LifecycleComponentDetector
{
    /**
     * @return array<int, array{
     *   product:string,
     *   label:string,
     *   kind:string,
     *   detected_version:string,
     *   detected_cycle:string,
     *   source:string
     * }>
     */
    public function forRepository(string $repositoryPath): array
    {
        return collect([
            ...$this->composerComponents($repositoryPath),
            ...$this->pythonComponents($repositoryPath),
            ...$this->nodeComponents($repositoryPath),
            ...$this->elixirComponents($repositoryPath),
            ...$this->rubyComponents($repositoryPath),
            ...$this->goComponents($repositoryPath),
            ...$this->javaComponents($repositoryPath),
            ...$this->dotnetComponents($repositoryPath),
        ])
            ->filter()
            ->unique(fn (array $component): string => $component['product'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function composerComponents(string $repositoryPath): array
    {
        $composerPath = $repositoryPath.'/composer.json';

        if (! file_exists($composerPath)) {
            return [];
        }

        $composer = $this->decodeJsonFile($composerPath);

        if (! is_array($composer)) {
            return [];
        }

        $lockPackages = collect([
            ...(array) data_get($this->decodeJsonFile($repositoryPath.'/composer.lock'), 'packages', []),
            ...(array) data_get($this->decodeJsonFile($repositoryPath.'/composer.lock'), 'packages-dev', []),
        ])->pluck('version', 'name');

        return array_values(array_filter([
            $this->makeComponent(
                product: 'laravel',
                label: 'Laravel',
                kind: 'framework',
                version: $this->firstFilledString(
                    $lockPackages->get('laravel/framework'),
                    data_get($composer, 'require.laravel/framework'),
                    data_get($composer, 'require-dev.laravel/framework'),
                ),
                source: file_exists($repositoryPath.'/composer.lock') ? 'composer.lock' : 'composer.json',
            ),
            $this->makeComponent(
                product: 'symfony',
                label: 'Symfony',
                kind: 'framework',
                version: $this->firstFilledString(
                    $lockPackages->get('symfony/framework-bundle'),
                    data_get($composer, 'require.symfony/framework-bundle'),
                    data_get($composer, 'require-dev.symfony/framework-bundle'),
                ),
                source: file_exists($repositoryPath.'/composer.lock') ? 'composer.lock' : 'composer.json',
            ),
            $this->makeComponent(
                product: 'api-platform',
                label: 'API Platform',
                kind: 'framework',
                version: $this->firstFilledString(
                    $lockPackages->get('api-platform/core'),
                    data_get($composer, 'require.api-platform/core'),
                    data_get($composer, 'require-dev.api-platform/core'),
                ),
                source: file_exists($repositoryPath.'/composer.lock') ? 'composer.lock' : 'composer.json',
            ),
            $this->makeComponent(
                product: 'php',
                label: 'PHP',
                kind: 'runtime',
                version: $this->firstFilledString(
                    data_get($composer, 'require.php'),
                    data_get($composer, 'require-dev.php'),
                ),
                source: 'composer.json',
            ),
        ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function pythonComponents(string $repositoryPath): array
    {
        $runtimeVersion = $this->firstFilledString(
            $this->firstLine($repositoryPath.'/.python-version'),
            $this->normalizeRuntimeTxt($this->firstLine($repositoryPath.'/runtime.txt')),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/pyproject.toml'), '/requires-python\s*=\s*["\']([^"\']+)["\']/i'),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/Pipfile'), '/python_version\s*=\s*["\']([^"\']+)["\']/i'),
        );

        $djangoVersion = $this->firstFilledString(
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/poetry.lock'), '/name\s*=\s*"django"[\s\S]*?version\s*=\s*"([^"]+)"/i'),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/requirements.txt'), '/^\s*django(?:\[.*?\])?\s*(?:[=><!~^]+)\s*v?([0-9][0-9A-Za-z.\-]*)/im'),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/pyproject.toml'), '/django\s*=\s*["\']([^"\']+)["\']/i'),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/Pipfile'), '/django\s*=\s*["\']([^"\']+)["\']/i'),
        );

        return array_values(array_filter([
            $this->makeComponent(
                product: 'python',
                label: 'Python',
                kind: 'runtime',
                version: $runtimeVersion,
                source: $this->firstExistingPath($repositoryPath, [
                    '.python-version',
                    'runtime.txt',
                    'pyproject.toml',
                    'Pipfile',
                ]) ?? 'pyproject.toml',
            ),
            $this->makeComponent(
                product: 'django',
                label: 'Django',
                kind: 'framework',
                version: $djangoVersion,
                source: $this->firstExistingPath($repositoryPath, [
                    'poetry.lock',
                    'requirements.txt',
                    'pyproject.toml',
                    'Pipfile',
                ]) ?? 'requirements.txt',
            ),
        ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function nodeComponents(string $repositoryPath): array
    {
        $package = $this->decodeJsonFile($repositoryPath.'/package.json');

        if (! is_array($package) && ! file_exists($repositoryPath.'/.nvmrc') && ! file_exists($repositoryPath.'/.node-version')) {
            return [];
        }

        $packageLock = $this->decodeJsonFile($repositoryPath.'/package-lock.json');

        $nodeVersion = $this->firstFilledString(
            $this->firstLine($repositoryPath.'/.nvmrc'),
            $this->firstLine($repositoryPath.'/.node-version'),
            data_get($package, 'volta.node'),
            data_get($package, 'engines.node'),
        );

        return array_values(array_filter([
            $this->makeComponent(
                product: 'nodejs',
                label: 'Node.js',
                kind: 'runtime',
                version: $nodeVersion,
                source: file_exists($repositoryPath.'/.nvmrc')
                    ? '.nvmrc'
                    : (file_exists($repositoryPath.'/.node-version') ? '.node-version' : 'package.json'),
            ),
            $this->makeComponent(
                product: 'express',
                label: 'Express',
                kind: 'framework',
                version: $this->firstFilledString(
                    $this->nodePackageVersion($packageLock, 'express'),
                    data_get($package, 'dependencies.express'),
                    data_get($package, 'devDependencies.express'),
                ),
                source: file_exists($repositoryPath.'/package-lock.json') ? 'package-lock.json' : 'package.json',
            ),
            $this->makeComponent(
                product: 'nextjs',
                label: 'Next.js',
                kind: 'framework',
                version: $this->firstFilledString(
                    $this->nodePackageVersion($packageLock, 'next'),
                    data_get($package, 'dependencies.next'),
                    data_get($package, 'devDependencies.next'),
                ),
                source: file_exists($repositoryPath.'/package-lock.json') ? 'package-lock.json' : 'package.json',
            ),
            $this->makeComponent(
                product: 'nuxt',
                label: 'Nuxt',
                kind: 'framework',
                version: $this->firstFilledString(
                    $this->nodePackageVersion($packageLock, 'nuxt'),
                    data_get($package, 'dependencies.nuxt'),
                    data_get($package, 'devDependencies.nuxt'),
                ),
                source: file_exists($repositoryPath.'/package-lock.json') ? 'package-lock.json' : 'package.json',
            ),
            $this->makeComponent(
                product: 'angular',
                label: 'Angular',
                kind: 'framework',
                version: $this->firstFilledString(
                    $this->nodePackageVersion($packageLock, '@angular/core'),
                    data_get($package, 'dependencies.@angular/core'),
                    data_get($package, 'devDependencies.@angular/core'),
                ),
                source: file_exists($repositoryPath.'/package-lock.json') ? 'package-lock.json' : 'package.json',
            ),
        ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function elixirComponents(string $repositoryPath): array
    {
        $mixExs = $this->fileContents($repositoryPath.'/mix.exs');
        $mixLock = $this->fileContents($repositoryPath.'/mix.lock');
        $toolVersions = $this->fileContents($repositoryPath.'/.tool-versions');

        if ($mixExs === null && $mixLock === null && $toolVersions === null) {
            return [];
        }

        $erlangVersion = $this->firstFilledString(
            $this->extractVersionFromText($toolVersions, '/^\s*erlang\s+([0-9][0-9A-Za-z.\-_+]*)/mi'),
            $this->extractVersionFromText($mixExs, '/elixir:\s*["\'][^"\']*otp-([0-9]+(?:\.[0-9]+){0,2})[^"\']*["\']/i'),
        );

        $phoenixVersion = $this->firstFilledString(
            $this->extractVersionFromText($mixLock, '/\{:\s*phoenix\s*,\s*\{:hex,\s*:phoenix,\s*"([^"]+)"/i'),
            $this->extractVersionFromText($mixExs, '/\{:\s*phoenix\s*,\s*"([^"]+)"/i'),
        );

        return array_values(array_filter([
            $this->makeComponent(
                product: 'erlang',
                label: 'Erlang / OTP',
                kind: 'runtime',
                version: $erlangVersion,
                source: file_exists($repositoryPath.'/.tool-versions') ? '.tool-versions' : 'mix.exs',
            ),
            $this->makeComponent(
                product: 'phoenix-framework',
                label: 'Phoenix Framework',
                kind: 'framework',
                version: $phoenixVersion,
                source: file_exists($repositoryPath.'/mix.lock') ? 'mix.lock' : 'mix.exs',
            ),
        ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function rubyComponents(string $repositoryPath): array
    {
        $rubyVersion = $this->firstFilledString(
            $this->firstLine($repositoryPath.'/.ruby-version'),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/Gemfile'), '/^\s*ruby\s+["\']([^"\']+)["\']/mi'),
        );

        $railsVersion = $this->firstFilledString(
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/Gemfile.lock'), '/^\s{4}rails \(([^)]+)\)/m'),
            $this->extractVersionFromText($this->fileContents($repositoryPath.'/Gemfile'), '/gem\s+["\']rails["\']\s*,\s*["\']([^"\']+)["\']/i'),
        );

        return array_values(array_filter([
            $this->makeComponent(
                product: 'ruby',
                label: 'Ruby',
                kind: 'runtime',
                version: $rubyVersion,
                source: file_exists($repositoryPath.'/.ruby-version') ? '.ruby-version' : 'Gemfile',
            ),
            $this->makeComponent(
                product: 'rails',
                label: 'Ruby on Rails',
                kind: 'framework',
                version: $railsVersion,
                source: file_exists($repositoryPath.'/Gemfile.lock') ? 'Gemfile.lock' : 'Gemfile',
            ),
        ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function goComponents(string $repositoryPath): array
    {
        $goVersion = $this->extractVersionFromText($this->fileContents($repositoryPath.'/go.mod'), '/^\s*go\s+([0-9]+(?:\.[0-9]+){0,2})/m');

        return array_values(array_filter([
            $this->makeComponent(
                product: 'go',
                label: 'Go',
                kind: 'runtime',
                version: $goVersion,
                source: 'go.mod',
            ),
        ]));
    }

    /**
     * Java runtime and Spring versions from Maven or Gradle build files.
     *
     * @return array<int, array<string, string>>
     */
    protected function javaComponents(string $repositoryPath): array
    {
        $source = $this->firstExistingPath($repositoryPath, ['pom.xml', 'build.gradle.kts', 'build.gradle']);

        if ($source === null) {
            return [];
        }

        $build = $this->fileContents($repositoryPath.'/'.$source);

        $javaVersion = $this->firstFilledString(
            $this->extractVersionFromText($build, '/<(?:java\.version|maven\.compiler\.release|maven\.compiler\.target|maven\.compiler\.source)>\s*([0-9.]+)\s*</'),
            $this->extractVersionFromText($build, '/JavaLanguageVersion\.of\(\s*([0-9]+)\s*\)/'),
            $this->extractVersionFromText($build, '/(?:sourceCompatibility|targetCompatibility|jvmTarget)\s*=\s*(?:JavaVersion\.VERSION_|["\']?)([0-9._]+)/'),
            $this->firstLine($repositoryPath.'/.java-version'),
        );

        // Java 8 and older are written 1.8, 1.7, ...
        if ($javaVersion !== null && preg_match('/^1[._]([0-9]+)/', $javaVersion, $legacy)) {
            $javaVersion = $legacy[1];
        }

        $springBootVersion = $this->firstFilledString(
            $this->extractVersionFromText($build, '/<artifactId>spring-boot-starter-parent<\/artifactId>\s*<version>([^<]+)<\/version>/'),
            $this->extractVersionFromText($build, '/(?:id\s*\(?\s*["\']org\.springframework\.boot["\']\s*\)?\s*version|org\.springframework\.boot:spring-boot-gradle-plugin:)\s*["\']?([0-9][^"\'\s)]*)/'),
        );

        return array_values(array_filter([
            $this->makeComponent(
                product: 'eclipse-temurin',
                label: 'Java',
                kind: 'runtime',
                version: $javaVersion,
                source: $source,
            ),
            $this->makeComponent(
                product: 'spring-boot',
                label: 'Spring Boot',
                kind: 'framework',
                version: $springBootVersion,
                source: $source,
            ),
        ]));
    }

    /**
     * .NET target framework from the first project file (or global.json's SDK).
     *
     * @return array<int, array<string, string>>
     */
    protected function dotnetComponents(string $repositoryPath): array
    {
        $project = $this->firstProjectFile($repositoryPath);
        $version = $this->extractVersionFromText($project !== null ? $this->fileContents($project) : null, '/<TargetFrameworks?>\s*(?:net|netcoreapp)([0-9]+\.[0-9]+)/');
        $source = $project !== null ? ltrim(substr($project, strlen($repositoryPath)), '/') : 'global.json';

        if ($version === null) {
            $version = data_get($this->decodeJsonFile($repositoryPath.'/global.json'), 'sdk.version');
            $source = 'global.json';
        }

        return array_values(array_filter([
            $this->makeComponent(
                product: 'dotnet',
                label: '.NET',
                kind: 'runtime',
                version: is_string($version) ? $version : null,
                source: $source,
            ),
        ]));
    }

    protected function firstProjectFile(string $repositoryPath): ?string
    {
        $candidates = [
            ...glob($repositoryPath.'/*.csproj') ?: [],
            ...glob($repositoryPath.'/*/*.csproj') ?: [],
            ...glob($repositoryPath.'/*/*/*.csproj') ?: [],
            ...glob($repositoryPath.'/*.fsproj') ?: [],
            ...glob($repositoryPath.'/*/*.fsproj') ?: [],
        ];

        return $candidates[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonFile(string $path): ?array
    {
        if (! file_exists($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function fileContents(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return is_string($contents) ? $contents : null;
    }

    protected function firstLine(string $path): ?string
    {
        $contents = $this->fileContents($path);

        if ($contents === null) {
            return null;
        }

        $line = strtok($contents, "\n");

        return is_string($line) ? trim($line) : null;
    }

    protected function normalizeRuntimeTxt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (str_starts_with(strtolower($value), 'python-')) {
            return substr($value, 7);
        }

        return $value;
    }

    protected function extractVersionFromText(?string $contents, string $pattern): ?string
    {
        if (! is_string($contents) || $contents === '') {
            return null;
        }

        if (! preg_match($pattern, $contents, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    protected function nodePackageVersion(?array $packageLock, string $packageName): ?string
    {
        if (! is_array($packageLock)) {
            return null;
        }

        $packages = $packageLock['packages'] ?? null;
        $packageEntry = is_array($packages)
            ? ($packages['node_modules/'.$packageName] ?? null)
            : null;

        return $this->firstFilledString(
            is_array($packageEntry) ? ($packageEntry['version'] ?? null) : null,
            data_get($packageLock, 'dependencies.'.$packageName.'.version'),
        );
    }

    protected function firstFilledString(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $paths
     */
    protected function firstExistingPath(string $repositoryPath, array $paths): ?string
    {
        foreach ($paths as $path) {
            if (file_exists($repositoryPath.'/'.$path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    protected function makeComponent(string $product, string $label, string $kind, ?string $version, string $source): ?array
    {
        $normalizedVersion = $this->normalizeVersion($version);
        $cycle = $this->resolveCycle($product, $normalizedVersion);

        if ($normalizedVersion === null || $cycle === null) {
            return null;
        }

        return [
            'product' => $product,
            'label' => $label,
            'kind' => $kind,
            'detected_version' => $normalizedVersion,
            'detected_cycle' => $cycle,
            'source' => $source,
        ];
    }

    protected function normalizeVersion(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (! preg_match('/v?([0-9]+(?:\.[0-9]+){0,2})/i', $value, $matches)) {
            return null;
        }

        return $matches[1];
    }

    protected function resolveCycle(string $product, ?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        // .NET cycles are "10", "8", "6", "5" but "3.1" and "2.1" before .NET 5.
        if ($product === 'dotnet') {
            return (int) $version >= 5 ? (string) (int) $version : implode('.', array_slice(explode('.', $version), 0, 2));
        }

        $precision = match ($product) {
            'laravel', 'nodejs', 'express', 'nextjs', 'nuxt', 'angular', 'erlang', 'eclipse-temurin' => 1,
            default => 2,
        };

        $segments = explode('.', $version);

        return implode('.', array_slice($segments, 0, min($precision, count($segments))));
    }
}
