<?php

namespace Tests\Unit;

use App\Services\SarifExporter;
use PHPUnit\Framework\TestCase;

class SarifExporterTest extends TestCase
{
    public function test_it_exports_supported_scanner_findings_to_sarif(): void
    {
        $sarif = SarifExporter::fromResults([
            'project_type' => 'laravel',
            'trivy' => [
                'Results' => [[
                    'Target' => 'composer.lock',
                    'Vulnerabilities' => [[
                        'VulnerabilityID' => 'CVE-2026-0001',
                        'PkgName' => 'acme/package',
                        'Severity' => 'CRITICAL',
                        'Title' => 'Critical dependency issue',
                        'InstalledVersion' => '1.0.0',
                        'FixedVersion' => '1.0.1',
                    ]],
                ]],
            ],
            'semgrep' => [
                'results' => [[
                    'check_id' => 'php.lang.security.sql-injection',
                    'path' => 'app/Http/Controllers/UserController.php',
                    'start' => ['line' => 42],
                    'extra' => ['severity' => 'ERROR', 'message' => 'Possible SQL injection'],
                ]],
            ],
            'gitleaks' => [[
                'RuleID' => 'generic-api-key',
                'Description' => 'Generic API key',
                'File' => '.env',
                'StartLine' => 3,
            ]],
            'laravel_rules' => [
                'findings' => [[
                    'rule_id' => 'laravel.debug-mode',
                    'title' => 'Debug mode enabled',
                    'description' => 'APP_DEBUG is true',
                    'severity' => 'CRITICAL',
                    'file_path' => '.env',
                    'line_start' => 2,
                ]],
            ],
            'env_audit' => [
                'findings' => [[
                    'key' => 'APP_URL',
                    'title' => 'HTTP URL',
                    'description' => 'APP_URL should use HTTPS',
                    'severity' => 'HIGH',
                    'line' => 4,
                ]],
            ],
            'code_quality' => [
                'results' => [[
                    'check_id' => 'php.best-practice.example',
                    'path' => 'app/Example.php',
                    'start' => ['line' => 7],
                    'extra' => ['severity' => 'WARNING', 'message' => 'Improve this code'],
                ]],
            ],
        ]);

        $this->assertSame('2.1.0', $sarif['version']);
        $this->assertSame('MySafeCoding Desktop', $sarif['runs'][0]['tool']['driver']['name']);
        $this->assertCount(6, $sarif['runs'][0]['results']);
        $this->assertContains('trivy.cve-2026-0001', array_column($sarif['runs'][0]['results'], 'ruleId'));
        $this->assertContains('framework.laravel.debug-mode', array_column($sarif['runs'][0]['results'], 'ruleId'));
    }
}
