<?php

namespace App\Scanning\Rules;

use App\Scanning\Rules\Packs\RulePack;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Regex-based, cross-language rule pack runner.
 *
 * The rules live in one RulePack per language family (see Rules/Packs). The
 * project is walked once and each file is checked against every pack that
 * applies to the detected stack and handles the file's extension.
 *
 * Laravel projects are covered by the dedicated LaravelRuleEngine plus the
 * custom Semgrep rules, so only the universal rules run for them here.
 */
class PatternRuleScanner
{
    protected const SKIP_DIRS = ['.git', 'vendor', 'node_modules', 'storage', 'dist', 'build', 'nativephp', '.cache', '__pycache__', '.venv', 'venv', 'bootstrap/cache', 'target', '.next', '.nuxt', '.svelte-kit', 'coverage', '_build', 'deps', 'bin', 'obj', 'public/build', '.gradle'];

    protected const PACKS = [
        Packs\UniversalPack::class,
        Packs\PhpPack::class,
        Packs\PythonPack::class,
        Packs\JavaScriptPack::class,
        Packs\RubyPack::class,
        Packs\GoPack::class,
        Packs\JavaPack::class,
        Packs\RustPack::class,
        Packs\DotNetPack::class,
        Packs\ElixirPack::class,
    ];

    /** @var list<string> */
    protected array $exclusions = [];

    /**
     * @param  list<string>  $exclusions
     * @return array{findings: array<int, array<string, mixed>>, summary: array{total:int, CRITICAL:int, HIGH:int, MEDIUM:int, LOW:int}}
     */
    public function scan(string $repositoryPath, string $language, array $exclusions = []): array
    {
        $repositoryPath = rtrim($repositoryPath, '/');
        $this->exclusions = array_values(array_filter(array_map(fn (string $e): string => trim($e, '/'), $exclusions)));

        $rulesByExtension = $this->rulesByExtension($language);
        $findings = [];

        foreach ($this->filteredFiles($repositoryPath, self::SKIP_DIRS, array_keys($rulesByExtension)) as $file) {
            $rules = $rulesByExtension[strtolower($file->getExtension())] ?? [];
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $rules = array_filter($rules, fn (array $rule): bool => $this->appliesToPath($rule, $relativePath));

            if ($rules === []) {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            array_push($findings, ...$this->checkFile($rules, $relativePath, $contents));
        }

        usort($findings, function (array $left, array $right): int {
            $severity = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3, 'INFO' => 4];

            return [$severity[$left['severity']] ?? 99, $left['file_path'], $left['line_start']]
                <=> [$severity[$right['severity']] ?? 99, $right['file_path'], $right['line_start']];
        });

        $summary = ['total' => count($findings), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        foreach ($findings as $finding) {
            if (isset($summary[$finding['severity']])) {
                $summary[$finding['severity']]++;
            }
        }

        return ['findings' => array_values($findings), 'summary' => $summary];
    }

    /**
     * Rules that apply to the project type, indexed by the file extensions they check.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function rulesByExtension(string $language): array
    {
        $index = [];

        foreach (self::PACKS as $class) {
            /** @var RulePack $pack */
            $pack = new $class;

            if (! $pack->appliesTo($language)) {
                continue;
            }

            foreach ($pack->rules() as $rule) {
                if (isset($rule['frameworks']) && ! in_array($language, $rule['frameworks'], true)) {
                    continue;
                }

                foreach ($rule['extensions'] ?? $pack->extensions() as $extension) {
                    $index[$extension][] = $rule;
                }
            }
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function appliesToPath(array $rule, string $relativePath): bool
    {
        if (preg_match(RulePack::GENERATED_PATHS, $relativePath)) {
            return false;
        }

        if (empty($rule['include_tests']) && preg_match(RulePack::TEST_PATHS, $relativePath)) {
            return false;
        }

        if (isset($rule['paths']) && ! preg_match($rule['paths'], $relativePath)) {
            return false;
        }

        return ! (isset($rule['skip']) && preg_match($rule['skip'], $relativePath));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    protected function checkFile(array $rules, string $relativePath, string $contents): array
    {
        $rules = array_filter($rules, static fn (array $rule): bool => (! isset($rule['when_file']) || preg_match($rule['when_file'], $contents))
            && ! (isset($rule['unless_file']) && preg_match($rule['unless_file'], $contents)));

        if ($rules === []) {
            return [];
        }

        $lines = preg_split('/\R/', $contents);
        $findings = [];

        foreach ($lines as $index => $line) {
            if (preg_match(RulePack::COMMENT_LINE, $line)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (! preg_match($rule['pattern'], $line)) {
                    continue;
                }

                if (isset($rule['unless']) && preg_match($rule['unless'], $line)) {
                    continue;
                }

                if (isset($rule['unless_nearby'])) {
                    $window = $rule['window'] ?? 10;
                    $context = implode("\n", array_slice($lines, max(0, $index - $window), $window * 2 + 1));

                    if (preg_match($rule['unless_nearby'], $context)) {
                        continue;
                    }
                }

                $findings[] = $this->makeFinding(
                    ruleId: $rule['rule_id'],
                    title: $rule['title'],
                    severity: $rule['severity'],
                    category: $rule['category'],
                    relativePath: $relativePath,
                    lineNumber: $index + 1,
                    match: trim($line),
                    why: $rule['why'] ?? null,
                    fix: $rule['fix'] ?? null,
                );
            }
        }

        return $findings;
    }

    // -------------------------------------------------------------------------
    // File iterators
    // -------------------------------------------------------------------------

    /**
     * @param  list<string>  $skipDirs
     * @param  list<string>  $extensions
     * @return iterable<SplFileInfo>
     */
    protected function filteredFiles(string $repositoryPath, array $skipDirs, array $extensions): iterable
    {
        $skip = array_merge($skipDirs, $this->exclusions);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($repositoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
                function (SplFileInfo $file) use ($repositoryPath, $skip): bool {
                    if ($file->isLink()) {
                        return false;
                    }
                    $relative = $this->relativePath($repositoryPath, $file->getPathname());

                    foreach ($skip as $dir) {
                        if ($relative === $dir || str_starts_with($relative, $dir.'/') || ($file->isDir() && $file->getFilename() === $dir)) {
                            return false;
                        }
                    }

                    return true;
                },
            ),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($file->getSize() > 1024 * 1024) {
                continue;
            }

            if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            yield $file;
        }
    }

    protected function relativePath(string $repositoryPath, string $path): string
    {
        return ltrim(str_replace($repositoryPath, '', $path), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeFinding(
        string $ruleId,
        string $title,
        string $severity,
        string $category,
        string $relativePath,
        int $lineNumber,
        string $match,
        ?string $why = null,
        ?string $fix = null,
    ): array {
        return [
            'rule_id' => $ruleId,
            'title' => $title,
            'severity' => strtoupper($severity),
            'category' => $category,
            'description' => $why ?? $title,
            'file_path' => $relativePath,
            'line_start' => $lineNumber,
            'code_snippet' => mb_strimwidth($match, 0, 300, '…'),
            'fix_guidance' => $fix,
            'fingerprint' => sha1($ruleId.'|'.$relativePath.'|'.$lineNumber.'|'.$match),
        ];
    }
}
