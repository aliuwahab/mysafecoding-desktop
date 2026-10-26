<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Process;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Report every scanner as installed so UI tests don't depend on the
        // machine (CI has no trivy/semgrep/gitleaks). Other commands still run.
        Process::fake([
            "'which' *" => Process::result('/usr/local/bin/tool'),
        ]);
    }
}
