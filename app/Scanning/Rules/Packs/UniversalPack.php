<?php

namespace App\Scanning\Rules\Packs;

/**
 * Cross-language secret rules, run on every project.
 */
class UniversalPack extends RulePack
{
    public function appliesTo(string $projectType): bool
    {
        return true;
    }

    public function extensions(): array
    {
        return ['php', 'py', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'rb', 'go', 'rs', 'java', 'kt', 'kts', 'cs', 'ex', 'exs', 'env', 'yml', 'yaml', 'json', 'toml', 'properties'];
    }

    public function rules(): array
    {
        return [
            [
                'pattern' => '/["\'](?:sk_live_[a-zA-Z0-9]{20,}|AKIA[0-9A-Z]{16}|ghp_[a-zA-Z0-9]{36}|glpat-[a-zA-Z0-9\-_]{20,}|xoxb-[0-9a-zA-Z]{24,}|ya29\.[0-9A-Za-z\-_]{60,})["\']/',
                'rule_id' => 'checkmycode.hardcoded-credential',
                'title' => 'Hardcoded credential detected in source',
                'severity' => 'critical',
                'category' => 'secret',
                'include_tests' => true,
                'why' => 'Credentials committed to source control are exposed to repository readers and remain recoverable from git history.',
                'fix' => 'Move the secret to environment-backed configuration and rotate the exposed credential immediately.',
            ],
            [
                'pattern' => '/(?:password|passwd|secret|token|api_?key)\s*=\s*["\'][^"\']{8,}["\']/',
                'unless' => '/=\s*["\'][^"\']*(<%|\$\{|\{\{|#\{)/',
                'rule_id' => 'checkmycode.plaintext-secret-assignment',
                'title' => 'Plaintext secret assignment detected',
                'severity' => 'high',
                'category' => 'secret',
                'include_tests' => true,
                'why' => 'Plaintext secrets in source code are easily exposed via source reviews, logs, or dependency audits.',
                'fix' => 'Load secrets from environment variables or a secrets manager, never hardcode them.',
                'skip' => '#(test|spec|mock|fixture|example|\.md)#i',
            ],
        ];
    }
}
