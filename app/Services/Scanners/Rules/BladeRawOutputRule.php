<?php

namespace App\Services\Scanners\Rules;

class BladeRawOutputRule extends BaseRule
{
    public function id(): string
    {
        return 'laravel.blade.raw-output';
    }

    public function title(): string
    {
        return 'Unescaped Blade Output';
    }

    public function severity(): string
    {
        return 'MEDIUM';
    }

    public function category(): string
    {
        return 'XSS';
    }

    /**
     * Raw output that is safe by construction: framework helpers that return
     * trusted HTML, JSON encoders, translations, and explicit sanitizers.
     */
    protected const SAFE_EXPRESSIONS = '/^(csrf_field\(\)|method_field\(|\$slot\b|\$attributes\b|\$\w+->links\(|\$\w+->render\(|json_encode\(|Js::from\(|Illuminate\\\\Support\\\\Js::from\(|__\(|trans\(|trans_choice\(|e\(|clean\(|Purifier::clean\(|strip_tags\(|nl2br\(\s*e\(|htmlspecialchars\(|Vite::|svg\(|\$\w+->toHtml\(|\$errors->|@json|\$qrCodeSvg\b|\$\w+->twoFactorQrCodeSvg\()/';

    public function check(string $projectPath): array
    {
        $findings = [];
        foreach ($this->findBladeFiles($projectPath) as $file) {
            $lines = $this->readLines($file);
            if (! $lines) {
                continue;
            }
            foreach ($lines as $i => $line) {
                if (! preg_match_all('/\{!!\s*(.+?)\s*!!\}/', $line, $matches)) {
                    continue;
                }

                $unsafe = array_filter($matches[1], fn (string $expression): bool => ! preg_match(self::SAFE_EXPRESSIONS, trim($expression)));
                if ($unsafe === []) {
                    continue;
                }

                $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                    'Unescaped Blade output {!! '.mb_strimwidth(trim(reset($unsafe)), 0, 60, '…').' !!} renders raw HTML. Safe only if the value is trusted or already sanitized; if it can contain user input, this is cross-site scripting (XSS).',
                    'Use {{ }} for plain text. If the HTML is needed, make sure it is sanitized before rendering (e.g. HTMLPurifier, or a rich-text renderer that sanitizes) and mark this finding as ignored once verified.',
                );
            }
        }

        return $findings;
    }
}
