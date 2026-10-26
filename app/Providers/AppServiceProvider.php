<?php

namespace App\Providers;

use App\Scanning\Store\DatabaseScanStore;
use App\Scanning\Store\EphemeralScanStore;
use App\Scanning\Store\ScanStore;
use App\Services\ScannerService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ScanStore::class, fn () => static::isWebMode()
            ? new EphemeralScanStore
            : new DatabaseScanStore);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('web-scans', fn (Request $request) => Limit::perHour(
            (int) config('mysafecoding.web.scans_per_hour', 10),
        )->by($request->ip()));

        if (! static::isWebMode()) {
            ScannerService::registerListeners();
        }
    }

    public static function isWebMode(): bool
    {
        return config('mysafecoding.mode') === 'web';
    }
}
