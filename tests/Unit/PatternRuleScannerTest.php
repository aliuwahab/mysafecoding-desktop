<?php

namespace Tests\Unit;

use App\Scanning\Rules\PatternRuleScanner;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class PatternRuleScannerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/mysafecoding-rules-'.uniqid();
        mkdir($this->path, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
        parent::tearDown();
    }

    private function write(string $relative, string $contents): void
    {
        @mkdir(dirname($this->path.'/'.$relative), 0755, true);
        file_put_contents($this->path.'/'.$relative, $contents);
    }

    private function ruleIds(array $result): array
    {
        return array_column($result['findings'], 'rule_id');
    }

    public function test_python_rules_and_finding_shape(): void
    {
        $this->write('app.py', "import pickle\ndata = pickle.loads(request.data)\nresult = eval(user_input)\n");

        $result = (new PatternRuleScanner)->scan($this->path, 'python');

        $this->assertContains('mysafecoding.python.pickle-deserialization', $this->ruleIds($result));
        $this->assertContains('mysafecoding.python.eval-usage', $this->ruleIds($result));

        $finding = $result['findings'][0];
        $this->assertSame('CRITICAL', $finding['severity']);
        $this->assertSame('app.py', $finding['file_path']);
        $this->assertIsInt($finding['line_start']);
        $this->assertNotEmpty($finding['fingerprint']);
        $this->assertSame($result['summary']['total'], count($result['findings']));
    }

    public function test_universal_credential_rule_runs_for_every_language(): void
    {
        $this->write('main.go', 'var key = "AKIAIOSFODNN7EXAMPLE"');

        $result = (new PatternRuleScanner)->scan($this->path, 'go');

        $this->assertContains('mysafecoding.hardcoded-credential', $this->ruleIds($result));
    }

    public function test_excluded_and_dependency_folders_are_skipped(): void
    {
        $this->write('node_modules/lib/index.js', 'eval(userInput)');
        $this->write('generated/out.js', 'eval(userInput)');
        $this->write('src/index.js', 'const ok = 1;');

        $result = (new PatternRuleScanner)->scan($this->path, 'node', ['generated']);

        $this->assertSame([], $result['findings']);
    }

    public function test_laravel_php_patterns_are_left_to_the_laravel_rule_engine(): void
    {
        $this->write('app/Console/Run.php', '<?php shell_exec($input);');

        $this->assertNotContains('mysafecoding.php.command-execution', $this->ruleIds((new PatternRuleScanner)->scan($this->path, 'laravel')));
        $this->assertContains('mysafecoding.php.command-execution', $this->ruleIds((new PatternRuleScanner)->scan($this->path, 'symfony')));
    }
}
