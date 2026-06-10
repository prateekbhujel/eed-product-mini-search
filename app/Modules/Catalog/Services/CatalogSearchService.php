<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductSearchRepository;
use App\Modules\Search\Models\SearchEvent;
use App\Modules\Search\Models\SearchSynonym;
use App\Modules\Search\Services\SearchCacheService;
use App\Modules\Search\Services\SearchQueryNormalizer;
use Illuminate\Support\Carbon;

class CatalogSearchService
{
    public function __construct(
        private readonly ProductSearchRepository $products,
        private readonly SearchQueryNormalizer $normalizer,
        private readonly SearchCacheService $cache,
    ) {
    }

    public function handle(array $filters): array
    {
        $filters = $this->cleanFilters($filters);
        $expandedTerms = $this->expandedTerms($filters['q'] ?? '');
        $cacheKey = $this->cache->key([...$filters, 'expanded' => $expandedTerms]);
        $cacheHit = $this->cache->hit($cacheKey);

        $payload = $this->cache->remember($cacheKey, 600, function () use ($filters, $expandedTerms): array {
            $ranked = $this->products->ranked($filters, $expandedTerms);
            $items = $ranked
                ->take(80)
                ->map(fn (array $row): array => $this->presentProduct($row['product'], $row['score']))
                ->values()
                ->all();

            return [
                'products' => $items,
                'facets' => $this->products->facets(),
                'suggestions' => $this->suggestions($filters['q'] ?? '', $items),
            ];
        });

        $payload['meta'] = [
            'cache_hit' => $cacheHit,
            'normalized_query' => $this->normalizer->normalize($filters['q'] ?? ''),
            'result_count' => count($payload['products']),
        ];

        $this->recordSearch($filters, $payload['meta']);

        return $payload;
    }

    public function presentProduct(Product $product, int $score = 0): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'name' => $product->name,
            'family' => $product->family,
            'description' => $product->description,
            'category' => [
                'name' => $product->category?->name,
                'short_name' => $product->category?->short_name,
                'path' => $product->category?->path,
            ],
            'price' => [
                'value' => number_format((float) $product->price, 2, '.', ''),
                'display' => number_format((float) $product->price, 2, ',', '.').' '.$product->currency,
                'compare_display' => $product->compare_price
                    ? number_format((float) $product->compare_price, 2, ',', '.').' '.$product->currency
                    : null,
            ],
            'availability' => [
                'code' => $product->availability,
                'label' => $this->availabilityLabel($product->availability),
                'delivery' => $product->delivery_text,
                'stock' => $product->stock_quantity,
            ],
            'rating' => round((float) $product->rating, 1),
            'review_count' => $product->review_count,
            'image_url' => $product->image_path ? asset($product->image_path) : null,
            'identifiers' => $product->identifiers
                ->groupBy('type')
                ->map(fn ($items) => $items->pluck('value')->values()->all())
                ->all(),
            'compatible_models' => $product->compatibleModels
                ->pluck('model_number')
                ->values()
                ->all(),
            'specs' => $product->specs ?? [],
            'score' => $score,
        ];
    }

    private function cleanFilters(array $filters): array
    {
        return collect($filters)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value): bool => filled($value))
            ->only(['q', 'family', 'brand', 'availability', 'sort'])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function expandedTerms(?string $query): array
    {
        $normalized = $this->normalizer->normalize($query);

        if ($normalized === '') {
            return [];
        }

        return SearchSynonym::query()
            ->orderByDesc('weight')
            ->get()
            ->filter(function (SearchSynonym $synonym) use ($normalized): bool {
                $term = $this->normalizer->normalize($synonym->term);

                return str_contains($normalized, $term) || str_contains($term, $normalized);
            })
            ->pluck('replacement')
            ->map(fn (string $replacement): string => $this->normalizer->normalize($replacement))
            ->flatMap(fn (string $replacement): array => $this->normalizer->tokens($replacement))
            ->unique()
            ->values()
            ->all();
    }

    private function suggestions(?string $query, array $products): array
    {
        $normalized = $this->normalizer->normalize($query);

        if ($normalized === '') {
            return ['Bosch pump', 'AEG heater', 'fridge shelf', 'DC31-00054A'];
        }

        $fromSynonyms = SearchSynonym::query()
            ->where('term', 'like', '%'.$normalized.'%')
            ->orWhere('replacement', 'like', '%'.$normalized.'%')
            ->orderByDesc('weight')
            ->limit(4)
            ->pluck('replacement')
            ->all();

        $fromResults = collect($products)
            ->take(4)
            ->flatMap(fn (array $product): array => [
                $product['brand'].' '.$product['family'],
                $product['identifiers']['oem'][0] ?? null,
                $product['compatible_models'][0] ?? null,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return collect([...$fromSynonyms, ...$fromResults])
            ->filter(fn (string $value): bool => $this->normalizer->normalize($value) !== $normalized)
            ->take(6)
            ->values()
            ->all();
    }

    private function availabilityLabel(string $availability): string
    {
        return match ($availability) {
            'in_stock' => 'In stock',
            'low_stock' => 'Low stock',
            'preorder' => 'Preorder',
            'backorder' => 'Backorder',
            default => ucfirst(str_replace('_', ' ', $availability)),
        };
    }

    private function recordSearch(array $filters, array $meta): void
    {
        SearchEvent::query()->create([
            'query' => $filters['q'] ?? null,
            'normalized_query' => $meta['normalized_query'],
            'filters' => collect($filters)->except('q')->all(),
            'result_count' => $meta['result_count'],
            'cache_hit' => $meta['cache_hit'],
            'searched_at' => Carbon::now(),
        ]);
    }
}
