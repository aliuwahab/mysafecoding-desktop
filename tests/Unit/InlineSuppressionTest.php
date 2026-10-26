<?php

namespace Tests\Unit;

use App\Scanning\ScanEngine;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Tests\TestCase;

class InlineSuppressionTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/checkmycode-suppress-'.uniqid();
        mkdir($this->path.'/resources/views', 0755, true);
        file_put_contents($this->path.'/resources/views/mail.blade.php', implode("\n", [
            '<td>',                                                                                  // 1
            '{{-- checkmycode-ignore[laravel.blade.raw-output]: sanitized by Filament --}}',         // 2
            '{!! $bodyHtml !!}',                                                                     // 3
            '{!! $footer !!} {{-- checkmycode-ignore[laravel.debug-mode]: other rule --}}',          // 4
            '{!! $any !!} {{-- checkmycode-ignore --}}',                                             // 5
            '{!! $unreviewed !!}',                                                                   // 6
        ]));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
        parent::tearDown();
    }

    public function test_only_matching_suppressions_hide_findings_and_they_are_recorded(): void
    {
        $finding = fn (int $line) => ['rule_id' => 'laravel.blade.raw-output', 'severity' => 'MEDIUM', 'file_path' => 'resources/views/mail.blade.php', 'line_start' => $line];
        $results = ['laravel_rules' => ['findings' => [$finding(3), $finding(4), $finding(5), $finding(6)], 'summary' => ['total' => 4, 'MEDIUM' => 4]], 'summary' => []];

        $engine = new ScanEngine;
        $reflection = new ReflectionClass($engine);
        $reflection->getProperty('projectPath')->setValue($engine, $this->path);
        $reflection->getProperty('logger')->setValue($engine, static function (): void {});
        $results = $reflection->getMethod('applyInlineSuppressions')->invoke($engine, $results);

        $this->assertSame([4, 6], array_column($results['laravel_rules']['findings'], 'line_start'));
        $this->assertSame(2, $results['summary']['laravel_rules_total']);
        $this->assertSame([3, 5], array_column($results['suppressed'], 'line'));
        $this->assertSame('sanitized by Filament', $results['suppressed'][0]['reason']);
    }
}
