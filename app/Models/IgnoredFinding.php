<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoredFinding extends Model
{
    protected $fillable = [
        'project_path',
        'scanner',
        'fingerprint',
        'reason',
    ];

    /**
     * Get all ignored fingerprints for a project, keyed by "scanner:fingerprint".
     */
    public static function getIgnoredMap(string $projectPath): array
    {
        return static::where('project_path', $projectPath)
            ->get()
            ->mapWithKeys(fn ($item) => [
                "{$item->scanner}:{$item->fingerprint}" => $item->reason ?? 'No reason provided',
            ])
            ->toArray();
    }
}
