<?php

namespace App\Services\Scanners\Rules;

class MissingMimeValidationRule extends BaseRule
{
    public function id(): string { return 'laravel.file-upload.missing-mime'; }
    public function title(): string { return 'File Upload Without MIME Validation'; }
    public function severity(): string { return 'HIGH'; }
    public function category(): string { return 'Upload'; }

    public function check(string $projectPath): array
    {
        $findings = [];

        foreach ($this->findPhpFiles($projectPath) as $file) {
            $content = $this->readFile($file);
            if (! $content) continue;

            $lines = explode("\n", $content);
            foreach ($lines as $i => $line) {
                // Detect file upload handling
                if (preg_match('/->file\s*\(|->hasFile\s*\(|UploadedFile/', $line)) {
                    // Check surrounding context for mime validation
                    $contextStart = max(0, $i - 5);
                    $context = implode("\n", array_slice($lines, $contextStart, 15));

                    if (! preg_match('/(mimes|mimetypes|mime_types|image|file)/', $context)) {
                        $findings[] = $this->makeFinding(
                            $file, $projectPath, $i + 1,
                            'File upload handling detected without MIME type validation. An attacker could upload executable files (e.g., .php, .phtml) that get executed by the web server.',
                            "Add MIME validation: \$request->validate(['file' => 'required|file|mimes:jpg,png,pdf|max:10240']);",
                        );
                    }
                }
            }
        }

        return $findings;
    }
}
