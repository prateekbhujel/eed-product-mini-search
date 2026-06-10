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
            ->assertSee('E24 Spare Parts Search');
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

    public function test_product_detail_page_and_api_include_reviews(): void
    {
        $this->seed();

        $product = Product::query()->where('sku', 'E24-PMP-DC310054A')->firstOrFail();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('E24 Spare Parts Search');

        $this->getJson('/api/catalog/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.sku', 'E24-PMP-DC310054A')
            ->assertJsonPath('product.reviews.0.verified', true);
    }

    public function test_external_product_adapter_returns_normalized_products(): void
    {
        cache()->flush();

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
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.cache_hit', false);

        $this->getJson('/api/catalog/external-search?q=phone&per_page=4')
            ->assertOk()
            ->assertJsonPath('meta.cache_hit', true);

        Http::assertSentCount(1);
    }
}
