<?php

namespace Tests\Unit;

use App\Scanning\FixPrompt;
use Tests\TestCase;

class FixPromptTest extends TestCase
{
    private function results(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/laravel-results.json')), true);
    }

    public function test_every_prompt_has_the_preserve_functionality_ground_rules(): void
    {
        $prompt = FixPrompt::forTab('semgrep', $this->results(), [], 'example-app');

        $this->assertStringContainsString('"example-app" (Laravel)', $prompt);
        $this->assertStringContainsString('Preserve existing functionality', $prompt);
        $this->assertStringContainsString('Needs review', $prompt);
        $this->assertStringContainsString('run the existing tests', $prompt);
        $this->assertStringContainsString('checkmycode.laravel.env-outside-config', $prompt);
        $this->assertStringContainsString('`app/Services/ScannerService.php:', $prompt);
    }

    public function test_dependency_prompt_lists_packages_and_fixed_versions(): void
    {
        $results = $this->results();
        $vuln = $results['trivy']['Results'][0]['Vulnerabilities'][0];

        $prompt = FixPrompt::forTab('trivy', $results);

        $this->assertStringContainsString("`{$vuln['PkgName']}` {$vuln['InstalledVersion']}", $prompt);
        $this->assertStringContainsString($vuln['VulnerabilityID'], $prompt);
        $this->assertStringContainsString('same major version', $prompt);
        $this->assertStringContainsString('### Safe upgrades (patch or minor): apply these', $prompt);
        $this->assertStringContainsString('same package-manager version', $prompt);
        $this->assertStringContainsString('pull-request description', $prompt);
    }

    public function test_secret_values_never_reach_the_prompt(): void
    {
        $results = $this->results();
        $results['gitleaks'] = [['RuleID' => 'stripe-access-token', 'Description' => 'Stripe Access Token', 'File' => 'src/pay.js', 'StartLine' => 3, 'Match' => 'key = "sk_live_REALSECRET123456"', 'Secret' => 'sk_live_REALSECRET123456', 'Commit' => 'abcdef1234567']];
        $results['custom_rules']['findings'][0]['code_snippet'] = 'const key = "sk_live_REALSECRET123456"';

        // Old results, before scans masked .env snippets, must not leak either.
        $results['laravel_rules']['findings'][0]['code_snippet'] = "3: APP_KEY=base64:REALSECRETKEYVALUE=\n4: APP_DEBUG=true";
        $results['laravel_rules']['findings'][0]['file_path'] = '.env';
        $results['semgrep']['results'][0]['extra']['lines'] = 'password = "REALSECRETpassword1"';

        foreach (['gitleaks', 'rules', 'semgrep'] as $tab) {
            $prompt = FixPrompt::forTab($tab, $results);
            $this->assertStringNotContainsString('REALSECRET', $prompt, $tab);
        }
        $this->assertStringContainsString('`src/pay.js:3` Stripe Access Token (also present in git history, commit abcdef12)', FixPrompt::forTab('gitleaks', $results));
    }

    public function test_ignored_findings_are_left_out_and_empty_tabs_have_no_prompt(): void
    {
        $results = $this->results();
        $finding = $results['custom_rules']['findings'][0];
        $ignored = ['custom_rules:'.$finding['fingerprint'] => 'test fixture'];

        $this->assertStringNotContainsString($finding['file_path'].':'.$finding['line_start'], FixPrompt::forTab('rules', $results, $ignored) ?? '');
        $this->assertNull(FixPrompt::forTab('workflows', ['workflows' => ['findings' => []]]));
    }

    public function test_license_prompt_asks_for_a_report_not_code_changes(): void
    {
        $prompt = FixPrompt::forTab('licenses', $this->results());

        $this->assertStringContainsString('Do not change any code for this task.', $prompt);
    }

    public function test_dependency_prompt_makes_one_decision_per_package(): void
    {
        $results = ['project_type' => 'node', 'trivy' => ['Results' => [[
            'Target' => 'package-lock.json',
            'Vulnerabilities' => [
                ['VulnerabilityID' => 'CVE-1', 'PkgName' => 'lodash', 'InstalledVersion' => '4.17.15', 'FixedVersion' => '4.17.19', 'Severity' => 'HIGH', 'Title' => 'a'],
                ['VulnerabilityID' => 'CVE-2', 'PkgName' => 'lodash', 'InstalledVersion' => '4.17.15', 'FixedVersion' => '4.18.0', 'Severity' => 'MEDIUM', 'Title' => 'b'],
                ['VulnerabilityID' => 'CVE-3', 'PkgName' => 'vite-plus', 'InstalledVersion' => '0.3.0', 'FixedVersion' => '1.1.0', 'Severity' => 'HIGH', 'Title' => 'c', 'DevDependency' => true],
            ],
        ]]]];

        $prompt = FixPrompt::forTab('trivy', $results);

        $this->assertSame(1, substr_count($prompt, '- `lodash`'));
        $this->assertStringContainsString('2 advisories (HIGH ×1, MEDIUM ×1)', $prompt);
        $this->assertStringContainsString('Upgrading to 4.18.0 (minor upgrade) fixes all of them.', $prompt);
        $this->assertMatchesRegularExpression('/### Major upgrade required.*\n- `vite-plus` 0\.3\.0 in `package-lock\.json` \(dev dependency\)/', $prompt);
        $this->assertStringContainsString('## Issues (3)', $prompt);
        $this->assertStringContainsString('assess the real exposure', $prompt);
    }
}
