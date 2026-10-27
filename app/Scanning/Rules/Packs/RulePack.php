<?php

namespace App\Scanning\Rules\Packs;

/**
 * A set of line-based pattern rules for one language family.
 *
 * Each rule is an array with:
 *   pattern        regex a source line must match (required)
 *   rule_id, title, severity, category, why, fix
 *   frameworks     only run for these project types
 *   extensions     only run on these file extensions (default: the pack's)
 *   paths          regex the relative path must match
 *   skip           regex the relative path must not match
 *   unless         regex the matched line must not match
 *   unless_nearby  regex that, found within `window` lines (default 10), suppresses the finding
 *   when_file      regex the whole file must match
 *   unless_file    regex the whole file must not match
 *   include_tests  true to also scan test and fixture files
 */
abstract class RulePack
{
    /** Test code, fixtures and specs: findings there are rarely exploitable. */
    public const TEST_PATHS = '#(^|/)(tests?|spec|specs|__tests__|__mocks__|fixtures?|testdata|e2e|cypress)/|[._-](test|spec)\.\w+$|_test\.go$|Tests?\.(java|kt|cs)$#i';

    /** Minified or bundled files: third-party or build output, not hand-written code. */
    public const GENERATED_PATHS = '#\.min\.(js|css)$|[.-]bundle\.js$|(^|/)(staticresources|assets/(vendor|libs?|private))/#i';

    /** Lines that are only a comment: a pattern inside one is documentation, not code. */
    public const COMMENT_LINE = '#^\s*(//|/\*|\*|\#(?![\[{!])|<!--|--\s)#';

    abstract public function appliesTo(string $projectType): bool;

    /**
     * @return list<string>
     */
    abstract public function extensions(): array;

    /**
     * @return list<array<string, mixed>>
     */
    abstract public function rules(): array;
}
