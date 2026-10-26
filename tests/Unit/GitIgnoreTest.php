<?php

namespace Tests\Unit;

use App\Scanning\GitIgnore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GitIgnoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/checkmycode-gitignore-'.uniqid();
        mkdir($this->path.'/config', 0755, true);
        mkdir($this->path.'/packages/api', 0755, true);
        file_put_contents($this->path.'/.gitignore', ".env*\n!.env.example\nconfig/local-*.json\n");
        file_put_contents($this->path.'/packages/api/.gitignore', "secrets.yml\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
        parent::tearDown();
    }

    public function test_reads_gitignore_rules_in_a_folder_without_git(): void
    {
        $ignored = GitIgnore::ignoredPaths($this->path, [
            '.env', '.env.production', '.env.example', 'config/local-keys.json', 'config/app.php',
            'packages/api/secrets.yml', 'src/app.js', './.env',
        ]);

        sort($ignored);
        $this->assertSame(['.env', '.env.production', 'config/local-keys.json', 'packages/api/secrets.yml'], $ignored);
        $this->assertDirectoryDoesNotExist($this->path.'/.git');
    }

    public function test_committed_files_are_never_treated_as_ignored(): void
    {
        file_put_contents($this->path.'/.env', 'KEY=committed-before-ignore');
        $git = fn (array $args) => Process::path($this->path)->run(['git', '-c', 'user.email=t@example.com', '-c', 'user.name=t', ...$args]);
        $git(['init', '-q']);
        $git(['add', '-f', '.env', '.gitignore']);
        $git(['commit', '-qm', 'leak']);

        $this->assertSame(['config/local-keys.json'], GitIgnore::ignoredPaths($this->path, ['.env', 'config/local-keys.json']));
    }

    public function test_no_gitignore_means_nothing_is_ignored(): void
    {
        unlink($this->path.'/.gitignore');

        $this->assertSame([], GitIgnore::ignoredPaths($this->path, ['.env', 'src/app.js']));
    }
}
