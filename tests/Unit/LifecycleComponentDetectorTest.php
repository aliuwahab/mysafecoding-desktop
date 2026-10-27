<?php

namespace Tests\Unit;

use App\Scanning\Lifecycle\LifecycleComponentDetector;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class LifecycleComponentDetectorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/checkmycode-lifecycle-'.uniqid();
        mkdir($this->path, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
        parent::tearDown();
    }

    private function cycles(array $files): array
    {
        foreach ($files as $relative => $contents) {
            if (! is_dir(dirname($this->path.'/'.$relative))) {
                mkdir(dirname($this->path.'/'.$relative), 0755, true);
            }
            file_put_contents($this->path.'/'.$relative, $contents);
        }

        return array_column((new LifecycleComponentDetector)->forRepository($this->path), 'detected_cycle', 'product');
    }

    public function test_maven_java_and_spring_boot(): void
    {
        $this->assertSame(['eclipse-temurin' => '17', 'spring-boot' => '3.2'], $this->cycles(['pom.xml' => <<<'XML'
            <project>
              <parent>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-starter-parent</artifactId>
                <version>3.2.5</version>
              </parent>
              <properties><java.version>17</java.version></properties>
            </project>
            XML]));
    }

    public function test_gradle_kotlin_dsl_and_legacy_java_version(): void
    {
        $this->assertSame(['eclipse-temurin' => '8', 'spring-boot' => '2.7'], $this->cycles(['build.gradle.kts' => <<<'KTS'
            plugins { id("org.springframework.boot") version "2.7.18" }
            java { sourceCompatibility = JavaVersion.VERSION_1_8 }
            KTS]));
    }

    public function test_dotnet_target_framework(): void
    {
        $this->assertSame(['dotnet' => '8'], $this->cycles(['src/Api/Api.csproj' => '<Project><PropertyGroup><TargetFramework>net8.0</TargetFramework></PropertyGroup></Project>']));
    }

    public function test_legacy_dotnet_core_cycle_keeps_the_minor(): void
    {
        $this->assertSame(['dotnet' => '3.1'], $this->cycles(['Api.csproj' => '<TargetFramework>netcoreapp3.1</TargetFramework>']));
    }
}
