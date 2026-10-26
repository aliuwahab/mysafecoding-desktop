<?php

namespace App\Services\Scanners\Rules;

class MissingMimeValidationRule extends BaseRule
{
    public function id(): string
    {
        return 'laravel.file-upload.missing-mime';
    }

    public function title(): string
    {
        return 'File Upload Without MIME Validation';
    }

    public function severity(): string
    {
        return 'HIGH';
    }

    public function category(): string
    {
        return 'Upload';
    }

    public function check(string $projectPath): array
    {
        $findings = [];
        $typeRule = '/mimes\s*:|mimetypes\s*:|extensions\s*:|\bimage\b|File::(types|image)\s*\(|->types\s*\(|getMimeType\s*\(|getClientMimeType\s*\(/i';

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $content = $this->readFile($file);
            if (! $content || ! preg_match('/->file\s*\(|->hasFile\s*\(/', $content)) {
                continue;
            }

            // File type checks anywhere in this class, or in a Form Request it type-hints, count.
            if (preg_match($typeRule, $content)) {
                continue;
            }
            foreach ($this->formRequestsUsedBy($content, $projectPath) as $request) {
                if (preg_match($typeRule, $request)) {
                    continue 2;
                }
            }

            foreach (explode("\n", $content) as $i => $line) {
                if (preg_match('/->file\s*\(|->hasFile\s*\(/', $line)) {
                    $findings[] = $this->makeFinding($file, $projectPath, $i + 1,
                        'File upload handling with no file-type validation found in this class or its Form Request. An attacker could upload executable files (e.g. .php, .phtml) that the web server might run.',
                        "Validate the type, e.g. 'document' => ['required', File::types(['pdf', 'docx'])->max(10 * 1024)], and store uploads outside the public folder.",
                    );
                    break;
                }
            }
        }

        return $findings;
    }
}
