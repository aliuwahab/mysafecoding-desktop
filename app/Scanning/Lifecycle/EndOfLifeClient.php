<?php

namespace App\Scanning\Lifecycle;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EndOfLifeClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function product(string $product): ?array
    {
        $product = strtolower(trim($product));

        if ($product === '') {
            return null;
        }

        $cacheKey = 'endoflife:product:'.$product;
        $ttl = max(60, (int) config('checkmycode.endoflife.cache_seconds', 86400));

        /** @var array<string, mixed>|null $result */
        $result = Cache::remember($cacheKey, $ttl, function () use ($product): ?array {
            try {
                $response = Http::acceptJson()
                    ->timeout((int) config('checkmycode.endoflife.timeout_seconds', 10))
                    ->get(rtrim((string) config('checkmycode.endoflife.base_url', 'https://endoflife.date'), '/').'/api/v1/products/'.$product.'/');
            } catch (\Throwable) {
                // Offline or endoflife.date unavailable: lifecycle checks are best-effort.
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return null;
            }

            $result = data_get($payload, 'result');

            return is_array($result) ? $result : null;
        });

        return $result;
    }
}
