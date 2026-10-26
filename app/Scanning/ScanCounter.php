<?php

namespace App\Scanning;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public counter of scans completed on the hosted web scanner.
 */
class ScanCounter
{
    protected const CACHE_KEY = 'scan-counts';

    public static function increment(): void
    {
        DB::table('scan_counts')->upsert(
            [['date' => now()->toDateString(), 'scans' => 1]],
            ['date'],
            ['scans' => DB::raw('scans + 1')],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{today:int, total:int}
     */
    public static function stats(): array
    {
        return Cache::remember(self::CACHE_KEY, 30, function (): array {
            if (! Schema::hasTable('scan_counts')) {
                return ['today' => 0, 'total' => 0];
            }

            return [
                'today' => (int) DB::table('scan_counts')->where('date', now()->toDateString())->value('scans'),
                'total' => (int) DB::table('scan_counts')->sum('scans'),
            ];
        });
    }
}
