<?php

use App\Http\Controllers\WebScanController;
use App\Livewire\Scanner;
use Illuminate\Support\Facades\Route;

Route::get('/', Scanner::class);

// Hosted web mode only; returns 404 on the desktop app.
Route::post('/scans', [WebScanController::class, 'store'])
    ->middleware('throttle:web-scans')
    ->name('web-scans.store');
