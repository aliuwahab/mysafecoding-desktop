<?php

namespace App\Services\Scanners\Rules;

use App\Services\Scanners\Contracts\RuleInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class BaseRule implements RuleInterface
{
    protected function findPhpFiles(string $projectPath, string $subDir = 'app'): array
    {
        $dir = $projectPath.'/'.$subDir;
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function findBladeFiles(string $projectPath): array
    {
        $dir = $projectPath.'/resources/views';
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function relativePath(string $filePath, string $projectPath): string
    {
        return ltrim(str_replace($projectPath, '', $filePath), '/');
    }

    protected function extractSnippet(string $filePath, int $lineNumber, int $context = 2): string
    {
        $lines = @file($filePath);
        if (! $lines) {
            return '';
        }

        $start = max(0, $lineNumber - 1 - $context);
        $end = min(count($lines), $lineNumber + $context);
        $snippet = [];

        for ($i = $start; $i < $end; $i++) {
            $snippet[] = ($i + 1).': '.rtrim($lines[$i]);
        }

        return implode("\n", $snippet);
    }

    protected function makeFinding(
        string $filePath,
        string $projectPath,
        int $line,
        string $description,
        string $fixGuidance,
        ?string $snippet = null,
    ): array {
        return [
            'rule_id' => $this->id(),
            'title' => $this->title(),
            'description' => $description,
            'severity' => $this->severity(),
            'category' => $this->category(),
            'file_path' => $this->relativePath($filePath, $projectPath),
            'line_start' => $line,
            'code_snippet' => $snippet ?? $this->extractSnippet($filePath, $line),
            'fix_guidance' => $fixGuidance,
        ];
    }

    protected function readFile(string $path): ?string
    {
        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        return @file_get_contents($path);
    }

    protected function readLines(string $path): ?array
    {
        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        return @file($path, FILE_IGNORE_NEW_LINES);
    }
}
