<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ExternalCatalog\SupplierProductGateway;
use App\Modules\Search\Services\SearchCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalProductSearchController extends Controller
{
    public function __invoke(Request $request, SupplierProductGateway $gateway, SearchCacheService $cache): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(20, max(4, (int) $request->query('per_page', 8)));
        $cacheKey = $cache->key([
            'version' => 'eed-dynamic-v3',
            'external' => 'supplier',
            'q' => $query,
            'page' => $page,
            'per_page' => $perPage,
        ]);
        $cacheHit = $cache->hit($cacheKey);

        $payload = $cache->remember(
            $cacheKey,
            1800,
            fn (): array => $gateway->search($query, $page, $perPage, $request->ip()),
        );

        $payload['meta']['cache_hit'] = $cacheHit;

        return response()->json($payload);
    }
}
