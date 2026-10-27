<?php

namespace App\Scanning\Rules\Packs;

/**
 * Rust: actix-web, axum, rocket, sqlx, diesel, reqwest.
 */
class RustPack extends RulePack
{
    public function appliesTo(string $projectType): bool
    {
        return $projectType === 'rust';
    }

    public function extensions(): array
    {
        return ['rs'];
    }

    public function rules(): array
    {
        return [
            [
                'pattern' => '/(query|query_as|query_scalar|execute|sql_query|raw_sql|batch_execute|prepare)(::<[^>]*>)?\s*\(\s*&?format!\s*\(|sqlx::query\w*\s*\(\s*&?\w+\s*\.as_str\(\)\s*\)/',
                'rule_id' => 'checkmycode.rust.sql-injection',
                'title' => 'SQL query built with format!',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'format! splices values straight into the SQL text, bypassing the driver\'s parameter binding.',
                'fix' => 'Use bind parameters: sqlx::query("... WHERE id = $1").bind(id), or the query! macros.',
            ],
            [
                'pattern' => '/Command::new\s*\(\s*"(sh|bash|zsh|cmd|cmd\.exe|powershell)"\s*\)/',
                'rule_id' => 'checkmycode.rust.shell-command',
                'title' => 'Command run through a shell',
                'severity' => 'high',
                'category' => 'command-injection',
                'why' => 'Running sh -c with a built string lets interpolated values inject extra commands.',
                'fix' => 'Call the program directly: Command::new("convert").arg(input).arg(output).',
            ],
            [
                'pattern' => '/danger_accept_invalid_certs\s*\(\s*true\s*\)|danger_accept_invalid_hostnames\s*\(\s*true\s*\)|dangerous\(\)\s*\.\s*set_certificate_verifier|SslVerifyMode::NONE|set_verify\s*\(\s*SslVerifyMode::NONE/',
                'rule_id' => 'checkmycode.rust.tls-verification-disabled',
                'title' => 'TLS certificate verification disabled',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Accepting invalid certificates lets anyone on the network path intercept HTTPS traffic.',
                'fix' => 'Remove the danger_* call and add the correct root certificate with add_root_certificate().',
            ],
            [
                'pattern' => '/\bMd5::new\s*\(|\bmd5::compute\s*\(|\bSha1::new\s*\(|\bsha1::Sha1\b|Md5::digest\s*\(|Sha1::digest\s*\(/',
                'rule_id' => 'checkmycode.rust.weak-hash',
                'title' => 'Weak hashing algorithm (MD5/SHA-1)',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are broken for security use and cheap to brute-force.',
                'fix' => 'Use sha2::Sha256; for passwords use the argon2 or bcrypt crates.',
            ],
            [
                'pattern' => '/CorsLayer::permissive\s*\(|CorsLayer::very_permissive\s*\(|Cors::permissive\s*\(|\.allow_any_origin\s*\(\s*\)|allow_origin\s*\(\s*(Any|AllowOrigin::any\(\))\s*\)/',
                'rule_id' => 'checkmycode.rust.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your API from a visitor\'s browser and read the responses.',
                'fix' => 'Configure explicit origins, e.g. CorsLayer::new().allow_origin("https://app.example".parse::<HeaderValue>()?).',
            ],
            [
                'pattern' => '/PreEscaped\s*\(|\|\s*safe\b|Html\s*\(\s*format!/',
                'extensions' => ['rs', 'html', 'jinja', 'tera', 'hbs'],
                'rule_id' => 'checkmycode.rust.unescaped-html',
                'title' => 'HTML output with escaping disabled',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'PreEscaped, |safe and format!-built Html responses render values as raw HTML; user input becomes XSS.',
                'fix' => 'Let the template engine escape values, or sanitize with the ammonia crate first.',
            ],
            [
                'pattern' => '/\b(NamedFile::open|File::open|fs::read|fs::read_to_string|tokio::fs::read|ServeFile::new)\s*\(\s*(format!\s*\([^)]*(path|query|params|req)\b|&?\s*(path|query|params)\.)/',
                'unless' => '/canonicalize|starts_with/',
                'rule_id' => 'checkmycode.rust.path-traversal',
                'title' => 'File path built from request input',
                'severity' => 'high',
                'category' => 'path-traversal',
                'why' => 'Request-controlled paths can use ../ to read files outside the intended directory.',
                'fix' => 'Canonicalize the joined path and check it starts_with the root directory before opening it.',
            ],
            [
                'pattern' => '/Validation\s*\{[^}]*validate_exp\s*:\s*false|insecure_disable_signature_validation\s*\(\s*\)|dangerous_insecure_decode/',
                'rule_id' => 'checkmycode.rust.jwt-not-verified',
                'title' => 'JWT accepted without full verification',
                'severity' => 'critical',
                'category' => 'authentication',
                'why' => 'Skipping signature or expiry checks lets forged or expired tokens through.',
                'fix' => 'Use jsonwebtoken::decode with a Validation that keeps signature and exp checks on.',
            ],
            [
                'pattern' => '/\b\w*(token|secret|password|otp|nonce|salt|key|code)\w*\s*(:\s*\w+\s*)?=.*\b(thread_rng|rand::random|SmallRng|StdRng::seed_from_u64)\b/i',
                'rule_id' => 'checkmycode.rust.insecure-random',
                'title' => 'Security value from a non-cryptographic RNG',
                'severity' => 'low',
                'category' => 'cryptography',
                'why' => 'Seeded or small RNGs are predictable; values generated with them can be guessed.',
                'fix' => 'Use rand::rngs::OsRng or getrandom for tokens and keys.',
            ],
            [
                'pattern' => '/std::mem::transmute|mem::transmute\s*(::<|\()/',
                'rule_id' => 'checkmycode.rust.transmute',
                'title' => 'mem::transmute used',
                'severity' => 'low',
                'category' => 'memory-safety',
                'why' => 'transmute reinterprets raw bits with no checks; a wrong type or size is undefined behaviour.',
                'fix' => 'Prefer safe conversions (from_ne_bytes, as, bytemuck) and document the invariants if transmute is required.',
            ],
            [
                'pattern' => '/^\s*(pub\s+)?unsafe\s+(fn|impl)\b|\bunsafe\s*\{/',
                'rule_id' => 'checkmycode.rust.unsafe-block',
                'title' => 'unsafe code',
                'severity' => 'info',
                'category' => 'memory-safety',
                'why' => 'unsafe blocks opt out of Rust\'s memory-safety guarantees and need careful review.',
                'fix' => 'Keep unsafe minimal, wrap it in a safe API and add a // SAFETY: comment explaining why it is sound.',
            ],
        ];
    }
}
