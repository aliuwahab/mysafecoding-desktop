<?php

namespace Tests\Unit;

use App\Scanning\ScanEngine;
use PHPUnit\Framework\TestCase;

class SecretRedactionTest extends TestCase
{
    public function test_masks_quoted_and_assigned_secrets(): void
    {
        $this->assertSame('const key = "sk_l********";', ScanEngine::maskSecretValues('const key = "sk_live_4eC39HqLyjWDarjtT1zdp7dc";'));
        $this->assertSame('3: DB_PASSWORD=hunt********', ScanEngine::maskSecretValues('3: DB_PASSWORD=hunter2hunter2'));
        $this->assertSame("x = 'ok'", ScanEngine::maskSecretValues("x = 'ok'"));
    }

    public function test_only_secret_findings_are_redacted(): void
    {
        $results = ScanEngine::redactSecrets([
            'custom_rules' => ['findings' => [
                ['rule_id' => 'checkmycode.hardcoded-credential', 'category' => 'secret', 'code_snippet' => 'k = "AKIAIOSFODNN7EXAMPLE"'],
                ['rule_id' => 'checkmycode.js.eval-usage', 'category' => 'injection', 'code_snippet' => 'eval("payload-string")'],
            ]],
            'semgrep' => ['results' => [
                ['check_id' => 'generic.secrets.security.detected-stripe-api-key', 'extra' => ['lines' => 'key = "sk_live_abcdefghijkl"']],
            ]],
        ]);

        $this->assertSame('k = "AKIA********"', $results['custom_rules']['findings'][0]['code_snippet']);
        $this->assertSame('eval("payload-string")', $results['custom_rules']['findings'][1]['code_snippet']);
        $this->assertSame('key = "sk_l********"', $results['semgrep']['results'][0]['extra']['lines']);
    }
}
