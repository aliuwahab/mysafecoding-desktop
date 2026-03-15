<?php

namespace App\Services\Scanners\Rules;

class MissingFillableRule extends BaseRule
{
    public function id(): string { return 'laravel.mass-assignment.missing-fillable'; }
    public function title(): string { return 'Mass Assignment: No $fillable or $guarded'; }
    public function severity(): string { return 'MEDIUM'; }
    public function category(): string { return 'Code'; }

    public function check(string $projectPath): array
    {
        $findings = [];
        foreach ($this->findPhpFiles($projectPath, 'app/Models') as $file) {
            $content = $this->readFile($file);
            if (! $content) continue;
            if (! preg_match('/extends\s+Model\b/', $content)) continue;
            if (preg_match('/\$(fillable|guarded)\s*=/', $content)) continue;

            $line = 1;
            foreach (explode("\n", $content) as $i => $l) {
                if (preg_match('/class\s+\w+\s+extends\s+Model/', $l)) { $line = $i + 1; break; }
            }
            $findings[] = $this->makeFinding(
                $file, $projectPath, $line,
                'Eloquent model extends Model but defines neither $fillable nor $guarded. Without these, mass assignment behavior depends on the framework default which may not be safe.',
                'Add a $fillable array listing the columns that should be mass-assignable, or add $guarded with columns to protect.',
            );
        }
        return $findings;
    }
}
