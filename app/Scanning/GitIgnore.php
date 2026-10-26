<?php

namespace App\Scanning;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

/**
 * Asks git which files it would ignore, so findings in local-only files
 * (a gitignored .env, local credentials) are not reported as leaks.
 */
class GitIgnore
{
    /**
     * The subset of $paths (relative to the project) that git ignores and
     * that are not tracked. Tracked files are never returned: a committed
     * secret is a leak even if the file was gitignored later.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function ignoredPaths(string $projectPath, array $paths): array
    {
        $paths = array_values(array_unique(array_filter(array_map(
            static fn (string $path): string => ltrim(preg_replace('#^\./#', '', $path) ?? $path, '/'),
            $paths,
        ))));

        if ($paths === []) {
            return [];
        }

        $temporaryGitDir = null;

        if (is_dir($projectPath.'/.git')) {
            // A real repository: the index tells git which files are tracked.
            $command = ['git', 'check-ignore', '--stdin'];
        } else {
            // Uploaded or non-git folders: read the project's .gitignore files
            // through an empty repository without touching the project.
            $temporaryGitDir = sys_get_temp_dir().'/checkmycode-gitdir-'.bin2hex(random_bytes(6));
            if (! Process::run(['git', 'init', '--quiet', '--bare', $temporaryGitDir])->successful()) {
                return [];
            }
            $command = ['git', '--git-dir='.$temporaryGitDir, '--work-tree=.', 'check-ignore', '--no-index', '--stdin'];
        }

        try {
            $result = Process::path($projectPath)
                ->env(ScanEngine::processEnvironment())
                ->input(implode("\n", $paths)."\n")
                ->timeout(60)
                ->run($command);
        } finally {
            if ($temporaryGitDir !== null) {
                (new Filesystem)->deleteDirectory($temporaryGitDir);
            }
        }

        // 0 = some paths ignored, 1 = none ignored, anything else = git failed.
        if (! in_array($result->exitCode(), [0, 1], true)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $result->output()))));
    }
}
