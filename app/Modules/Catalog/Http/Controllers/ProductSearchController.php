<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function __construct(private readonly CatalogSearchService $search)
    {
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json($this->search->handle($request->only([
            'q',
            'family',
            'brand',
            'availability',
            'sort',
        ])));
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => $this->search->presentProduct($product->loadMissing([
                'category',
                'identifiers',
                'compatibleModels',
            ])),
        ]);
    }
}
