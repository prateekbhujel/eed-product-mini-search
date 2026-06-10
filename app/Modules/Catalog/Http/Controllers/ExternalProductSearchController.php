<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ExternalCatalog\DummyJsonProductGateway;
use App\Modules\Search\Services\SearchCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalProductSearchController extends Controller
{
    public function __invoke(Request $request, DummyJsonProductGateway $gateway, SearchCacheService $cache): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(20, max(4, (int) $request->query('per_page', 8)));
        $cacheKey = $cache->key([
            'external' => 'dummyjson',
            'q' => $query,
            'page' => $page,
            'per_page' => $perPage,
        ]);
        $cacheHit = $cache->hit($cacheKey);

        $payload = $cache->remember(
            $cacheKey,
            1800,
            fn (): array => $gateway->search($query, $page, $perPage),
        );

        $payload['meta']['cache_hit'] = $cacheHit;

        return response()->json($payload);
    }
}
