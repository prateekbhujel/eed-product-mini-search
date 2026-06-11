<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogSearchService;
use App\Modules\Catalog\Services\CatalogSeoService;
use Illuminate\View\View;

class CatalogPageController extends Controller
{
    public function __construct(
        private readonly CatalogSearchService $search,
        private readonly CatalogSeoService $seo,
    ) {}

    public function __invoke(): View
    {
        $product = request()->route('product');

        if (is_string($product)) {
            $product = Product::query()->where('slug', $product)->firstOrFail();
        }

        if ($product instanceof Product) {
            $product->load(['category', 'identifiers', 'compatibleModels', 'reviews']);
            $presented = $this->search->presentProduct($product);

            return view('catalog.app', [
                'seo' => $this->seo->product($presented),
            ]);
        }

        return view('catalog.app', [
            'seo' => $this->seo->index(),
        ]);
    }
}
