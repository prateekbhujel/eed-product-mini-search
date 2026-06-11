<?php

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Search\Services\SearchQueryNormalizer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductSearchRepository
{
    public function __construct(private readonly SearchQueryNormalizer $normalizer)
    {
    }

    /**
     * @return Collection<int, array{score: int, product: Product}>
     */
    public function ranked(array $filters, array $expandedTerms): Collection
    {
        $query = $this->normalizer->normalize($filters['q'] ?? '');
        $tokens = collect([
            ...$this->normalizer->tokens($query),
            ...$expandedTerms,
        ])->unique()->values()->all();

        $products = $this->baseProducts($filters, $query, $tokens);

        return $products
            ->map(fn (Product $product): array => [
                'score' => $this->score($product, $query, $tokens),
                'product' => $product,
            ])
            ->filter(fn (array $row): bool => $query === '' || $row['score'] > 0)
            ->sortBy([
                ['score', 'desc'],
                fn (array $a, array $b): int => strcmp($a['product']->name, $b['product']->name),
            ])
            ->values();
    }

    public function facets(): array
    {
        return Cache::remember('catalog.facets.v1', 600, function (): array {
            $products = Product::query()
                ->where('is_active', true)
                ->with('category')
                ->get();

            return [
                'families' => $this->countFacet($products, 'family'),
                'brands' => $this->countFacet($products, 'brand'),
                'availability' => $this->countFacet($products, 'availability'),
                'categories' => $products
                    ->groupBy(fn (Product $product): string => $product->category?->short_name ?? 'Other')
                    ->map(fn (Collection $items, string $label): array => [
                        'label' => $label,
                        'value' => $items->first()->category?->slug,
                        'count' => $items->count(),
                    ])
                    ->values()
                    ->sortBy('label')
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @param EloquentCollection<int, Product> $products
     * @return array<int, array{label: string, value: string, count: int}>
     */
    private function countFacet(EloquentCollection $products, string $field): array
    {
        return $products
            ->groupBy($field)
            ->map(fn (Collection $items, string $value): array => [
                'label' => $this->label($field, $value),
                'value' => $value,
                'count' => $items->count(),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, Product>
     */
    private function baseProducts(array $filters, string $query, array $tokens): EloquentCollection
    {
        $compactQuery = $this->normalizer->compact($query);
        $searchTokens = collect([$query, ...$tokens])
            ->map(fn (?string $token): string => $this->normalizer->normalize($token))
            ->filter(fn (string $token): bool => strlen($token) >= 3)
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return Product::query()
            ->where('is_active', true)
            ->with(['category', 'identifiers', 'compatibleModels'])
            ->when($filters['family'] ?? null, fn ($query, string $family) => $query->where('family', $family))
            ->when($filters['brand'] ?? null, fn ($query, string $brand) => $query->where('brand', $brand))
            ->when($filters['availability'] ?? null, fn ($query, string $availability) => $query->where('availability', $availability))
            ->when($query !== '', function ($builder) use ($compactQuery, $searchTokens): void {
                $builder->where(function ($candidate) use ($compactQuery, $searchTokens): void {
                    if ($compactQuery !== '') {
                        $candidate
                            ->orWhere('sku', 'like', '%'.$compactQuery.'%')
                            ->orWhereHas('identifiers', fn ($identifier) => $identifier
                                ->where('normalized_value', 'like', '%'.$compactQuery.'%'))
                            ->orWhereHas('compatibleModels', fn ($model) => $model
                                ->where('normalized_model_number', 'like', '%'.$compactQuery.'%'));
                    }

                    foreach ($searchTokens as $token) {
                        $candidate
                            ->orWhere('brand', 'like', '%'.$token.'%')
                            ->orWhere('name', 'like', '%'.$token.'%')
                            ->orWhere('family', 'like', '%'.$token.'%')
                            ->orWhere('search_keywords', 'like', '%'.$token.'%')
                            ->orWhereHas('category', fn ($category) => $category
                                ->where('name', 'like', '%'.$token.'%')
                                ->orWhere('short_name', 'like', '%'.$token.'%'));
                    }
                });
            })
            ->get();
    }

    /**
     * @param array<int, string> $tokens
     */
    private function score(Product $product, string $query, array $tokens): int
    {
        if ($query === '') {
            return 10 + min(8, $product->stock_quantity);
        }

        $score = 0;
        $compactQuery = $this->normalizer->compact($query);
        $name = $this->normalizer->normalize($product->name);
        $brand = $this->normalizer->normalize($product->brand);
        $family = $this->normalizer->normalize($product->family);
        $category = $this->normalizer->normalize($product->category?->name);
        $haystack = $this->documentText($product);
        $queryTokens = $this->normalizer->tokens($query);
        $matchedQueryTokens = collect($queryTokens)
            ->filter(fn (string $token): bool => str_contains($haystack, $token))
            ->count();

        if (count($queryTokens) >= 2 && $matchedQueryTokens < 2) {
            return 0;
        }

        if ($matchedQueryTokens > 0) {
            $score += $matchedQueryTokens * 24;
        }

        if ($this->normalizer->compact($product->sku) === $compactQuery) {
            $score += 180;
        }

        foreach ($product->identifiers as $identifier) {
            $identifierCompact = $this->normalizer->compact($identifier->value);

            if ($identifierCompact === $compactQuery) {
                $score += $identifier->type === 'oem' ? 170 : 130;
            } elseif ($compactQuery !== '' && str_contains($identifierCompact, $compactQuery)) {
                $score += 95;
            }
        }

        foreach ($product->compatibleModels as $model) {
            $modelCompact = $this->normalizer->compact($model->model_number);

            if ($modelCompact === $compactQuery) {
                $score += 145;
            } elseif ($compactQuery !== '' && str_contains($modelCompact, $compactQuery)) {
                $score += 70;
            }
        }

        if (str_contains($name, $query)) {
            $score += 130;
        }

        if ($brand === $query || str_contains($brand, $query)) {
            $score += 45;
        }

        if (str_contains($family, $query) || str_contains($category, $query)) {
            $score += 40;
        }

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (str_contains($haystack, $token)) {
                $score += strlen($token) > 3 ? 16 : 7;
            }
        }

        if ($score > 0 && $product->stock_quantity > 0) {
            $score += 8;
        }

        return $score;
    }

    private function documentText(Product $product): string
    {
        $parts = [
            $product->sku,
            $product->brand,
            $product->name,
            $product->family,
            $product->category?->name,
            $product->category?->path,
            $product->description,
            $product->search_keywords,
            implode(' ', $product->specs ?? []),
            $product->identifiers->pluck('value')->implode(' '),
            $product->compatibleModels->pluck('model_number')->implode(' '),
            $product->compatibleModels->pluck('model_family')->implode(' '),
        ];

        return $this->normalizer->normalize(implode(' ', array_filter($parts)));
    }

    private function label(string $field, string $value): string
    {
        if ($field !== 'availability') {
            return $value;
        }

        return match ($value) {
            'in_stock' => 'In stock',
            'low_stock' => 'Low stock',
            'preorder' => 'Preorder',
            'backorder' => 'Backorder',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }
}
