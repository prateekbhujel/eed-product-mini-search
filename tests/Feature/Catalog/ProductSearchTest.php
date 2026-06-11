<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_react_catalog_shell(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('E24 Appliance Spare Parts Search');
    }

    public function test_search_matches_exact_oem_number(): void
    {
        $this->seed();

        $this->getJson('/api/catalog/search?q=DC31-00054A')
            ->assertOk()
            ->assertJsonPath('products.0.brand', 'Samsung')
            ->assertJsonPath('products.0.family', 'Washing machine pump');
    }

    public function test_search_understands_common_wording(): void
    {
        $this->seed();

        $this->getJson('/api/catalog/search?q=fridge%20shelf')
            ->assertOk()
            ->assertJsonPath('products.0.family', 'Refrigerator storage')
            ->assertJsonCount(6, 'suggestions');
    }

    public function test_precise_phrase_search_ranks_matching_parts_first(): void
    {
        $this->seed();

        $response = $this->getJson('/api/catalog/search?q=drain%20pump%2030W');

        $response
            ->assertOk()
            ->assertJsonPath('products.0.family', 'Washing machine pump')
            ->assertJsonPath('products.0.name', 'Askoll drain pump 30W');

        $families = collect($response->json('products'))
            ->take(6)
            ->pluck('family')
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['Washing machine pump'], $families);
    }

    public function test_filters_apply_to_ranked_results(): void
    {
        $this->seed();

        $this->getJson('/api/catalog/search?q=pump&brand=Bosch')
            ->assertOk()
            ->assertJsonPath('products.0.brand', 'Bosch')
            ->assertJsonPath('facets.brands.0.value', 'AEG');
    }

    public function test_search_results_are_paginated(): void
    {
        $this->seed();

        $this->getJson('/api/catalog/search?per_page=8&page=1')
            ->assertOk()
            ->assertJsonCount(8, 'products')
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.has_more', true)
            ->assertJsonPath('pagination.next_page', 2);

        $this->getJson('/api/catalog/search?per_page=8&page=2')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 2);
    }

    public function test_demo_seed_has_enough_catalog_rows_for_pagination(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(2000, Product::query()->count());
    }

    public function test_typo_search_returns_did_you_mean(): void
    {
        $this->seed();

        $this->getJson('/api/catalog/search?q=wahser%20pmp')
            ->assertOk()
            ->assertJsonPath('did_you_mean', 'washer pump');
    }

    public function test_product_detail_page_and_api_include_reviews(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'E24-PMP-DC310054A')->firstOrFail();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('og:type')
            ->assertSee('application/ld+json')
            ->assertSee($product->sku);

        $this->getJson('/api/catalog/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.sku', 'E24-PMP-DC310054A')
            ->assertJsonPath('product.price.display', '€31.40')
            ->assertJsonPath('product.gallery_urls.0', asset('catalog-images/photos/washing-machine-pump.jpg'))
            ->assertJsonPath('product.reviews.0.verified', true);
    }

    public function test_sitemap_lists_product_urls(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'E24-PMP-DC310054A')->firstOrFail();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee(route('catalog.products.page', ['product' => $product->slug]), false);
    }

    public function test_external_product_adapter_maps_eed_article_search(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => 'demo-id',
            'services.eed.session_id' => 'demo-session',
            'services.eed.shop_url' => 'https://eed.pratikbhujel.com.np',
        ]);

        Http::fake([
            'shop.euras.com/eed.php*' => Http::response([
                'gesamtanzahltreffer' => 12,
                'artikel' => [[
                    'artikelnummer' => 'Q509827',
                    'artikelbezeichnung' => 'HDMI cable high speed 2m',
                    'artikelhersteller' => 'Generic',
                    'vgruppenname' => 'CABLES',
                    'ekpreis' => '5,60',
                    'bildurl' => 'https://example.test/q509827.jpg',
                    'bestellbar' => 'J',
                ]],
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=HDMI%20cable&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'Q509827')
            ->assertJsonPath('products.0.name', 'HDMI cable high speed 2m')
            ->assertJsonPath('products.0.source', 'eed')
            ->assertJsonPath('products.0.price', 5.6)
            ->assertJsonPath('meta.gateway', 'eed')
            ->assertJsonPath('meta.has_more', true);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://shop.euras.com/eed.php?')
                && ($query['format'] ?? null) === 'json'
                && ($query['id'] ?? null) === 'demo-id'
                && ($query['sessionid'] ?? null) === 'demo-session'
                && ($query['art'] ?? null) === 'artikelsuche'
                && ($query['suchbg'] ?? null) === 'HDMIcable'
                && ($query['customerip'] ?? null) === md5('127.0.0.1');
        });
    }

    public function test_external_product_adapter_falls_back_without_eed_credentials(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => null,
            'services.eed.session_id' => null,
        ]);

        Http::fake([
            'dummyjson.com/products/search*' => Http::response([
                'products' => [[
                    'id' => 10,
                    'title' => 'Test product',
                    'brand' => 'Demo',
                    'category' => 'tools',
                    'price' => 19.99,
                    'thumbnail' => 'https://example.test/image.jpg',
                    'rating' => 4.7,
                    'stock' => 12,
                ]],
                'total' => 12,
                'skip' => 0,
                'limit' => 4,
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=phone&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Test product')
            ->assertJsonPath('products.0.source', 'dummyjson')
            ->assertJsonPath('meta.gateway', 'eed-fallback')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.cache_hit', false);

        $this->getJson('/api/catalog/external-search?q=phone&per_page=4')
            ->assertOk()
            ->assertJsonPath('meta.cache_hit', true);

        Http::assertSentCount(1);
    }
}
