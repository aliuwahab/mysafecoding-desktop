<?php

namespace App\Scanning\Rules\Packs;

use App\Scanning\ProjectDetector;

/**
 * Plain PHP and Symfony. Laravel projects use the LaravelRuleEngine instead.
 */
class PhpPack extends RulePack
{
    public function appliesTo(string $projectType): bool
    {
        return ProjectDetector::isPhp($projectType) && $projectType !== 'laravel';
    }

    public function extensions(): array
    {
        return ['php'];
    }

    public function rules(): array
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
            [
                'pattern' => '/(->query|->exec|->prepare|mysqli_query|pg_query|->executeQuery|->executeStatement|->createQuery)\s*\(\s*(\$\w+\s*,\s*)?["\'][^"\']*["\']\s*\.\s*\$|(->query|->exec|mysqli_query|pg_query|->executeQuery|->executeStatement|->createQuery)\s*\(\s*(\$\w+\s*,\s*)?"[^"]*\$\w+/',
                'rule_id' => 'checkmycode.php.sql-concatenation',
                'title' => 'SQL query built by concatenating variables',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Concatenating or interpolating variables into SQL bypasses parameter binding and enables SQL injection.',
                'fix' => 'Use prepared statements with placeholders ($pdo->prepare("... WHERE id = ?")->execute([$id])) or Doctrine parameters.',
            ],
            [
                'pattern' => '/\b(include|include_once|require|require_once)\b\s*\(?\s*[^;]*\$_(GET|POST|REQUEST|COOKIE)/',
                'rule_id' => 'checkmycode.php.file-inclusion',
                'title' => 'File include path taken from request input',
                'severity' => 'critical',
                'category' => 'code-execution',
                'why' => 'Including a request-controlled path is local/remote file inclusion and usually leads to code execution.',
                'fix' => 'Map the input to an allowlist of known templates instead of building the path from it.',
            ],
            [
                'pattern' => '/\b(echo|print)\b[^;]*\$_(GET|POST|REQUEST|COOKIE|SERVER\[["\'](PHP_SELF|REQUEST_URI|QUERY_STRING))/',
                'unless' => '/htmlspecialchars|htmlentities|intval|\(int\)|json_encode|e\(/',
                'rule_id' => 'checkmycode.php.reflected-xss',
                'title' => 'Request input echoed without escaping',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'Printing request values directly lets attackers inject script into the page (reflected XSS).',
                'fix' => 'Escape output with htmlspecialchars($value, ENT_QUOTES, "UTF-8") or render through Twig.',
            ],
            [
                'pattern' => '/\bextract\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/',
                'rule_id' => 'checkmycode.php.extract-request',
                'title' => 'extract() on request input',
                'severity' => 'high',
                'category' => 'mass-assignment',
                'why' => 'extract() turns request keys into local variables, letting attackers overwrite any variable in scope.',
                'fix' => 'Read the specific keys you need from the request instead.',
            ],
            [
                'pattern' => '/CURLOPT_SSL_VERIFY(PEER|HOST)\s*,\s*(false|0)\b|["\']verify["\']\s*=>\s*false/',
                'rule_id' => 'checkmycode.php.curl-tls-verification-disabled',
                'title' => 'TLS certificate verification disabled',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Without certificate checks, anyone on the network path can intercept and modify HTTPS traffic.',
                'fix' => 'Keep CURLOPT_SSL_VERIFYPEER on (and VERIFYHOST = 2) and fix the CA bundle instead.',
            ],
            [
                'pattern' => '/header\s*\(\s*["\']Access-Control-Allow-Origin:\s*\*/i',
                'rule_id' => 'checkmycode.php.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your endpoint from a visitor\'s browser and read the responses.',
                'fix' => 'Echo back only origins from an allowlist, or configure nelmio/cors-bundle with explicit origins.',
            ],
            [
                'pattern' => '/header\s*\(\s*["\']Location:\s*["\']?\s*\.\s*\$_(GET|POST|REQUEST)|new\s+RedirectResponse\s*\(\s*\$request->(query|request|get)\b|->redirect\s*\(\s*\$request->(query|request|get)\b/',
                'rule_id' => 'checkmycode.php.raw-open-redirect',
                'title' => 'Redirect target taken straight from the request',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Redirect to generated routes ($this->redirectToRoute()) or validate the target against an allowlist.',
            ],
            [
                'pattern' => '/move_uploaded_file\s*\(.*\$_FILES\[[^\]]+\]\[["\']name["\']\]/',
                'rule_id' => 'checkmycode.php.upload-client-filename',
                'title' => 'Upload saved under the client-supplied filename',
                'severity' => 'high',
                'category' => 'file-upload',
                'why' => 'The client controls the name, so it can use ../ or a .php extension to plant an executable file.',
                'fix' => 'Generate the filename server-side, check the MIME type with finfo, and store uploads outside the web root.',
            ],
            [
                'pattern' => '/setcookie\s*\(/',
                'unless' => '/["\']httponly["\']\s*=>\s*true|,\s*true\s*,\s*true\s*\)/i',
                'rule_id' => 'checkmycode.php.insecure-cookie',
                'title' => 'setcookie() without Secure and HttpOnly',
                'severity' => 'low',
                'category' => 'session',
                'why' => 'Cookies without HttpOnly can be read by injected scripts; without Secure they are sent over plain HTTP.',
                'fix' => 'Pass options: setcookie($name, $value, ["secure" => true, "httponly" => true, "samesite" => "Lax"]).',
            ],

            // Symfony
            [
                'pattern' => '/\|\s*raw\b|\{%\s*autoescape\s+false/',
                'extensions' => ['twig'],
                'rule_id' => 'checkmycode.symfony.twig-raw',
                'title' => 'Twig output printed with |raw',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'The |raw filter disables escaping; user-controlled values become XSS.',
                'fix' => 'Drop |raw, or sanitize HTML first (e.g. symfony/html-sanitizer with |sanitize_html).',
            ],
            [
                'pattern' => '/^\s*csrf_protection\s*:\s*false\b/',
                'extensions' => ['yaml', 'yml'],
                'paths' => '#(^|/)config/#',
                'rule_id' => 'checkmycode.symfony.csrf-disabled',
                'title' => 'Symfony CSRF protection disabled',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'Forms without CSRF tokens can be submitted by any site the user visits.',
                'fix' => 'Enable framework.csrf_protection and keep it on for browser forms.',
            ],
            [
                'pattern' => '/^\s*-\s*\{\s*path:\s*[\'"]?\^\/(admin|api)[^,}]*,\s*roles:\s*PUBLIC_ACCESS/',
                'extensions' => ['yaml', 'yml'],
                'paths' => '#security\.ya?ml$#',
                'rule_id' => 'checkmycode.symfony.admin-public-access',
                'title' => 'Admin or API path open to the public',
                'severity' => 'high',
                'category' => 'authorization',
                'why' => 'An access_control rule grants PUBLIC_ACCESS to an /admin or /api prefix.',
                'fix' => 'Require ROLE_ADMIN (or an API role) on the prefix and open only the specific public routes.',
            ],
            [
                'pattern' => '/\$this->renderView\s*\(\s*\$request|createTemplate\s*\(\s*\$/',
                'rule_id' => 'checkmycode.symfony.template-injection',
                'title' => 'Template name or source taken from a variable',
                'severity' => 'high',
                'category' => 'injection',
                'why' => 'Rendering a user-controlled template (or Twig source) is server-side template injection.',
                'fix' => 'Render fixed template names and pass user values as variables.',
            ],
        ];
    }
}
