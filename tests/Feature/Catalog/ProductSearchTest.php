<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
