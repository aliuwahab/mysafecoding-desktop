<?php

namespace Tests\Unit;

use App\Services\GitHubActionsWorkflowGenerator;
use PHPUnit\Framework\TestCase;

class GitHubActionsWorkflowGeneratorTest extends TestCase
{
    public function test_it_generates_portable_github_actions_workflow(): void
    {
        $workflow = GitHubActionsWorkflowGenerator::generate(['vendor', 'node_modules', 'vendor']);

        $this->assertStringContainsString('name: MySafeCoding', $workflow);
        $this->assertStringContainsString('actions/checkout@v4', $workflow);
        $this->assertStringContainsString('raw.githubusercontent.com/aliuwahab/laraguard-desktop/main/resources/cli/mysafecoding-scan.sh', $workflow);
        $this->assertStringContainsString("/tmp/mysafecoding-scan.sh . --output-dir mysafecoding-results --non-interactive --exclude 'vendor' --exclude 'node_modules'", $workflow);
        $this->assertSame(1, substr_count($workflow, "--exclude 'vendor'"));
    }
}
