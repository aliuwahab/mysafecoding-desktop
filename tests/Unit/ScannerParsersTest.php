<?php

namespace Tests\Unit;

use App\Scanning\Parsers\OsvScannerResults;
use App\Scanning\Parsers\ZizmorResults;
use Tests\TestCase;

class ScannerParsersTest extends TestCase
{
    public function test_osv_findings_merge_into_dependencies_without_duplicates(): void
    {
        $trivy = ['Results' => [[
            'Target' => 'package-lock.json',
            'Type' => 'npm',
            'Vulnerabilities' => [
                ['VulnerabilityID' => 'CVE-2020-8203', 'PkgName' => 'lodash', 'InstalledVersion' => '4.17.15', 'Severity' => 'HIGH'],
            ],
        ]]];

        $osv = ['results' => [[
            'source' => ['path' => '/work/app/composer.lock', 'type' => 'lockfile'],
            'packages' => [
                [
                    'package' => ['name' => 'lodash', 'version' => '4.17.15', 'ecosystem' => 'npm'],
                    'vulnerabilities' => [['id' => 'GHSA-p6mc-m468-83gw', 'aliases' => ['CVE-2020-8203'], 'summary' => 'dup']],
                    'groups' => [],
                ],
                [
                    'package' => ['name' => 'guzzlehttp/guzzle', 'version' => '7.4.0', 'ecosystem' => 'Packagist'],
                    'vulnerabilities' => [[
                        'id' => 'GHSA-25mq-v84q-4j7r',
                        'aliases' => ['CVE-2022-29248'],
                        'summary' => 'Cross-domain cookie leakage in Guzzle',
                        'affected' => [['package' => ['name' => 'guzzlehttp/guzzle'], 'ranges' => [['events' => [['introduced' => '7.0.0'], ['fixed' => '7.4.3']]]]]],
                    ]],
                    'groups' => [['ids' => ['GHSA-25mq-v84q-4j7r'], 'max_severity' => '8.1']],
                ],
            ],
        ]]];

        $merged = OsvScannerResults::merge($trivy, $osv, '/work/app');

        $this->assertSame(1, $merged['added']);
        $added = $merged['trivy']['Results'][1];
        $this->assertSame('composer.lock', $added['Target']);
        $this->assertSame('composer', $added['Type']);
        $this->assertSame('CVE-2022-29248', $added['Vulnerabilities'][0]['VulnerabilityID']);
        $this->assertSame('HIGH', $added['Vulnerabilities'][0]['Severity']);
        $this->assertSame('7.4.3', $added['Vulnerabilities'][0]['FixedVersion']);
        $this->assertSame('https://osv.dev/vulnerability/GHSA-25mq-v84q-4j7r', $added['Vulnerabilities'][0]['PrimaryURL']);
    }

    public function test_osv_works_when_trivy_did_not_run(): void
    {
        $merged = OsvScannerResults::merge(null, ['results' => []], '/work');

        $this->assertSame(['Results' => []], $merged['trivy']);
        $this->assertSame(0, $merged['added']);
    }

    public function test_zizmor_findings_become_workflow_findings(): void
    {
        $result = ZizmorResults::parse(json_decode(file_get_contents(base_path('tests/Fixtures/zizmor.json')), true));

        $this->assertSame(6, $result['summary']['total']);
        $this->assertSame(4, $result['summary']['HIGH']);

        $injection = collect($result['findings'])->firstWhere('rule_id', 'zizmor.template-injection');
        $this->assertSame('HIGH', $injection['severity']);
        $this->assertSame('.github/workflows/ci.yml', $injection['file_path']);
        $this->assertSame(14, $injection['line_start']);
        $this->assertStringContainsString('docs.zizmor.sh', $injection['link']);
    }

    public function test_zizmor_ignored_findings_are_skipped(): void
    {
        $result = ZizmorResults::parse([['ident' => 'artipacked', 'ignored' => true, 'locations' => []]]);

        $this->assertSame(0, $result['summary']['total']);
    }
}
