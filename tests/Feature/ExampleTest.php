<?php

namespace Tests\Feature;

use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('CheckMyCode')
            ->assertSee('Select Folder');
    }

    public function test_the_latest_scan_is_rendered_on_load(): void
    {
        $scan = Scan::create([
            'project_path' => '/tmp/example-app',
            'status' => 'completed',
            'exclusions' => ['vendor'],
            'results' => [
                'summary' => [
                    'trivy_total' => 2,
                    'semgrep_total' => 1,
                    'gitleaks_total' => 0,
                ],
            ],
            'findings_count' => 3,
            'completed_at' => now(),
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee(basename($scan->project_path))
            ->assertSee('total findings')
            ->assertSee('3');
    }
}
