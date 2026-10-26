<?php

namespace Tests\Unit;

use App\Scanning\Web\ArchiveExtractor;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class ArchiveExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/checkmycode-extract-'.uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function zip(array $files): string
    {
        $path = $this->dir.'/upload.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    public function test_extracts_files_and_skips_git_metadata(): void
    {
        $count = (new ArchiveExtractor(100, 1024 * 1024))->extract(
            $this->zip(['src/app.js' => 'x', '.git/HEAD' => 'ref', 'lib/.git/config' => 'x']),
            $this->dir.'/out',
        );

        $this->assertSame(1, $count);
        $this->assertFileExists($this->dir.'/out/src/app.js');
        $this->assertDirectoryDoesNotExist($this->dir.'/out/.git');
    }

    public function test_rejects_path_traversal(): void
    {
        $this->expectException(RuntimeException::class);

        (new ArchiveExtractor(100, 1024 * 1024))->extract($this->zip(['a/../../escape.txt' => 'x']), $this->dir.'/out');
    }

    public function test_enforces_uncompressed_size_limit(): void
    {
        $this->expectExceptionMessage('larger than');

        (new ArchiveExtractor(100, 1024))->extract($this->zip(['big.txt' => str_repeat('a', 5000)]), $this->dir.'/out');
    }

    public function test_enforces_file_count_limit(): void
    {
        $this->expectExceptionMessage('more than 2 files');

        (new ArchiveExtractor(2, 1024 * 1024))->extract($this->zip(['a' => '1', 'b' => '2', 'c' => '3']), $this->dir.'/out');
    }

    public function test_safe_relative_path(): void
    {
        $this->assertSame('a/b.php', ArchiveExtractor::safeRelativePath('./a//b.php'));
        $this->assertSame('a/b.php', ArchiveExtractor::safeRelativePath('a\\b.php'));
        $this->assertNull(ArchiveExtractor::safeRelativePath('/etc/passwd'));
        $this->assertNull(ArchiveExtractor::safeRelativePath('C:/x.txt'));
        $this->assertNull(ArchiveExtractor::safeRelativePath('../x'));
        $this->assertNull(ArchiveExtractor::safeRelativePath("a\0b"));
    }
}
