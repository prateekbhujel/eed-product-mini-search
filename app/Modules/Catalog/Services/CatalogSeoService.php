<?php

namespace App\Modules\Catalog\Services;

use Illuminate\Support\Str;

class CatalogSeoService
{
    public function index(): array
    {
        return [
            'title' => 'E24 Appliance Spare Parts Search',
            'description' => 'Search appliance spare parts by model number, OEM reference, brand, category and availability.',
            'canonical' => route('catalog.index'),
            'robots' => request()->query() ? 'noindex,follow' : 'index,follow',
            'type' => 'website',
            'image' => asset('catalog-images/photos/washing-machine-pump.jpg'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'E24 Appliance Spare Parts Search',
                'url' => route('catalog.index'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('catalog.index').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    public function product(array $product): array
    {
        $title = Str::limit($product['brand'].' '.$product['name'].' | '.$product['sku'], 64, '');
        $description = Str::limit($product['description'] ?: $product['name'].' appliance spare part.', 155, '');
        $canonical = route('catalog.products.page', ['product' => $product['slug']]);
        $availability = $this->schemaAvailability($product['availability']['code'] ?? '');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index,follow',
            'type' => 'product',
            'image' => $product['image_url'] ?? null,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product['brand'].' '.$product['name'],
                'sku' => $product['sku'],
                'mpn' => $product['identifiers']['oem'][0] ?? $product['sku'],
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $product['brand'],
                ],
                'category' => $product['category']['name'] ?? $product['family'],
                'description' => $description,
                'image' => $product['gallery_urls'] ?? [$product['image_url']],
                'url' => $canonical,
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $canonical,
                    'priceCurrency' => 'EUR',
                    'price' => $product['price']['value'],
                    'availability' => $availability,
                    'itemCondition' => 'https://schema.org/NewCondition',
                ],
                'aggregateRating' => [
                    '@type' => 'AggregateRating',
                    'ratingValue' => number_format((float) $product['rating'], 1, '.', ''),
                    'reviewCount' => $product['review_count'],
                ],
            ],
        ];
    }

    private function schemaAvailability(string $availability): string
    {
        return match ($availability) {
            'in_stock', 'low_stock' => 'https://schema.org/InStock',
            'preorder' => 'https://schema.org/PreOrder',
            'backorder' => 'https://schema.org/BackOrder',
            default => 'https://schema.org/OutOfStock',
        };
    }
}
