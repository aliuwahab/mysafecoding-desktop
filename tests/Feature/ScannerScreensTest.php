<?php

namespace Tests\Feature;

use App\Livewire\Scanner;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Renders every results tab against real engine output so template
 * regressions show up in CI rather than in the app.
 */
class ScannerScreensTest extends TestCase
{
    use RefreshDatabase;

    private function results(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/laravel-results.json')), true);
    }

    public function test_every_tab_renders_scan_results(): void
    {
        $results = $this->results();
        $scan = Scan::create([
            'project_path' => '/tmp/example-app',
            'status' => 'completed',
            'exclusions' => ['vendor'],
            'results' => $results,
            'findings_count' => $results['summary']['findings_total'],
            'started_at' => now()->subSeconds(14),
            'completed_at' => now(),
        ]);

        $component = Livewire::test(Scanner::class)
            ->assertSet('selectedHistoryId', (string) $scan->id)
            ->assertSee('Security grade')
            ->assertSee('Lifecycle');

        $expectations = [
            'log' => 'Scan log',
            'summary' => 'Severity distribution',
            'trivy' => $results['trivy']['Results'][0]['Vulnerabilities'][0]['VulnerabilityID'],
            'semgrep' => 'checkmycode.laravel.env-outside-config',
            'gitleaks' => 'stripe-access-token',
            'rules' => 'checkmycode.hardcoded-credential',
            'config' => 'Image user should not be root',
            'lifecycle' => 'Security support ends',
            'licenses' => 'nativephp/php-bin',
            'env_audit' => 'Environment audit',
            'code_quality' => 'Code quality',
        ];

        foreach ($expectations as $tab => $text) {
            $component->set('activeTab', $tab)->assertSee($text);
        }
    }

    public function test_gitignored_skips_and_local_env_are_explained(): void
    {
        $results = $this->results();
        $results['gitignored'] = ['skipped_findings' => 2, 'files' => ['.env', 'config/local-secrets.json'], 'env_local_only' => true];
        $results['env_audit']['local_only'] = true;
        Scan::create(['project_path' => '/tmp/example-app', 'status' => 'completed', 'results' => $results]);

        Livewire::test(Scanner::class)
            ->set('activeTab', 'gitleaks')
            ->assertSee('Skipped 2 findings')
            ->assertSee('config/local-secrets.json')
            ->set('activeTab', 'env_audit')
            ->assertSee('settings and they don')
            ->assertSee('affect the grade');
    }

    public function test_rule_findings_can_be_ignored(): void
    {
        $results = $this->results();
        Scan::create([
            'project_path' => '/tmp/example-app',
            'status' => 'completed',
            'results' => $results,
        ]);
        $finding = $results['custom_rules']['findings'][0];

        Livewire::test(Scanner::class)
            ->set('activeTab', 'rules')
            ->call('promptIgnore', 'custom_rules:'.$finding['fingerprint'])
            ->set('ignoreReason', 'Test fixture')
            ->call('confirmIgnore')
            ->assertSee('IGNORED');

        $this->assertDatabaseHas('ignored_findings', [
            'project_path' => '/tmp/example-app',
            'scanner' => 'custom_rules',
            'fingerprint' => $finding['fingerprint'],
        ]);
    }

    public function test_results_from_the_previous_engine_still_render(): void
    {
        Scan::create([
            'project_path' => '/tmp/legacy-app',
            'status' => 'completed',
            'results' => [
                'project_type' => 'laravel',
                'summary' => ['trivy' => ['CRITICAL' => 1, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0], 'trivy_total' => 1, 'laravel_rules_total' => 0],
                'trivy' => ['Results' => []],
            ],
            'findings_count' => 1,
        ]);

        $component = Livewire::test(Scanner::class)->assertSee('Security grade');

        foreach (['rules', 'lifecycle', 'config', 'licenses', 'trivy', 'gitleaks'] as $tab) {
            $component->set('activeTab', $tab)->assertOk();
        }
    }
}
