<?php

namespace App\Scanning\Rules\Packs;

/**
 * Go: net/http, Gin, Echo, Fiber.
 */
class GoPack extends RulePack
{
    public function appliesTo(string $projectType): bool
    {
        return $projectType === 'go';
    }

    public function extensions(): array
    {
        return ['go'];
    }

    public function rules(): array
    {
        return [
            [
                'pattern' => '/exec\.Command\s*\(/',
                'unless' => '/"(sh|bash|zsh|cmd|cmd\.exe|powershell)"\s*,\s*"(-c|\/c|-Command)"/',
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
            [
                'pattern' => '/exec\.Command(Context)?\s*\(\s*(ctx\s*,\s*)?["\'](sh|bash|zsh|cmd|cmd\.exe|powershell)["\']\s*,\s*["\'](-c|\/c|-Command)["\']/',
                'rule_id' => 'checkmycode.go.shell-command',
                'title' => 'Command run through a shell',
                'severity' => 'high',
                'category' => 'command-injection',
                'why' => 'Passing a string to sh -c lets any interpolated value inject extra commands.',
                'fix' => 'Call the program directly with separate arguments: exec.Command("convert", in, out).',
            ],
            [
                'pattern' => '/\.(Query|QueryRow|Exec|QueryContext|QueryRowContext|ExecContext|Raw|Where)\s*\((ctx\s*,\s*)?\s*("[^"]*"\s*\+|`[^`]*`\s*\+)/',
                'rule_id' => 'checkmycode.go.sql-string-concat',
                'title' => 'SQL query built with concatenation',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Concatenated SQL bypasses parameter binding and allows SQL injection.',
                'fix' => 'Use placeholders: db.Query("SELECT ... WHERE id = $1", id) / gorm Where("id = ?", id).',
            ],
            [
                'pattern' => '/sha1\.New\(\)|sha1\.Sum\(|des\.NewCipher\(|rc4\.NewCipher\(/',
                'rule_id' => 'checkmycode.go.weak-crypto',
                'title' => 'Weak hash or cipher (SHA-1, DES, RC4)',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'SHA-1, DES and RC4 are broken for security use.',
                'fix' => 'Use crypto/sha256 for hashing and crypto/aes with GCM for encryption.',
            ],
            [
                'pattern' => '/\brand\.(Int|Intn|Int31|Int63|Read|Uint32|Uint64)\s*\(/',
                'when_file' => '/"math\/rand(\/v2)?"/',
                'unless_file' => '/"crypto\/rand"/',
                'rule_id' => 'checkmycode.go.insecure-random',
                'title' => 'math/rand used where crypto/rand may be needed',
                'severity' => 'low',
                'category' => 'cryptography',
                'why' => 'math/rand is predictable; tokens, keys or codes generated with it can be guessed.',
                'fix' => 'Use crypto/rand (rand.Read or rand.Text) for anything security-sensitive.',
            ],
            [
                'pattern' => '/template\.(HTML|JS|URL|HTMLAttr|CSS)\s*\(/',
                'rule_id' => 'checkmycode.go.template-unescaped',
                'title' => 'html/template escaping bypassed',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'template.HTML and friends mark content as trusted, so user input in them becomes XSS.',
                'fix' => 'Pass plain strings to the template and let html/template escape them, or sanitize with bluemonday first.',
            ],
            [
                'pattern' => '/"text\/template"/',
                'when_file' => '/net\/http|gin-gonic|labstack\/echo|gofiber/',
                'rule_id' => 'checkmycode.go.text-template-for-html',
                'title' => 'text/template used in a web handler',
                'severity' => 'medium',
                'category' => 'xss',
                'why' => 'text/template does no HTML escaping; rendering user input with it into a page is XSS.',
                'fix' => 'Use html/template for anything that ends up in an HTTP response.',
            ],
            [
                'pattern' => '/http\.Redirect\s*\(\s*\w+\s*,\s*\w+\s*,\s*(r\.(URL\.Query\(\)\.Get|FormValue|PostFormValue)|c\.(Query|PostForm|Param|QueryParam|FormValue))/',
                'rule_id' => 'checkmycode.go.open-redirect',
                'title' => 'Redirect target taken straight from the request',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Only allow relative paths or check the parsed URL host against an allowlist.',
            ],
            [
                'pattern' => '/\.Redirect\s*\(\s*(http\.Status\w+|\d{3})\s*,\s*c\.(Query|PostForm|Param|QueryParam|FormValue)\s*\(/',
                'rule_id' => 'checkmycode.go.framework-open-redirect',
                'title' => 'Redirect target taken straight from the request',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Only allow relative paths or check the parsed URL host against an allowlist.',
            ],
            [
                'pattern' => '/(http\.ServeFile|os\.Open|os\.ReadFile|ioutil\.ReadFile|c\.File|c\.Attachment|c\.SendFile)\s*\(.*(r\.URL\.(Query|Path)|r\.FormValue|c\.(Query|Param|PostForm|QueryParam|FormValue|Params))/',
                'unless' => '/filepath\.(Clean|Base)|securejoin/',
                'rule_id' => 'checkmycode.go.path-traversal',
                'title' => 'File path built from request input',
                'severity' => 'high',
                'category' => 'path-traversal',
                'why' => 'Request-controlled paths can use ../ to read files outside the intended directory.',
                'fix' => 'Use filepath.Base on the name, or join with a root and verify the result stays inside it (os.Root in Go 1.24+).',
            ],
            [
                'pattern' => '/AllowedOrigins\s*:\s*\[\]string\{\s*"\*"|AllowOrigins\s*:\s*(\[\]string\{\s*)?"\*"|AllowAllOrigins\s*:\s*true|"Access-Control-Allow-Origin"\s*,\s*"\*"/',
                'rule_id' => 'checkmycode.go.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your API from a visitor\'s browser and read the responses.',
                'fix' => 'List the real frontend origins in AllowedOrigins / AllowOrigins.',
            ],
            [
                'pattern' => '/\b(HttpOnly|Secure)\s*:\s*false\b/',
                'rule_id' => 'checkmycode.go.insecure-cookie',
                'title' => 'Cookie set without HttpOnly or Secure',
                'severity' => 'medium',
                'category' => 'session',
                'why' => 'Without HttpOnly, injected scripts can read the cookie; without Secure, it is sent over plain HTTP.',
                'fix' => 'Set HttpOnly: true, Secure: true and SameSite: http.SameSiteLaxMode on auth cookies.',
            ],
            [
                'pattern' => '/SigningMethodNone|UnsafeAllowNoneSignatureType|jwt\.ParseUnverified\s*\(|WithoutClaimsValidation\s*\(/',
                'rule_id' => 'checkmycode.go.jwt-not-verified',
                'title' => 'JWT accepted without signature verification',
                'severity' => 'critical',
                'category' => 'authentication',
                'why' => 'Unsigned or unverified tokens let anyone forge an identity.',
                'fix' => 'Use jwt.Parse with a keyfunc that checks token.Method against the expected algorithm.',
            ],
            [
                'pattern' => '/gin\.SetMode\s*\(\s*gin\.DebugMode\s*\)/',
                'rule_id' => 'checkmycode.go.gin-debug-mode',
                'title' => 'Gin forced into debug mode',
                'severity' => 'medium',
                'category' => 'configuration',
                'why' => 'Debug mode logs every route and request detail and is not meant for production.',
                'fix' => 'Remove the call and set GIN_MODE=release in production.',
            ],
            [
                'pattern' => '/_\s+"net\/http\/pprof"/',
                'rule_id' => 'checkmycode.go.pprof-exposed',
                'title' => 'net/http/pprof imported',
                'severity' => 'medium',
                'category' => 'information-disclosure',
                'why' => 'The blank pprof import registers /debug/pprof on the default mux, exposing memory, goroutines and command line.',
                'fix' => 'Serve pprof only on a separate, internal-only listener, or remove it from production builds.',
            ],
            [
                'pattern' => '/http\.ListenAndServe\s*\(/',
                'unless_file' => '/ReadHeaderTimeout|ReadTimeout/',
                'rule_id' => 'checkmycode.go.http-server-no-timeouts',
                'title' => 'HTTP server started without timeouts',
                'severity' => 'low',
                'category' => 'denial-of-service',
                'why' => 'http.ListenAndServe has no read timeouts, so slow clients (Slowloris) can exhaust connections.',
                'fix' => 'Use an http.Server{ReadHeaderTimeout: 5 * time.Second, ...} and call its ListenAndServe.',
            ],
        ];
    }
}
