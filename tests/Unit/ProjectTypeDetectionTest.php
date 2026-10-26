<?php

namespace Tests\Unit;

use App\Scanning\ProjectDetector;
use PHPUnit\Framework\TestCase;

class ProjectTypeDetectionTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = sys_get_temp_dir().'/checkmycode-project-type-'.uniqid();
        mkdir($this->fixturePath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturePath);
        parent::tearDown();
    }

    public function test_detects_laravel_project(): void
    {
        $this->createFile('artisan', '');
        $this->createJson('composer.json', ['require' => ['laravel/framework' => '^12.0']]);

        $this->assertSame('laravel', $this->detect());
    }

    public function test_detects_django_from_manage_py(): void
    {
        $this->createFile('requirements.txt', "requests==2.31.0\n");
        $this->createFile('manage.py', '');

        $this->assertSame('django', $this->detect());
    }

    public function test_detects_language_of_a_single_file(): void
    {
        $this->createFile('handler.py', 'print(1)');

        $this->assertSame('python', ProjectDetector::detect($this->fixturePath.'/handler.py'));
    }

    public function test_detects_nextjs_project(): void
    {
        $this->createJson('package.json', ['dependencies' => ['next' => '^15.0', 'react' => '^19.0']]);

        $this->assertSame('nextjs', $this->detect());
    }

    public function test_detects_django_project(): void
    {
        $this->createFile('requirements.txt', "django==5.1\npsycopg[binary]\n");

        $this->assertSame('django', $this->detect());
    }

    public function test_detects_rails_project(): void
    {
        $this->createFile('Gemfile', "source 'https://rubygems.org'\ngem 'rails', '~> 8.0'\n");

        $this->assertSame('rails', $this->detect());
    }

    public function test_detects_spring_project(): void
    {
        $this->createFile('pom.xml', '<project><dependency><groupId>org.springframework.boot</groupId></dependency></project>');

        $this->assertSame('spring', $this->detect());
    }

    public function test_detects_dotnet_project(): void
    {
        $this->createFile('src/App/App.csproj', '<Project Sdk="Microsoft.NET.Sdk.Web"></Project>');

        $this->assertSame('dotnet', $this->detect());
    }

    private function detect(): string
    {
        return ProjectDetector::detect($this->fixturePath);
    }

    private function createJson(string $relativePath, array $data): void
    {
        $this->createFile($relativePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function createFile(string $relativePath, string $content): void
    {
        $fullPath = $this->fixturePath.'/'.$relativePath;
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
