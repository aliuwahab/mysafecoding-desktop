<?php

namespace App\Scanning\Rules\Packs;

use App\Scanning\ProjectDetector;

/**
 * Elixir and Phoenix, including HEEx/EEx templates and config files.
 */
class ElixirPack extends RulePack
{
    protected const TEMPLATES = ['heex', 'eex', 'leex'];

    public function appliesTo(string $projectType): bool
    {
        return ProjectDetector::isElixir($projectType);
    }

    public function extensions(): array
    {
        return ['ex', 'exs'];
    }

    public function rules(): array
    {
        return [
            [
                'pattern' => '/\bCode\.(eval_string|eval_quoted|compile_string)\s*\(/',
                'rule_id' => 'checkmycode.elixir.code-eval',
                'title' => 'Code evaluated at runtime',
                'severity' => 'critical',
                'category' => 'code-execution',
                'why' => 'Evaluating a string or AST built from user input runs arbitrary Elixir on the server.',
                'fix' => 'Remove runtime evaluation; dispatch to known functions through pattern matching instead.',
            ],
            [
                'pattern' => '/:erlang\.binary_to_term\s*\(|Plug\.Crypto\.non_executable_binary_to_term\s*\([^,)]*\)/',
                'unless' => '/\[\s*:safe\s*\]/',
                'rule_id' => 'checkmycode.elixir.unsafe-binary-to-term',
                'title' => 'binary_to_term without :safe',
                'severity' => 'critical',
                'category' => 'deserialization',
                'why' => 'Decoding untrusted Erlang terms can create atoms (exhausting the atom table) or executable funs.',
                'fix' => 'Use Plug.Crypto.non_executable_binary_to_term(bin, [:safe]), or JSON for untrusted data.',
            ],
            [
                'pattern' => '/\bString\.to_atom\s*\(|\bList\.to_atom\s*\(|:erlang\.binary_to_atom\s*\(/',
                'rule_id' => 'checkmycode.elixir.atom-exhaustion',
                'title' => 'Atoms created from dynamic strings',
                'severity' => 'medium',
                'category' => 'denial-of-service',
                'why' => 'Atoms are never garbage collected; creating them from user input can crash the VM once the atom table fills.',
                'fix' => 'Use String.to_existing_atom/1, or map input to atoms with an explicit allowlist.',
            ],
            [
                'pattern' => '/System\.(cmd|shell)\s*\(\s*"(sh|bash|zsh)"|System\.shell\s*\(|:os\.cmd\s*\(/',
                'rule_id' => 'checkmycode.elixir.shell-command',
                'title' => 'Command run through a shell',
                'severity' => 'high',
                'category' => 'command-injection',
                'why' => 'System.shell, :os.cmd and sh -c parse a command string, so interpolated values can inject extra commands.',
                'fix' => 'Use System.cmd("program", [arg1, arg2]) with an argument list.',
            ],
            [
                'pattern' => '/(Repo\.query!?|Ecto\.Adapters\.SQL\.query!?|SQL\.query!?)\s*\(\s*(\w+\s*,\s*)?"[^"]*#\{|fragment\s*\(\s*"[^"]*#\{/',
                'rule_id' => 'checkmycode.elixir.sql-injection',
                'title' => 'SQL built with string interpolation',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Interpolating into raw SQL or fragment() bypasses Ecto\'s parameter binding.',
                'fix' => 'Use placeholders: Repo.query("... WHERE id = $1", [id]) or fragment("lower(?)", ^value).',
            ],
            [
                'pattern' => '/\braw\s*\(/',
                'extensions' => self::TEMPLATES,
                'rule_id' => 'checkmycode.phoenix.raw-html',
                'title' => 'Raw HTML output (escaping disabled)',
                'severity' => 'high',
                'skip' => '#(^|/)deps/#',
                'category' => 'xss',
                'why' => 'raw/1 renders a value without escaping; user content there becomes XSS.',
                'fix' => 'Render the value normally, or sanitize it with HtmlSanitizeEx first.',
            ],
            [
                'pattern' => '/redirect\s*\(\s*conn\s*,\s*external:\s*(params|conn\.params|conn\.query_params|\w+\[)/',
                'rule_id' => 'checkmycode.phoenix.open-redirect',
                'title' => 'Redirect target taken from request params',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied external URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Use redirect(conn, to: ~p"/path") for internal paths, or validate the host against an allowlist.',
            ],
            [
                'pattern' => '/(send_file|send_download|File\.read!?|File\.stream!)\s*\(.*(params\[|conn\.params|\bparams\b)/',
                'unless' => '/Path\.basename|Path\.safe_relative/',
                'rule_id' => 'checkmycode.phoenix.path-traversal',
                'title' => 'File path built from request params',
                'severity' => 'high',
                'category' => 'path-traversal',
                'why' => 'Request-controlled paths can use ../ to read files outside the intended directory.',
                'fix' => 'Use Path.safe_relative/2 (Elixir 1.14+) or look files up by id.',
            ],
            [
                'pattern' => '/:crypto\.hash\s*\(\s*:(md5|sha)\b|:crypto\.(md5|sha)\s*\(/',
                'rule_id' => 'checkmycode.elixir.weak-hash',
                'title' => 'Weak hashing algorithm (MD5/SHA-1)',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are broken for security use and cheap to brute-force.',
                'fix' => 'Use :crypto.hash(:sha256, data); for passwords use Bcrypt or Argon2 (via phx.gen.auth).',
            ],
            [
                'pattern' => '/check_origin:\s*false/',
                'skip' => '#(^|/)config/(dev|test)\.exs$#',
                'rule_id' => 'checkmycode.phoenix.check-origin-disabled',
                'title' => 'WebSocket origin check disabled',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'Without check_origin any website can open a LiveView or channel socket as the visiting user (cross-site WebSocket hijacking).',
                'fix' => 'Remove check_origin: false, or list the allowed origins explicitly.',
            ],
            [
                'pattern' => '/origins?:\s*("\*"|\["\*"\]|~r\/\.\*\/)/',
                'skip' => '#(^|/)config/(dev|test)\.exs$#',
                'rule_id' => 'checkmycode.phoenix.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your API from a visitor\'s browser and read the responses.',
                'fix' => 'List the real frontend origins in the CORSPlug / Corsica configuration.',
            ],
            [
                'pattern' => '/(secure|http_only):\s*false/',
                'skip' => '#(^|/)config/(dev|test)\.exs$#',
                'rule_id' => 'checkmycode.phoenix.insecure-cookie',
                'title' => 'Cookie set without Secure or HttpOnly',
                'severity' => 'medium',
                'category' => 'session',
                'why' => 'Without http_only, injected scripts can read the cookie; without secure, it is sent over plain HTTP.',
                'fix' => 'Keep http_only: true and set secure: true in the session and put_resp_cookie options.',
            ],
            [
                'pattern' => '/debug_errors:\s*true|code_reloader:\s*true/',
                'paths' => '#(^|/)config/(prod|runtime)\.exs$#',
                'rule_id' => 'checkmycode.phoenix.debug-in-production',
                'title' => 'Debug setting enabled in production config',
                'severity' => 'high',
                'category' => 'configuration',
                'why' => 'debug_errors shows stack traces and request details to every visitor; code_reloader is for development only.',
                'fix' => 'Keep these settings in config/dev.exs only.',
            ],
            [
                'pattern' => '/(secret_key_base|signing_salt|encryption_salt|live_view:\s*\[\s*signing_salt):\s*"[^"]{8,}"/',
                'paths' => '#(^|/)config/(prod|runtime|config)\.exs$#',
                'rule_id' => 'checkmycode.phoenix.hardcoded-secret',
                'title' => 'Secret key or salt hardcoded in config',
                'severity' => 'critical',
                'category' => 'secret',
                'why' => 'secret_key_base signs and encrypts sessions; with it anyone can forge cookies.',
                'fix' => 'Read it in config/runtime.exs: secret_key_base: System.fetch_env!("SECRET_KEY_BASE"), and rotate it.',
            ],
            [
                'pattern' => '/pipeline\s+:browser\s+do/',
                'unless_file' => '/plug\s+:protect_from_forgery/',
                'paths' => '#router\.ex$#',
                'rule_id' => 'checkmycode.phoenix.csrf-missing',
                'title' => 'Browser pipeline without CSRF protection',
                'severity' => 'high',
                'category' => 'csrf',
                'why' => 'The :browser pipeline does not plug :protect_from_forgery, so forms and LiveView are open to CSRF.',
                'fix' => 'Add plug :protect_from_forgery to the :browser pipeline (it is in the Phoenix generator by default).',
            ],
            [
                'pattern' => '/(post|live)\s+"[^"]*(log_in|login|sign_in|signin|register|reset_password|users\/reset)[^"]*"/',
                'paths' => '#router\.ex$#',
                'unless_file' => '/Hammer|PlugAttack|ExRated|RateLimit|rate_limit/',
                'rule_id' => 'checkmycode.phoenix.auth-missing-rate-limit',
                'title' => 'Authentication route without rate limiting',
                'severity' => 'medium',
                'category' => 'brute-force',
                'why' => 'Login, registration and password-reset routes without a rate limit can be brute-forced.',
                'fix' => 'Add a rate-limiting plug (Hammer or PlugAttack) to the authentication pipeline.',
            ],
        ];
    }
}
