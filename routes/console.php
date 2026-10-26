<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('checkmycode:prune-workspaces {--minutes=60}', function (Filesystem $files) {
    $root = config('checkmycode.web.workspace_path');
    $cutoff = now()->subMinutes((int) $this->option('minutes'))->getTimestamp();
    $pruned = 0;

    foreach (is_dir($root) ? $files->directories($root) : [] as $workspace) {
        if (filemtime($workspace) < $cutoff) {
            $files->deleteDirectory($workspace);
            $pruned++;
        }
    }

    $this->info("Pruned {$pruned} stale scan workspaces.");
})->purpose('Delete uploaded code left behind by scans that crashed (web mode)');

if (config('checkmycode.mode') === 'web') {
    Schedule::command('checkmycode:prune-workspaces')->everyFifteenMinutes();
}
