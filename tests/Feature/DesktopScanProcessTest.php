<?php

namespace Tests\Feature;

use App\Console\Commands\ScanCommand;
use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\ScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The desktop app runs `checkmycode:scan --machine` as a NativePHP child
 * process; these tests drive the same parsing the process events go through.
 */
class DesktopScanProcessTest extends TestCase
{
    use RefreshDatabase;

    private function feed(Scan $scan, string $output, string $level = 'info'): void
    {
        $method = new ReflectionMethod(ScannerService::class, 'persistEventOutput');
        $method->invoke(null, 'scanner-'.$scan->id, $output, $level);
    }

    private function machineLine(array $payload): string
    {
        return ScanCommand::MACHINE_PREFIX.json_encode($payload);
    }

    public function test_machine_log_lines_are_stored_with_their_scanner_and_level(): void
    {
        $scan = Scan::create(['project_path' => '/tmp/app', 'status' => 'running']);

        $this->feed($scan, implode("\n", [
            $this->machineLine(['line' => 'Trivy: 3 vulnerable dependencies', 'scanner' => 'trivy', 'level' => 'info']),
            $this->machineLine(['line' => 'semgrep is not installed', 'scanner' => 'system', 'level' => 'warn']),
            'PHP Deprecated: something noisy',
        ]));

        $logs = ScanLog::where('scan_id', $scan->id)->orderBy('id')->get(['line', 'scanner', 'level'])->toArray();

        $this->assertSame([
            ['line' => 'Trivy: 3 vulnerable dependencies', 'scanner' => 'trivy', 'level' => 'info'],
            ['line' => 'semgrep is not installed', 'scanner' => 'system', 'level' => 'warn'],
            ['line' => 'PHP Deprecated: something noisy', 'scanner' => 'system', 'level' => 'info'],
        ], $logs);
    }

    public function test_a_machine_line_split_across_chunks_is_reassembled(): void
    {
        $scan = Scan::create(['project_path' => '/tmp/app', 'status' => 'running']);
        $line = $this->machineLine(['line' => 'Gitleaks: 2 potential secrets found.', 'scanner' => 'gitleaks', 'level' => 'info']);

        $this->feed($scan, substr($line, 0, 5));
        $this->feed($scan, substr($line, 5, 30));
        $this->assertSame(0, ScanLog::where('scan_id', $scan->id)->count());

        $this->feed($scan, substr($line, 35)."\n");

        $this->assertSame(['Gitleaks: 2 potential secrets found.'], ScanLog::where('scan_id', $scan->id)->pluck('line')->all());
        $this->assertSame('gitleaks', ScanLog::where('scan_id', $scan->id)->value('scanner'));
    }

    public function test_completed_process_stores_results_from_results_json(): void
    {
        $outputDir = sys_get_temp_dir().'/checkmycode-desktop-test-'.uniqid();
        mkdir($outputDir);
        $results = json_decode(file_get_contents(base_path('tests/Fixtures/laravel-results.json')), true);
        file_put_contents($outputDir.'/results.json', json_encode($results));

        $scan = Scan::create(['project_path' => '/tmp/app', 'status' => 'running', 'output_path' => $outputDir]);

        ScannerService::completeScan($scan, 0);

        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertSame($results['summary']['findings_total'], $scan->findings_count);
        $this->assertSame('laravel', $scan->results['project_type']);

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($outputDir);
    }

    public function test_failed_process_marks_the_scan_failed(): void
    {
        $scan = Scan::create(['project_path' => '/tmp/app', 'status' => 'running', 'output_path' => '/nonexistent']);

        ScannerService::completeScan($scan, 2);

        $this->assertSame('failed', $scan->refresh()->status);
        $this->assertDatabaseHas('scan_logs', ['scan_id' => $scan->id, 'line' => 'Scan failed with exit code 2.']);
    }
}
