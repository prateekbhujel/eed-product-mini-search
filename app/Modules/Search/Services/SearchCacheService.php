<?php

namespace App\Modules\Search\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class SearchCacheService
{
    public function key(array $filters): string
    {
        ksort($filters);

        return 'catalog.search.v3.'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));
    }

    public function remember(string $key, int $seconds, Closure $callback): array
    {
        return Cache::remember($key, $seconds, $callback);
    }

    public function hit(string $key): bool
    {
        return Cache::has($key);
    }
}
