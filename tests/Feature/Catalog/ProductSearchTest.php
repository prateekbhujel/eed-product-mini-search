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
                'fehlernummer' => '0',
                'gesamtanzahltreffer' => 12,
                'neuesessionid' => 'generated-session',
                'treffer' => [
                    '1' => [
                        'artikelnummer' => 'R423020',
                        'artikelbezeichnung' => '149335115 SONY AC-ADAPTER (AC-M1215WW',
                        'originalnummer' => '149335115',
                        'artikelhersteller' => 'SONY',
                        'vgruppenname' => 'AC-Adapter',
                        'ekpreis' => '28,05',
                        'thumbnailurl' => 'https://example.test/r423020.jpg',
                        'bestellbar' => 'J',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=SONY%20adapter&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'R423020')
            ->assertJsonPath('products.0.name', '149335115 SONY AC-ADAPTER (AC-M1215WW')
            ->assertJsonPath('products.0.source', 'eed')
            ->assertJsonPath('products.0.category', 'AC-Adapter')
            ->assertJsonPath('products.0.price', 28.05)
            ->assertJsonPath('products.0.image_url', 'https://example.test/r423020.jpg')
            ->assertJsonPath('meta.gateway', 'eed-live')
            ->assertJsonPath('meta.session_id', 'generated-session')
            ->assertJsonPath('meta.has_more', true);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://shop.euras.com/eed.php?')
                && ($query['format'] ?? null) === 'json'
                && ($query['id'] ?? null) === 'demo-id'
                && ($query['sessionid'] ?? null) === 'demo-session'
                && ($query['art'] ?? null) === 'artikelsuche'
                && ($query['suchbg'] ?? null) === 'SONY'
                && ($query['shopurl'] ?? null) === 'https://eed.pratikbhujel.com.np'
                && ($query['customerip'] ?? null) === md5('127.0.0.1');
        });
    }

    public function test_external_product_adapter_uses_captured_eed_test_response_without_credentials(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => null,
            'services.eed.session_id' => null,
        ]);

        Http::fake();

        $this->getJson('/api/catalog/external-search?q=SONY&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'R423020')
            ->assertJsonPath('products.0.name', '149335115 SONY AC-ADAPTER (AC-M1215WW')
            ->assertJsonPath('products.0.brand', 'SONY')
            ->assertJsonPath('products.0.category', 'AC-Adapter')
            ->assertJsonPath('products.0.source', 'eed-test')
            ->assertJsonPath('products.0.price', 28.05)
            ->assertJsonPath('meta.gateway', 'eed-vpn-captured')
            ->assertJsonPath('meta.live_error', 'missing_eed_id')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.cache_hit', false);

        $this->getJson('/api/catalog/external-search?q=SONY&per_page=4')
            ->assertOk()
            ->assertJsonPath('meta.cache_hit', true);

        Http::assertNothingSent();
    }

    public function test_external_product_adapter_routes_electrolux_to_public_eed_aeg_feed(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => 'demo-id',
            'services.eed.session_id' => 'auto',
        ]);

        Http::fake([
            'shop.euras.com/eed.php*' => Http::response([
                'fehlernummer' => '0',
                'gesamtanzahltreffer' => 200,
                'treffer' => [
                    '1' => [
                        'artikelnummer' => 'H632136',
                        'artikelbezeichnung' => '5551121162 GLASPLATTE MIT RAHMEN, INOX, AEG',
                        'artikelhersteller' => 'ELECTROLUX / AEG',
                        'vgruppenname' => 'GLASKERAMIKFLAECHEN',
                        'ekpreis' => '177,23',
                        'thumbnailurl' => 'https://example.test/aeg.jpg',
                        'bestellbar' => 'J',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=ELECTROLUX&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'H632136')
            ->assertJsonPath('products.0.source_query', 'AEG')
            ->assertJsonPath('meta.gateway', 'eed-live')
            ->assertJsonPath('meta.requested_query', 'ELECTROLUX')
            ->assertJsonPath('meta.eed_query', 'AEG')
            ->assertJsonPath('meta.query_mapped', true);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['art'] ?? null) === 'artikelsuche'
                && ($query['suchbg'] ?? null) === 'AEG';
        });
    }

    public function test_external_product_adapter_uses_extended_eed_family_search(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => 'demo-id',
            'services.eed.session_id' => 'auto',
        ]);

        Http::fake([
            'shop.euras.com/eed.php*' => Http::response([
                'fehlernummer' => '0',
                'gesamtanzahltreffer' => 2,
                'vgruppentreffer' => [
                    '1' => [
                        'vgruppenid' => '5853500000',
                        'vgruppenname' => 'Glasplatten',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=GLAS&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'family-5853500000')
            ->assertJsonPath('products.0.name', 'Glasplatten')
            ->assertJsonPath('products.0.source', 'eed-family')
            ->assertJsonPath('products.0.lookup_type', 'article_family')
            ->assertJsonPath('meta.eed_command', 'artikelsuche_neu')
            ->assertJsonPath('meta.eed_query', 'GLAS');

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['art'] ?? null) === 'artikelsuche_neu'
                && ($query['suchbg'] ?? null) === 'GLAS';
        });
    }

    public function test_external_product_adapter_uses_eed_appliance_manufacturer_search(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => 'demo-id',
            'services.eed.session_id' => 'auto',
        ]);

        Http::fake([
            'shop.euras.com/eed.php*' => Http::response([
                'fehlernummer' => '0',
                'gesamtanzahltreffer' => 1,
                'treffer' => [
                    '1' => [
                        'herstellerid' => '15250000',
                        'geraetehersteller' => 'SAMSUNG',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=SAMSUNG&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'manufacturer-15250000')
            ->assertJsonPath('products.0.name', 'SAMSUNG')
            ->assertJsonPath('products.0.source', 'eed-manufacturer')
            ->assertJsonPath('products.0.lookup_type', 'appliance_manufacturer')
            ->assertJsonPath('meta.eed_command', 'geraetehersteller')
            ->assertJsonPath('meta.eed_query', 'SAMSUNG');

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['art'] ?? null) === 'geraetehersteller'
                && ($query['suchbg'] ?? null) === 'SAMSUNG';
        });
    }

    public function test_external_product_adapter_uses_session_auto_when_session_is_not_configured(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => 'demo-id',
            'services.eed.session_id' => null,
            'services.eed.shop_url' => 'https://eed.pratikbhujel.com.np',
        ]);

        Http::fake([
            'shop.euras.com/eed.php*' => Http::response([
                'fehlernummer' => '0',
                'gesamtanzahltreffer' => 1,
                'treffer' => [
                    '1' => [
                        'artikelnummer' => 'R423020',
                        'artikelbezeichnung' => '149335115 SONY AC-ADAPTER (AC-M1215WW',
                        'artikelhersteller' => 'SONY',
                        'vgruppenname' => 'AC-Adapter',
                        'ekpreis' => '28,05',
                        'bestellbar' => 'J',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/catalog/external-search?q=SONY&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'R423020');

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['sessionid'] ?? null) === 'auto';
        });
    }

    public function test_external_product_adapter_falls_back_to_captured_eed_response_when_live_gateway_fails(): void
    {
        cache()->flush();
        config([
            'services.eed.id' => 'demo-id',
            'services.eed.session_id' => 'auto',
        ]);

        Http::fake([
            'shop.euras.com/eed.php*' => Http::response('Page not found', 404),
        ]);

        $this->getJson('/api/catalog/external-search?q=R423020&per_page=4')
            ->assertOk()
            ->assertJsonPath('products.0.external_id', 'R423020')
            ->assertJsonPath('products.0.source', 'eed-test')
            ->assertJsonPath('meta.gateway', 'eed-vpn-captured')
            ->assertJsonPath('meta.live_error', 'http_404');
    }
}
