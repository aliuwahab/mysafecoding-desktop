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
        $isEnvFile = str_starts_with(basename($filePath), '.env');
        $snippet = [];

        for ($i = $start; $i < $end; $i++) {
            $line = rtrim($lines[$i]);
            // Never copy secrets from .env files into scan results.
            if ($isEnvFile) {
                $line = preg_replace('/^(\s*[A-Z0-9_]*(?:KEY|SECRET|PASSWORD|TOKEN|PASS|CREDENTIALS?|DSN)[A-Z0-9_]*\s*=\s*)\S.*$/i', '$1********', $line);
            }
            $snippet[] = ($i + 1).': '.$line;
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

    /**
     * Source of the Form Request classes type-hinted in a controller's methods
     * (Laravel puts validation and per-request authorization there).
     *
     * @return array<string, string> class name => file contents
     */
    protected function formRequestsUsedBy(string $controllerContent, string $projectPath): array
    {
        preg_match_all('/public\s+function\s+\w+\s*\(([^)]*)\)/', $controllerContent, $signatures);
        preg_match_all('/\b([A-Z]\w*Request)\s+\$\w+/', implode(',', $signatures[1] ?? []), $types);

        $requests = [];
        foreach (array_unique($types[1] ?? []) as $class) {
            if ($class === 'Request') {
                continue;
            }
            foreach ($this->findPhpFiles($projectPath, 'app') as $file) {
                if (basename($file) !== $class.'.php') {
                    continue;
                }
                $content = $this->readFile($file);
                if ($content !== null && preg_match('/class\s+'.$class.'\s+extends\s+\w*Request\b/', $content)) {
                    $requests[$class] = $content;
                    break;
                }
            }
        }

        return $requests;
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
