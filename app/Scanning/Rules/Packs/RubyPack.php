<?php

namespace App\Scanning\Rules\Packs;

use App\Scanning\ProjectDetector;

/**
 * Ruby and Rails.
 */
class RubyPack extends RulePack
{
    public function appliesTo(string $projectType): bool
    {
        return ProjectDetector::isRuby($projectType);
    }

    public function extensions(): array
    {
        return ['rb', 'rake', 'erb'];
    }

    public function rules(): array
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
                // Only commands a shell parses from a built string: interpolated,
                // concatenated or passed in a variable. system("git", "status") and
                // literal commands are safe, and conn.exec(sql) is a database call.
                'pattern' => '/(?<![.\w])(Kernel\.)?(system|exec|spawn)(\s*\(\s*|\s+)("[^"]*#\{|["\'][^"\']*["\']\s*\+|[a-z_@][\w.@\[\]:"\']*\s*(\)|$))|\b(Open3\.(capture2e?|capture3|popen2e?|popen3|pipeline\w*)|IO\.popen)\s*\(?\s*("[^"]*#\{|["\'][^"\']*["\']\s*\+|[a-z_@][\w.@\[\]:"\']*\s*(\)|$))|`[^`]*#\{[^`]*`|%x[\(\{\[][^\)\}\]]*#\{/',
                'skip' => '#(^|/)(lib/tasks|bin|script|scripts|db/seeds|config)/|(^|/)(Rakefile|Gemfile|Guardfile|Capfile)$|\.(rake|gemspec)$#',
                'rule_id' => 'checkmycode.ruby.shell-execution',
                'title' => 'Shell command built from a dynamic string',
                'severity' => 'high',
                'category' => 'command-injection',
                'why' => 'The shell parses the whole string, so an interpolated or concatenated value can append extra commands (command injection).',
                'fix' => 'Pass the program and arguments separately, e.g. system("convert", input, output) or Open3.capture3("git", "log", ref).',
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
            [
                'pattern' => '/\.(find_by_sql|order|reorder|group|having|joins|pluck|select|from|exists\?|count_by_sql|delete_all|update_all|execute|exec_query)\s*\(\s*["\'][^"\']*#\{/',
                'rule_id' => 'checkmycode.rails.sql-injection-raw',
                'title' => 'Raw SQL fragment built with string interpolation',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Interpolating values into order/group/joins/find_by_sql or a raw execute puts them straight into the SQL text.',
                'fix' => 'Use bind parameters (find_by_sql(["... = ?", v])), hash conditions, or Arel; allowlist column names for order().',
            ],
            [
                'pattern' => '/\.html_safe\b|(?<![.:\w])raw\s*\(|<%==/',
                // Not flagged: fixed string literals, translations without arguments,
                // yield/content_for blocks and values that already went through sanitize.
                'unless' => '/(["\'][^"\'#]*["\']|\bt\(\s*["\'][^"\']*["\']\s*\)|\b(yield|content_for)\([^)]*\)|\b(sanitize|safe_join|strip_tags)\([^)]*\))\.html_safe\b|(?<![.:\w])raw\s*\(\s*(["\'][^"\'#]*["\']\s*\)|sanitize\(|t\(\s*["\'][^"\']*["\']\s*\)\s*\))/',
                'rule_id' => 'checkmycode.rails.html-safe',
                'title' => 'Output marked html_safe / raw (escaping disabled)',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'html_safe, raw() and <%== print values without escaping; user content there becomes XSS.',
                'fix' => 'Let ERB escape the value, or use sanitize(value) / content_tag to build markup.',
            ],
            [
                'pattern' => '/skip_before_action\s+:verify_authenticity_token|skip_forgery_protection|protect_from_forgery\s+with:\s*:null_session/',
                'rule_id' => 'checkmycode.rails.csrf-disabled',
                'title' => 'CSRF protection disabled',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'Without CSRF checks, any site the user visits can submit forms to your app on their behalf.',
                'fix' => 'Keep protect_from_forgery with: :exception for browser controllers; use token auth only for real API controllers.',
            ],
            [
                'pattern' => '/redirect_to\s*\(?\s*(params\[|params\.|request\.referr?er|request\.(query|request)_parameters)/',
                'rule_id' => 'checkmycode.rails.open-redirect',
                'title' => 'Redirect target taken straight from the request',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Redirect to named routes, or use redirect_to(url, allow_other_host: false) (Rails 7+).',
            ],
            [
                'pattern' => '/\b(send_file|send_data|File\.(read|open|new)|IO\.read)\s*\(?\s*(params\[|params\.|"[^"]*#\{params)/',
                'rule_id' => 'checkmycode.rails.path-traversal',
                'title' => 'File path built from request params',
                'severity' => 'high',
                'category' => 'path-traversal',
                'why' => 'Request-controlled paths can use ../ to read config/master.key, .env or other files.',
                'fix' => 'Look files up by id from the database or an allowlist; never pass params into file paths.',
            ],
            [
                'pattern' => '/params\[[^\]]+\]\.(constantize|safe_constantize)|\.(send|public_send|try)\s*\(\s*params\[/',
                'rule_id' => 'checkmycode.rails.dynamic-dispatch',
                'title' => 'Class or method chosen from request params',
                'severity' => 'high',
                'category' => 'code-execution',
                'why' => 'constantize/send with user input lets attackers instantiate arbitrary classes or call arbitrary methods.',
                'fix' => 'Map the param to an explicit allowlist of classes or method names.',
            ],
            [
                'pattern' => '/\bYAML\.load\s*\(|\bPsych\.load\s*\(|\bYAML\.unsafe_load\s*\(/',
                'unless' => '/safe_load|permitted_classes/',
                'rule_id' => 'checkmycode.ruby.yaml-unsafe-load',
                'title' => 'YAML.load on possibly untrusted data',
                'severity' => 'high',
                'category' => 'deserialization',
                'why' => 'YAML.load (Psych < 4) and unsafe_load can instantiate arbitrary Ruby objects, a known code-execution gadget.',
                'fix' => 'Use YAML.safe_load(data, permitted_classes: [...]).',
            ],
            [
                'pattern' => '/Digest::(MD5|SHA1)\b|OpenSSL::Digest::(MD5|SHA1)\b/',
                'rule_id' => 'checkmycode.ruby.weak-hash',
                'title' => 'Weak hashing algorithm (MD5/SHA-1)',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are broken for security use and cheap to brute-force.',
                'fix' => 'Use Digest::SHA256; for passwords use has_secure_password (bcrypt).',
            ],
            [
                'pattern' => '/OpenSSL::SSL::VERIFY_NONE|verify_mode\s*[:=]\s*0\b|ssl_verify_mode:\s*:none/',
                'rule_id' => 'checkmycode.ruby.tls-verification-disabled',
                'title' => 'TLS certificate verification disabled',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Without certificate checks, anyone on the network path can intercept and modify HTTPS traffic.',
                'fix' => 'Use OpenSSL::SSL::VERIFY_PEER and fix the trust store instead.',
            ],
            [
                'pattern' => '/\b(httponly|secure)\s*:\s*false\b/',
                'rule_id' => 'checkmycode.rails.insecure-cookie',
                'title' => 'Cookie set without HttpOnly or Secure',
                'severity' => 'medium',
                'category' => 'session',
                'why' => 'Without HttpOnly, injected scripts can read the cookie; without Secure, it is sent over plain HTTP.',
                'fix' => 'Set httponly: true and secure: Rails.env.production? on session and auth cookies.',
            ],
            [
                'pattern' => '/\borigins\s+["\']\*["\']/',
                'rule_id' => 'checkmycode.rails.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your API from a visitor\'s browser and read the responses.',
                'fix' => 'In config/initializers/cors.rb list your real frontend origins instead of "*".',
            ],
            [
                'pattern' => '/config\.force_ssl\s*=\s*false/',
                'paths' => '#config/environments/production\.rb$#',
                'rule_id' => 'checkmycode.rails.force-ssl-disabled',
                'title' => 'force_ssl disabled in production',
                'severity' => 'medium',
                'category' => 'transport-security',
                'why' => 'Without force_ssl, cookies and credentials can travel over plain HTTP and HSTS is not sent.',
                'fix' => 'Set config.force_ssl = true in config/environments/production.rb.',
            ],
            [
                'pattern' => '/config\.consider_all_requests_local\s*=\s*true/',
                'paths' => '#config/environments/production\.rb$#',
                'rule_id' => 'checkmycode.rails.debug-in-production',
                'title' => 'Detailed error pages enabled in production',
                'severity' => 'critical',
                'category' => 'configuration',
                'why' => 'consider_all_requests_local shows full stack traces, params and environment data to every visitor.',
                'fix' => 'Set config.consider_all_requests_local = false in production.',
            ],
            [
                'pattern' => '/secret_key_base\s*[=:]\s*["\'][0-9a-f]{20,}["\']/i',
                'rule_id' => 'checkmycode.rails.hardcoded-secret-key-base',
                'title' => 'secret_key_base hardcoded in source',
                'severity' => 'critical',
                'category' => 'secret',
                'why' => 'secret_key_base signs and encrypts cookies; with it anyone can forge sessions.',
                'fix' => 'Use Rails credentials or ENV["SECRET_KEY_BASE"], and rotate the committed key.',
            ],
            [
                'pattern' => '/\battr_accessible\b.*:(admin|role|is_admin|superuser)\b|permit\s*\(.*:(admin|role|is_admin|superuser)\b/',
                'rule_id' => 'checkmycode.rails.privileged-attribute-permitted',
                'title' => 'Privileged attribute is mass-assignable',
                'severity' => 'high',
                'category' => 'mass-assignment',
                'why' => 'Permitting admin or role in strong parameters lets users promote themselves.',
                'fix' => 'Remove privileged attributes from permit() and set them only from trusted admin code.',
            ],
            [
                'pattern' => '/^\s*def\s+create\b/',
                'paths' => '#app/controllers/(.*/)?(sessions?|logins?|passwords?|password_resets|registrations|sign_?ups?|auth|authentications?)_controller\.rb$#',
                'unless_file' => '/rate_limit|throttle|Rack::Attack|rack_attack/',
                'rule_id' => 'checkmycode.rails.auth-missing-rate-limit',
                'title' => 'Authentication action without rate limiting',
                'severity' => 'medium',
                'category' => 'brute-force',
                'why' => 'Login, signup and password-reset actions without a rate limit can be brute-forced.',
                'fix' => 'Use Rails 7.2+ rate_limit to: 5, within: 1.minute, or configure Rack::Attack throttles.',
            ],
        ];
    }
}
