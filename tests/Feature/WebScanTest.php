<?php

namespace Tests\Feature;

use App\Jobs\RunWebScan;
use App\Livewire\Scanner;
use App\Scanning\ScanEngine;
use App\Scanning\Store\EphemeralScanStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class WebScanTest extends TestCase
{
    use RefreshDatabase;

    private string $workspaceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceRoot = sys_get_temp_dir().'/checkmycode-web-test-'.uniqid();
        config([
            'checkmycode.mode' => 'web',
            'checkmycode.web.workspace_path' => $this->workspaceRoot,
        ]);
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->workspaceRoot);
        parent::tearDown();
    }

    private function zip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'msc').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return new UploadedFile($path, 'project.zip', 'application/zip', null, true);
    }

    public function test_upload_is_extracted_queued_and_owned_by_the_session(): void
    {
        Queue::fake();

        $response = $this->post('/scans', [
            'archive' => $this->zip(['composer.json' => '{}', 'app/User.php' => '<?php', '.git/config' => '[core]']),
            'name' => 'my-app',
            'exclusions' => ['vendor'],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $id = $response->json('id');

        $this->assertSame('pending', EphemeralScanStore::raw($id)['status']);
        $this->assertContains($id, session('web_scans'));

        Queue::assertPushed(RunWebScan::class, function (RunWebScan $job) use ($id) {
            return $job->scanId === $id
                && is_file($job->workspace.'/src/app/User.php')
                && ! is_dir($job->workspace.'/src/.git');
        });
    }

    public function test_upload_route_is_not_available_on_desktop(): void
    {
        config(['checkmycode.mode' => 'desktop']);

        $this->post('/scans', ['archive' => $this->zip(['a.php' => '<?php']), 'name' => 'x'], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_path_traversal_archives_are_rejected(): void
    {
        Queue::fake();

        $this->post('/scans', [
            'archive' => $this->zip(['../../evil.php' => '<?php']),
            'name' => 'evil',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'unsafe path'));

        Queue::assertNothingPushed();
    }

    public function test_job_stores_results_and_deletes_the_uploaded_code(): void
    {
        $id = EphemeralScanStore::create('my-app', []);
        $workspace = $this->workspaceRoot.'/job';
        mkdir($workspace.'/src', 0700, true);
        file_put_contents($workspace.'/src/index.php', '<?php echo 1;');

        $engine = $this->mock(ScanEngine::class);
        $engine->shouldReceive('run')->once()->andReturnUsing(function (string $path, string $out, array $exclusions, callable $log, array $options) {
            $this->assertFalse($options['git_history']);
            $this->assertTrue($options['redact_secrets']);
            $this->assertFalse($options['respect_gitignore']);
            $log('Semgrep: 0 findings.', 'semgrep', 'info');

            return ['project_type' => 'php', 'summary' => ['findings_total' => 3, 'grade' => 'C']];
        });

        (new RunWebScan($id, $workspace, []))->handle($engine, new \Illuminate\Filesystem\Filesystem);

        $scan = EphemeralScanStore::raw($id);
        $this->assertSame('completed', $scan['status']);
        $this->assertSame(3, $scan['findings_count']);
        $this->assertDirectoryDoesNotExist($workspace);
        $this->assertNotNull($scan = EphemeralScanStore::raw($id)['code_deleted_at']);
        $this->assertSame(['today' => 1, 'total' => 1], \App\Scanning\ScanCounter::stats());
    }

    public function test_community_counter_shows_today_and_total(): void
    {
        \Illuminate\Support\Facades\DB::table('scan_counts')->insert([
            ['date' => now()->subDay()->toDateString(), 'scans' => 40],
            ['date' => now()->toDateString(), 'scans' => 2],
        ]);
        \App\Scanning\ScanCounter::increment();

        $this->assertSame(['today' => 3, 'total' => 43], \App\Scanning\ScanCounter::stats());

        $this->get('/')
            ->assertSee('Scans by the community')
            ->assertSee('projects scanned today')
            ->assertDontSee('in total');
    }

    public function test_a_session_cannot_load_someone_elses_scan(): void
    {
        $id = EphemeralScanStore::create('someone-else', []);
        EphemeralScanStore::update($id, ['status' => 'completed', 'results' => ['summary' => ['findings_total' => 0]]]);

        Livewire::test(Scanner::class)
            ->call('loadScan', $id)
            ->assertSet('selectedHistoryId', null)
            ->call('webScanStarted', $id)
            ->assertSet('activeScanId', null);
    }

    public function test_web_page_shows_browser_picker_and_privacy_note(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('webkitdirectory', false)
            ->assertSee('no code stored')
            ->assertSee('By scanning, you agree to the')
            ->assertSee(route('terms'), false)
            ->assertSee('Private scan.')
            ->assertSee('What happens to my code?')
            ->assertSee('Deleted immediately:')
            ->assertDontSee('Install via Homebrew')
            ->assertSee('https://github.com/aliuwahab"', false)
            ->assertSee('https://github.com/sponsors/aliuwahab', false)
            ->assertDontSee('Hosted scan')
            ->assertDontSee('CI YAML')
            ->assertDontSee('⌘');
    }

    public function test_terms_page_explains_code_is_not_stored_and_includes_the_license(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('Terms of Use')
            ->assertSee('We do not store your code.')
            ->assertSee('deleted as soon as the scan finishes')
            ->assertSee('Permission is hereby granted, free of charge')
            ->assertSee('id="9-license"', false)
            ->assertDontSee('googleapis');
    }
}
