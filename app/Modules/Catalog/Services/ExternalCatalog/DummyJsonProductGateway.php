<?php

namespace App\Modules\Catalog\Services\ExternalCatalog;

use App\Modules\Catalog\Data\ExternalProductData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DummyJsonProductGateway implements SupplierProductGateway
{
    public function search(string $query, int $page = 1, int $perPage = 8, ?string $visitorIp = null): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $perPage = min(20, max(4, $perPage));

        if ($query === '') {
            return $this->emptyPayload($page, $perPage);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->retry(1, 150)
                ->get('https://dummyjson.com/products/search', [
                    'q' => $query,
                    'limit' => $perPage,
                    'skip' => ($page - 1) * $perPage,
                    'select' => 'id,title,brand,category,price,thumbnail,rating,stock',
                ]);
        } catch (ConnectionException) {
            return $this->emptyPayload($page, $perPage);
        }

        if (! $response->ok()) {
            return $this->emptyPayload($page, $perPage);
        }

        $products = collect($response->json('products', []))
            ->map(fn (array $row): array => ExternalProductData::fromDummyJson($row)->toArray())
            ->values()
            ->all();

        $total = (int) $response->json('total', count($products));

        return [
            'products' => $products,
            'source' => 'dummyjson',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page * $perPage < $total,
                'next_page' => $page * $perPage < $total ? $page + 1 : null,
            ],
        ];
    }

    private function emptyPayload(int $page, int $perPage): array
    {
        return [
            'products' => [],
            'source' => 'dummyjson',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'has_more' => false,
                'next_page' => null,
            ],
        ];
    }
}
