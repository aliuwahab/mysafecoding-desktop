<?php

namespace Tests\Unit;

use App\Services\GitHubActionsWorkflowGenerator;
use PHPUnit\Framework\TestCase;

class GitHubActionsWorkflowGeneratorTest extends TestCase
{
    public function test_it_generates_portable_github_actions_workflow(): void
    {
        $workflow = GitHubActionsWorkflowGenerator::generate(['vendor', 'node_modules', 'vendor'], 'ghcr.io/example/mysafecoding:1.0');

        $this->assertStringContainsString('name: MySafeCoding', $workflow);
        $this->assertStringContainsString('actions/checkout@v4', $workflow);
        $this->assertStringContainsString('docker run --rm -v "$PWD:/src" -w /src ghcr.io/example/mysafecoding:1.0 scan /src --no-defaults', $workflow);
        $this->assertStringContainsString("--exclude 'vendor' --exclude 'node_modules'", $workflow);
        $this->assertStringContainsString('github/codeql-action/upload-sarif@v3', $workflow);
        $this->assertSame(1, substr_count($workflow, "--exclude 'vendor'"));
    }
}
