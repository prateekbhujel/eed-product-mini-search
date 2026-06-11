<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Response;

class CatalogSitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [[
            'loc' => route('catalog.index'),
            'lastmod' => now()->toDateString(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ]];

        Product::query()
            ->where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->cursor()
            ->each(function (Product $product) use (&$urls): void {
                $urls[] = [
                    'loc' => route('catalog.products.page', ['product' => $product->slug]),
                    'lastmod' => optional($product->updated_at)->toDateString() ?? now()->toDateString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $xml = view('catalog.sitemap', ['urls' => $urls])->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
