<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function __construct(private readonly CatalogSearchService $search) {}

    public function search(Request $request): JsonResponse
    {
        return response()->json($this->search->handle($request->only([
            'q',
            'family',
            'brand',
            'availability',
            'sort',
            'page',
            'per_page',
        ])));
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->with([
                'category',
                'identifiers',
                'compatibleModels',
                'reviews',
            ])
            ->firstOrFail();

        return response()->json([
            'product' => $this->search->presentProduct($product),
        ]);
    }
}
