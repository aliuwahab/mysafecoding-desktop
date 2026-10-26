<?php

namespace App\Scanning\Rules;

use App\Scanning\ProjectDetector;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Regex-based rule pack shared with the hosted CheckMyCode platform.
 *
 * Laravel projects are covered by the dedicated LaravelRuleEngine plus the
 * custom Semgrep rules, so only the universal rules run for them here.
 */
class PatternRuleScanner
{
    protected const SKIP_DIRS = ['.git', 'vendor', 'node_modules', 'storage', 'dist', 'build', 'nativephp', '.cache', '__pycache__', '.venv', 'venv', 'bootstrap/cache'];

    /** @var list<string> */
    protected array $exclusions = [];

    /**
     * @param  list<string>  $exclusions
     * @return array{findings: array<int, array<string, mixed>>, summary: array{total:int, CRITICAL:int, HIGH:int, MEDIUM:int, LOW:int}}
     */
    public function scan(string $repositoryPath, string $language, array $exclusions = []): array
    {
        $repositoryPath = rtrim($repositoryPath, '/');
        $this->exclusions = array_values(array_filter(array_map(fn (string $e): string => trim($e, '/'), $exclusions)));

        $findings = [
            ...$this->universalFindings($repositoryPath),
        ];

        if (ProjectDetector::isPhp($language) && $language !== 'laravel') {
            $findings = [
                ...$findings,
                ...$this->phpPatternFindings($repositoryPath),
            ];
        }

        if (ProjectDetector::isPython($language)) {
            $findings = [
                ...$findings,
                ...$this->pythonPatternFindings($repositoryPath),
                ...$this->djangoConfigFindings($repositoryPath, $language),
            ];
        }

        if (ProjectDetector::isJavaScript($language)) {
            $findings = [
                ...$findings,
                ...$this->javascriptPatternFindings($repositoryPath),
            ];
        }

        if (ProjectDetector::isRuby($language)) {
            $findings = [
                ...$findings,
                ...$this->rubyPatternFindings($repositoryPath),
            ];
        }

        if ($language === 'go') {
            $findings = [
                ...$findings,
                ...$this->goPatternFindings($repositoryPath),
            ];
        }

        usort($findings, function (array $left, array $right): int {
            $severity = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3, 'INFO' => 4];

            return [$severity[$left['severity']] ?? 99, $left['file_path'], $left['line_start']]
                <=> [$severity[$right['severity']] ?? 99, $right['file_path'], $right['line_start']];
        });

        $summary = ['total' => count($findings), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        foreach ($findings as $finding) {
            if (isset($summary[$finding['severity']])) {
                $summary[$finding['severity']]++;
            }
        }

        return ['findings' => array_values($findings), 'summary' => $summary];
    }

