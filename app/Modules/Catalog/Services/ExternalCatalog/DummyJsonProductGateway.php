<?php

namespace App\Modules\Catalog\Services\ExternalCatalog;

use App\Modules\Catalog\Data\ExternalProductData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DummyJsonProductGateway
{
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->retry(1, 150)
                ->get('https://dummyjson.com/products/search', [
                    'q' => $query,
                    'limit' => min(max($limit, 1), 20),
                    'select' => 'id,title,brand,category,price,thumbnail,rating,stock',
                ]);
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        return collect($response->json('products', []))
            ->map(fn (array $row): array => ExternalProductData::fromDummyJson($row)->toArray())
            ->values()
            ->all();
    }
}
