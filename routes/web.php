<?php

use App\Http\Controllers\WebScanController;
use App\Livewire\Scanner;
use Illuminate\Support\Facades\Route;

Route::get('/', Scanner::class);

Route::get('/terms', fn () => view('terms', [
    'updated' => '26 October 2026',
    'host' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'this website',
    'ttl' => (int) config('checkmycode.web.results_ttl_minutes', 60),
    'github' => config('checkmycode.github_url'),
    'license' => trim((string) @file_get_contents(base_path('LICENSE'))),
]))->name('terms');

// Hosted web mode only; returns 404 on the desktop app.
Route::post('/scans', [WebScanController::class, 'store'])
    ->middleware('throttle:web-scans')
    ->name('web-scans.store');