    // -------------------------------------------------------------------------
    // Universal rules — run on every codebase regardless of language
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function universalFindings(string $repositoryPath): array
    {
        $findings = [];

        foreach ($this->textFilesIn($repositoryPath) as $file) {
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                foreach ($this->universalPatternRules() as $rule) {
                    if (! preg_match($rule['pattern'], $line)) {
                        continue;
                    }

                    if (($rule['skip'] ?? null) !== null && preg_match($rule['skip'], $relativePath)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        ruleId: $rule['rule_id'],
                        title: $rule['title'],
                        severity: $rule['severity'],
                        category: $rule['category'],
                        relativePath: $relativePath,
                        lineNumber: $lineNumber + 1,
                        match: trim($line),
                        why: $rule['why'] ?? null,
                        fix: $rule['fix'] ?? null,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * Cross-language pattern rules.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function universalPatternRules(): array
    {
        return [
            [
                'pattern' => '/["\'](?:sk_live_[a-zA-Z0-9]{20,}|AKIA[0-9A-Z]{16}|ghp_[a-zA-Z0-9]{36}|glpat-[a-zA-Z0-9\-_]{20,}|xoxb-[0-9a-zA-Z]{24,}|ya29\.[0-9A-Za-z\-_]{60,})["\']/',
                'rule_id' => 'checkmycode.hardcoded-credential',
                'title' => 'Hardcoded credential detected in source',
                'severity' => 'critical',
                'category' => 'secret',
                'why' => 'Credentials committed to source control are exposed to repository readers and remain recoverable from git history.',
                'fix' => 'Move the secret to environment-backed configuration and rotate the exposed credential immediately.',
            ],
            [
                'pattern' => '/(?:password|passwd|secret|token|api_?key)\s*=\s*["\'][^"\']{8,}["\']/',
                'rule_id' => 'checkmycode.plaintext-secret-assignment',
                'title' => 'Plaintext secret assignment detected',
                'severity' => 'high',
                'category' => 'secret',
                'why' => 'Plaintext secrets in source code are easily exposed via source reviews, logs, or dependency audits.',
                'fix' => 'Load secrets from environment variables or a secrets manager, never hardcode them.',
                'skip' => '#(test|spec|mock|fixture|example|\.md)#i',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // PHP / Laravel / Symfony rules
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function phpPatternFindings(string $repositoryPath): array
    {
        $findings = [];

        foreach ($this->phpFiles($repositoryPath) as $file) {
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                foreach ($this->phpPatternRules() as $rule) {
                    if (! preg_match($rule['pattern'], $line)) {
                        continue;
                    }

                    if (($rule['skip'] ?? null) !== null && preg_match($rule['skip'], $relativePath)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        ruleId: $rule['rule_id'],
                        title: $rule['title'],
                        severity: $rule['severity'],
                        category: $rule['category'],
                        relativePath: $relativePath,
                        lineNumber: $lineNumber + 1,
                        match: trim($line),
                        why: $rule['why'] ?? null,
                        fix: $rule['fix'] ?? null,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function phpPatternRules(): array
    {
        return [
            [
                'pattern' => '/\b(eval|shell_exec|system|passthru|exec)\s*\(/',
                'rule_id' => 'checkmycode.php.command-execution',
                'title' => 'Dynamic command execution primitive detected',
                'severity' => 'critical',
                'category' => 'code-execution',
                'why' => 'Command execution helpers turn untrusted input into shell execution and often lead directly to remote code execution.',
                'fix' => 'Avoid shell execution in request paths. Replace it with framework APIs or strict allowlisted commands.',
            ],
            [
                'pattern' => '/\b(DB::raw|selectRaw|whereRaw|orderByRaw)\s*\(/',
                'rule_id' => 'checkmycode.php.raw-sql',
                'title' => 'Raw SQL helper detected',
                'severity' => 'high',
                'category' => 'injection',
                'why' => 'Raw SQL helpers frequently bypass parameter binding and make injection bugs easier to introduce.',
                'fix' => 'Prefer query builder clauses with bound parameters. If raw SQL is required, bind every user-controlled value.',
            ],
            [
                'pattern' => '/withoutVerifying\s*\(/',
                'rule_id' => 'checkmycode.php.tls-verification-disabled',
                'title' => 'HTTP client TLS verification disabled',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Disabling TLS verification allows man-in-the-middle interception of outbound requests.',
                'fix' => 'Remove withoutVerifying() and fix the underlying certificate trust issue instead.',
            ],
            [
                'pattern' => '/\benv\s*\(/',
                'rule_id' => 'checkmycode.php.env-outside-config',
                'title' => 'env() usage detected outside config files',
                'severity' => 'medium',
                'category' => 'configuration',
                'why' => 'Using env() directly in runtime code can behave unpredictably once configuration is cached.',
                'fix' => 'Resolve values in config files and read them through config() in application code.',
                'skip' => '#(^|/)config/#',
            ],
            [
                'pattern' => '/::(create|forceCreate)\s*\(\s*(\$[A-Za-z_][A-Za-z0-9_]*->all\s*\(\s*\)|request\s*\(\s*\)\s*->all\s*\(\s*\))\s*\)/',
                'rule_id' => 'checkmycode.php.request-all-create',
                'title' => 'Request data passed directly into create()',
                'severity' => 'high',
                'category' => 'mass-assignment',
                'why' => 'Passing request()->all() into model creation makes unintended attributes writable if model protections are weak.',
                'fix' => 'Validate and map only explicit fields before calling create().',
            ],
            [
                'pattern' => '/->(update|fill|forceFill)\s*\(\s*(\$[A-Za-z_][A-Za-z0-9_]*->all\s*\(\s*\)|request\s*\(\s*\)\s*->all\s*\(\s*\))\s*\)/',
                'rule_id' => 'checkmycode.php.request-all-update',
                'title' => 'Request data passed directly into update/fill()',
                'severity' => 'high',
                'category' => 'mass-assignment',
                'why' => 'Mass-assigning the entire request payload can overwrite sensitive columns or authorization-sensitive state.',
                'fix' => 'Whitelist validated fields before update() or fill().',
            ],
            [
                'pattern' => '/::unguard\s*\(/',
                'rule_id' => 'checkmycode.php.model-unguard',
                'title' => 'Global mass-assignment protection disabled',
                'severity' => 'high',
                'category' => 'mass-assignment',
                'why' => 'Model::unguard() removes the global mass-assignment barrier for the rest of the request lifecycle.',
                'fix' => 'Remove global unguarding and define explicit $fillable or guarded attributes on each model.',
            ],
            [
                'pattern' => '/protected\s+\$guarded\s*=\s*\[\s*\]\s*;/',
                'rule_id' => 'checkmycode.php.empty-guarded',
                'title' => 'Model guarded array is empty',
                'severity' => 'medium',
                'category' => 'mass-assignment',
                'why' => 'An empty guarded array effectively makes every attribute mass assignable.',
                'fix' => 'Define a restrictive $fillable array or guard sensitive columns explicitly.',
            ],
            [
                'pattern' => '/Gate::before\s*\(.*(?:=>\s*true|return\s+true)/',
                'rule_id' => 'checkmycode.php.gate-before-allow-all',
                'title' => 'Gate::before unconditionally allows all abilities',
                'severity' => 'critical',
                'category' => 'authorization',
                'why' => 'A globally permissive Gate::before callback short-circuits every downstream authorization check.',
                'fix' => 'Restrict Gate::before to narrowly defined super-admin conditions or remove it.',
            ],
            [
                'pattern' => '/withoutMiddleware\s*\(.*(VerifyCsrfToken|ValidateCsrfToken)/',
                'rule_id' => 'checkmycode.php.csrf-disabled-route',
                'title' => 'Route explicitly disables CSRF middleware',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'Removing CSRF protection from state-changing routes exposes them to cross-site request forgery.',
                'fix' => 'Re-enable CSRF validation or constrain the endpoint to signed, non-browser requests.',
            ],
            [
                'pattern' => '/loginUsingId\s*\(\s*(\$[A-Za-z_][A-Za-z0-9_]*->|request\s*\(\s*\)\s*->)/',
                'rule_id' => 'checkmycode.php.login-using-id-request',
                'title' => 'Request-controlled loginUsingId usage detected',
                'severity' => 'high',
                'category' => 'authentication',
                'why' => 'Using request-controlled identifiers in loginUsingId() can turn parameter tampering into account takeover.',
                'fix' => 'Authenticate through validated credentials or signed trusted identifiers, not raw request input.',
            ],
            [
                'pattern' => '/(redirect|Redirect::to|redirect\(\))\s*\(\s*(\$request|\$_GET|\$_POST|request\s*\(\s*\))/',
                'rule_id' => 'checkmycode.php.open-redirect',
                'title' => 'User-controlled redirect target detected',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL allows phishing chains and trust abuse after login flows.',
                'fix' => 'Validate destinations against an allowlist or use internal named routes only.',
            ],
            [
                'pattern' => '/\bunserialize\s*\(\s*(\$[A-Za-z_][A-Za-z0-9_]*->|request\s*\(\s*\))/',
                'rule_id' => 'checkmycode.php.unserialize-user-input',
                'title' => 'PHP unserialize() called with user-controlled input',
                'severity' => 'critical',
                'category' => 'deserialization',
                'why' => 'Deserializing untrusted data can trigger arbitrary object instantiation and remote code execution via PHP property injection.',
                'fix' => 'Avoid unserialize() on untrusted data. Use JSON or a typed deserializer instead.',
            ],
            [
                'pattern' => '/\b(md5|sha1)\s*\(\s*\$/',
                'rule_id' => 'checkmycode.php.weak-hash',
                'title' => 'Weak hashing algorithm detected',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are computationally cheap, enabling fast brute-force attacks against hashed secrets.',
                'fix' => 'Use password_hash() for passwords and hash(\'sha256\', ...) or stronger for data integrity.',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Python / Django / Flask rules
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pythonPatternFindings(string $repositoryPath): array
    {
        $findings = [];

        foreach ($this->sourceFilesIn($repositoryPath, ['py']) as $file) {
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                foreach ($this->pythonPatternRules() as $rule) {
                    if (! preg_match($rule['pattern'], $line)) {
                        continue;
                    }

                    if (($rule['skip'] ?? null) !== null && preg_match($rule['skip'], $relativePath)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        ruleId: $rule['rule_id'],
                        title: $rule['title'],
                        severity: $rule['severity'],
                        category: $rule['category'],
                        relativePath: $relativePath,
                        lineNumber: $lineNumber + 1,
                        match: trim($line),
                        why: $rule['why'] ?? null,
                        fix: $rule['fix'] ?? null,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pythonPatternRules(): array
    {
        return [
            [
                'pattern' => '/\beval\s*\(/',
                'rule_id' => 'checkmycode.python.eval-usage',
                'title' => 'eval() usage detected',
                'severity' => 'critical',
                'category' => 'code-execution',
                'why' => 'eval() on untrusted input can execute arbitrary Python code and lead to full system compromise.',
                'fix' => 'Replace eval() with a safe parser, AST evaluation, or a typed input handler.',
            ],
            [
                'pattern' => '/\b(subprocess\.(call|run|Popen|check_output)|os\.system)\s*\(/',
                'rule_id' => 'checkmycode.python.subprocess-usage',
                'title' => 'Subprocess or os.system call detected',
                'severity' => 'high',
                'category' => 'code-execution',
                'why' => 'Shell commands assembled from user input are vulnerable to command injection.',
                'fix' => 'Pass arguments as a list (never shell=True with user input) and validate every argument.',
            ],
            [
                'pattern' => '/\bpickle\.loads?\s*\(/',
                'rule_id' => 'checkmycode.python.pickle-deserialization',
                'title' => 'Python pickle deserialization detected',
                'severity' => 'critical',
                'category' => 'deserialization',
                'why' => 'Deserializing untrusted pickle data can execute arbitrary code during reconstruction.',
                'fix' => 'Use JSON, MessagePack, or another safe serialization format for untrusted data.',
            ],
            [
                'pattern' => '/\b__import__\s*\(/',
                'rule_id' => 'checkmycode.python.dynamic-import',
                'title' => 'Dynamic __import__() usage detected',
                'severity' => 'high',
                'category' => 'code-execution',
                'why' => 'Dynamic imports from user-controlled strings can load malicious modules.',
                'fix' => 'Use importlib.import_module() with an allowlist of permitted module names.',
            ],
            [
                'pattern' => '/hashlib\.(md5|sha1)\s*\(/',
                'rule_id' => 'checkmycode.python.weak-hash',
                'title' => 'Weak hashing algorithm detected',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are computationally cheap, enabling fast brute-force attacks against hashed secrets.',
                'fix' => 'Use hashlib.sha256() or stronger. For passwords, use bcrypt, argon2, or scrypt.',
            ],
            [
                'pattern' => '/\bverify\s*=\s*False\b/',
                'rule_id' => 'checkmycode.python.ssl-verification-disabled',
                'title' => 'TLS certificate verification disabled in HTTP request',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Disabling SSL verification allows man-in-the-middle interception of HTTPS traffic.',
                'fix' => 'Remove verify=False and fix the underlying certificate trust issue.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function djangoConfigFindings(string $repositoryPath, string $language): array
    {
        if ($language !== 'django') {
            return [];
        }

        $findings = [];

        foreach ($this->sourceFilesIn($repositoryPath, ['py']) as $file) {
            if (! str_contains($file->getPathname(), 'settings')) {
                continue;
            }

            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());

            foreach ($lines as $index => $line) {
                if (preg_match('/^\s*DEBUG\s*=\s*True\b/', $line)) {
                    $findings[] = $this->makeFinding(
                        ruleId: 'checkmycode.django.debug-enabled',
                        title: 'Django DEBUG is set to True',
                        severity: 'critical',
                        category: 'configuration',
                        relativePath: $relativePath,
                        lineNumber: $index + 1,
                        match: trim($line),
                        why: 'DEBUG=True exposes stack traces, query details, and environment data to any visitor.',
                        fix: 'Set DEBUG = False in production or load from os.environ[\'DJANGO_DEBUG\'].',
                    );
                }

                if (preg_match('/^\s*ALLOWED_HOSTS\s*=\s*\[[\s\'\"]*\*/', $line)) {
                    $findings[] = $this->makeFinding(
                        ruleId: 'checkmycode.django.allowed-hosts-wildcard',
                        title: 'Django ALLOWED_HOSTS is set to wildcard',
                        severity: 'high',
                        category: 'configuration',
                        relativePath: $relativePath,
                        lineNumber: $index + 1,
                        match: trim($line),
                        why: 'Wildcard ALLOWED_HOSTS disables Django\'s Host header validation, enabling header injection attacks.',
                        fix: 'Set ALLOWED_HOSTS to a specific list of trusted hostnames.',
                    );
                }

                if (preg_match("/SECRET_KEY\s*=\s*['\"][^'\"]{10,}['\"]/", $line) && ! str_contains($line, 'os.environ') && ! str_contains($line, 'env(')) {
                    $findings[] = $this->makeFinding(
                        ruleId: 'checkmycode.django.hardcoded-secret-key',
                        title: 'Django SECRET_KEY appears to be hardcoded',
                        severity: 'critical',
                        category: 'secret',
                        relativePath: $relativePath,
                        lineNumber: $index + 1,
                        match: trim($line),
                        why: 'A hardcoded SECRET_KEY in source control exposes session signing keys to anyone with repository access.',
                        fix: 'Load SECRET_KEY from an environment variable: SECRET_KEY = os.environ[\'DJANGO_SECRET_KEY\'].',
                    );
                }
            }
        }

        return $findings;
    }

    // -------------------------------------------------------------------------
    // JavaScript / TypeScript / Node.js rules
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function javascriptPatternFindings(string $repositoryPath): array
    {
        $findings = [];

        foreach ($this->sourceFilesIn($repositoryPath, ['js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx']) as $file) {
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                foreach ($this->javascriptPatternRules() as $rule) {
                    if (! preg_match($rule['pattern'], $line)) {
                        continue;
                    }

                    if (($rule['skip'] ?? null) !== null && preg_match($rule['skip'], $relativePath)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        ruleId: $rule['rule_id'],
                        title: $rule['title'],
                        severity: $rule['severity'],
                        category: $rule['category'],
                        relativePath: $relativePath,
                        lineNumber: $lineNumber + 1,
                        match: trim($line),
                        why: $rule['why'] ?? null,
                        fix: $rule['fix'] ?? null,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function javascriptPatternRules(): array
    {
        return [
            [
                'pattern' => '/\beval\s*\(/',
                'rule_id' => 'checkmycode.js.eval-usage',
                'title' => 'eval() usage detected',
                'severity' => 'critical',
                'category' => 'code-execution',
                'why' => 'eval() on untrusted strings can execute arbitrary JavaScript and is a common XSS escalation target.',
                'fix' => 'Replace eval() with JSON.parse() for data, or a safe expression evaluator for logic.',
                'skip' => '#(\.min\.js|test|spec|__tests__)#',
            ],
            [
                'pattern' => '/innerHTML\s*=(?!=)/',
                'rule_id' => 'checkmycode.js.unsafe-innerhtml',
                'title' => 'Unsafe innerHTML assignment detected',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'Assigning unescaped strings to innerHTML introduces DOM XSS vulnerabilities.',
                'fix' => 'Use textContent for plain text or a trusted sanitization library like DOMPurify for HTML.',
                'skip' => '#(test|spec|__tests__)#',
            ],
            [
                'pattern' => '/document\.write\s*\(/',
                'rule_id' => 'checkmycode.js.document-write',
                'title' => 'document.write() usage detected',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'document.write() can introduce XSS when given untrusted input and interferes with modern CSP.',
                'fix' => 'Use modern DOM manipulation methods (createElement, appendChild) instead.',
                'skip' => '#(test|spec|__tests__)#',
            ],
            [
                'pattern' => '/\brequire\s*\(\s*(?:child_process|exec|spawn)/',
                'rule_id' => 'checkmycode.js.child-process-require',
                'title' => 'child_process module imported',
                'severity' => 'medium',
                'category' => 'code-execution',
                'why' => 'Child process execution with user-controlled input can lead to command injection.',
                'fix' => 'Validate and allowlist any arguments passed to child process commands. Avoid shell: true.',
            ],
            [
                'pattern' => '/process\.env\.NODE_ENV\s*!==\s*["\']production["\']/',
                'rule_id' => 'checkmycode.js.dev-mode-check',
                'title' => 'Non-production environment check detected in code',
                'severity' => 'info',
                'category' => 'configuration',
                'why' => 'Development-mode checks in production paths can accidentally expose debug features.',
                'fix' => 'Ensure this path is not reachable in production or guard it with a stricter check.',
            ],
            [
                'pattern' => '/new\s+Function\s*\(/',
                'rule_id' => 'checkmycode.js.new-function',
                'title' => 'new Function() usage detected',
                'severity' => 'high',
                'category' => 'code-execution',
                'why' => 'new Function() is equivalent to eval() and can execute arbitrary code from strings.',
                'fix' => 'Replace with a proper function definition or a safe expression evaluator.',
                'skip' => '#(test|spec|__tests__)#',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Ruby / Rails rules
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rubyPatternFindings(string $repositoryPath): array
    {
        $findings = [];

        foreach ($this->sourceFilesIn($repositoryPath, ['rb', 'rake', 'erb']) as $file) {
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                foreach ($this->rubyPatternRules() as $rule) {
                    if (! preg_match($rule['pattern'], $line)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        ruleId: $rule['rule_id'],
                        title: $rule['title'],
                        severity: $rule['severity'],
                        category: $rule['category'],
                        relativePath: $relativePath,
                        lineNumber: $lineNumber + 1,
                        match: trim($line),
                        why: $rule['why'] ?? null,
                        fix: $rule['fix'] ?? null,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rubyPatternRules(): array
    {
        return [
            [
                'pattern' => '/\beval\s*\(/',
                'rule_id' => 'checkmycode.ruby.eval-usage',
                'title' => 'eval() usage detected',
                'severity' => 'critical',
                'category' => 'code-execution',
                'why' => 'Ruby eval() on untrusted input can execute arbitrary code.',
                'fix' => 'Replace with a safe expression parser or structured data handling.',
            ],
            [
                'pattern' => '/\bsystem\s*\(|`[^`]*`|\bexec\s*\(|\bspawn\s*\(/',
                'rule_id' => 'checkmycode.ruby.shell-execution',
                'title' => 'Shell command execution detected',
                'severity' => 'high',
                'category' => 'code-execution',
                'why' => 'Shell execution with user-controlled input leads to command injection.',
                'fix' => 'Use parameterized shell calls and avoid interpolating user input into shell strings.',
            ],
            [
                'pattern' => '/Marshal\.load\s*\(/',
                'rule_id' => 'checkmycode.ruby.marshal-deserialization',
                'title' => 'Ruby Marshal.load() deserialization detected',
                'severity' => 'critical',
                'category' => 'deserialization',
                'why' => 'Deserializing untrusted Marshal data can execute arbitrary Ruby code.',
                'fix' => 'Use JSON or another safe format for untrusted data. Never Marshal.load untrusted input.',
            ],
            [
                'pattern' => '/params\.permit!\s*\(?\s*\)?/',
                'rule_id' => 'checkmycode.rails.permit-all',
                'title' => 'Strong parameters permit! (allow-all) detected',
                'severity' => 'high',
                'category' => 'mass-assignment',
                'why' => 'permit! bypasses Rails strong parameters and makes all submitted fields mass-assignable.',
                'fix' => 'Replace permit! with an explicit list of allowed parameters.',
            ],
            [
                'pattern' => '/\.where\s*\(\s*["\'][^"\']*#\{/',
                'rule_id' => 'checkmycode.rails.sql-injection-interpolation',
                'title' => 'SQL query with string interpolation detected',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'String-interpolated ActiveRecord queries bypass parameter binding and are vulnerable to SQL injection.',
                'fix' => 'Use parameterized queries: .where("column = ?", value) or .where(column: value).',
            ],
            [
                'pattern' => '/render\s+inline\s*:/',
                'rule_id' => 'checkmycode.rails.inline-render',
                'title' => 'Inline template rendering detected',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'Rendering inline templates with user-controlled content can introduce XSS or code execution.',
                'fix' => 'Use a proper template file and avoid rendering user input directly as ERB.',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Go rules
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function goPatternFindings(string $repositoryPath): array
    {
        $findings = [];

        foreach ($this->sourceFilesIn($repositoryPath, ['go']) as $file) {
            $relativePath = $this->relativePath($repositoryPath, $file->getPathname());
            $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                foreach ($this->goPatternRules() as $rule) {
                    if (! preg_match($rule['pattern'], $line)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        ruleId: $rule['rule_id'],
                        title: $rule['title'],
                        severity: $rule['severity'],
                        category: $rule['category'],
                        relativePath: $relativePath,
                        lineNumber: $lineNumber + 1,
                        match: trim($line),
                        why: $rule['why'] ?? null,
                        fix: $rule['fix'] ?? null,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function goPatternRules(): array
    {
        return [
            [
                'pattern' => '/exec\.Command\s*\(/',
                'rule_id' => 'checkmycode.go.command-execution',
                'title' => 'exec.Command usage detected',
                'severity' => 'medium',
                'category' => 'code-execution',
                'why' => 'exec.Command with user-controlled arguments can lead to command injection.',
                'fix' => 'Validate and allowlist all arguments before passing to exec.Command. Never interpolate user input into shell strings.',
            ],
            [
                'pattern' => '/InsecureSkipVerify\s*:\s*true/',
                'rule_id' => 'checkmycode.go.tls-insecure-skip-verify',
                'title' => 'TLS InsecureSkipVerify set to true',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Disabling TLS certificate verification allows man-in-the-middle attacks on HTTPS connections.',
                'fix' => 'Remove InsecureSkipVerify and fix the underlying certificate configuration.',
            ],
            [
                'pattern' => '/fmt\.Sprintf\s*\(.*SELECT|fmt\.Sprintf\s*\(.*INSERT|fmt\.Sprintf\s*\(.*UPDATE|fmt\.Sprintf\s*\(.*DELETE/',
                'rule_id' => 'checkmycode.go.sql-string-format',
                'title' => 'SQL query built with fmt.Sprintf',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Building SQL with fmt.Sprintf and user-controlled values bypasses parameterization and enables SQL injection.',
                'fix' => 'Use db.Query() or db.Exec() with parameterized placeholders (?) instead of string formatting.',
            ],
            [
                'pattern' => '/md5\.New\(\)|md5\.Sum\(/',
                'rule_id' => 'checkmycode.go.weak-hash-md5',
                'title' => 'Weak MD5 hash function detected',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 is cryptographically broken and should not be used for security-sensitive hashing.',
                'fix' => 'Use crypto/sha256 or crypto/sha512 for data integrity, and bcrypt/argon2 for passwords.',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // File iterators
    // -------------------------------------------------------------------------

    /**
     * Iterate all text source files, skipping common build artefact directories.
     *
     * @return iterable<SplFileInfo>
     */
    protected function textFilesIn(string $repositoryPath): iterable
    {
        $skipDirs = self::SKIP_DIRS;
        $textExts = ['php', 'py', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'rb', 'go', 'rs', 'java', 'kt', 'ex', 'exs', 'env', 'yml', 'yaml', 'json', 'toml'];

        return $this->filteredFiles($repositoryPath, $skipDirs, $textExts);
    }

    /**
     * Iterate only PHP files.
     *
     * @return iterable<SplFileInfo>
     */
    protected function phpFiles(string $repositoryPath): iterable
    {
        $skipDirs = self::SKIP_DIRS;

        return $this->filteredFiles($repositoryPath, $skipDirs, ['php']);
    }

    /**
     * Iterate source files with the given extensions.
     *
     * @param  list<string>  $extensions
     * @return iterable<SplFileInfo>
     */
    protected function sourceFilesIn(string $repositoryPath, array $extensions): iterable
    {
        $skipDirs = self::SKIP_DIRS;

        return $this->filteredFiles($repositoryPath, $skipDirs, $extensions);
    }

    /**
     * @param  list<string>  $skipDirs
     * @param  list<string>  $extensions
     * @return iterable<SplFileInfo>
     */
    protected function filteredFiles(string $repositoryPath, array $skipDirs, array $extensions): iterable
    {
        $skip = array_merge($skipDirs, $this->exclusions);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($repositoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
                function (SplFileInfo $file) use ($repositoryPath, $skip): bool {
                    if ($file->isLink()) {
                        return false;
                    }
                    $relative = $this->relativePath($repositoryPath, $file->getPathname());

                    foreach ($skip as $dir) {
                        if ($relative === $dir || str_starts_with($relative, $dir.'/') || ($file->isDir() && $file->getFilename() === $dir)) {
                            return false;
                        }
                    }

                    return true;
                },
            ),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($file->getSize() > 1024 * 1024) {
                continue;
            }

            if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            yield $file;
        }
    }

    protected function relativePath(string $repositoryPath, string $path): string
    {
        return ltrim(str_replace($repositoryPath, '', $path), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeFinding(
        string $ruleId,
        string $title,
        string $severity,
        string $category,
        string $relativePath,
        int $lineNumber,
        string $match,
        ?string $why = null,
        ?string $fix = null,
    ): array {
        return [
            'rule_id' => $ruleId,
            'title' => $title,
            'severity' => strtoupper($severity),
            'category' => $category,
            'description' => $why ?? $title,
            'file_path' => $relativePath,
            'line_start' => $lineNumber,
            'code_snippet' => mb_strimwidth($match, 0, 300, '…'),
            'fix_guidance' => $fix,
            'fingerprint' => sha1($ruleId.'|'.$relativePath.'|'.$lineNumber.'|'.$match),
        ];
    }
}
