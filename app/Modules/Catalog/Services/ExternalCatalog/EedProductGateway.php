<?php

namespace App\Modules\Catalog\Services\ExternalCatalog;

use App\Modules\Catalog\Data\ExternalProductData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EedProductGateway implements SupplierProductGateway
{
    public function __construct(private readonly DummyJsonProductGateway $fallback)
    {
    }

    public function search(string $query, int $page = 1, int $perPage = 8, ?string $visitorIp = null): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $perPage = min(20, max(4, $perPage));

        if (! $this->hasCredentials()) {
            $payload = $this->fallback->search($query, $page, $perPage, $visitorIp);
            $payload['meta']['gateway'] = 'eed-fallback';

            return $payload;
        }

        $keyword = $this->eedKeyword($query);

        if ($keyword === '') {
            return $this->emptyPayload($page, $perPage);
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.eed.timeout', 8))
                ->retry(1, 250)
                ->get((string) config('services.eed.base_url'), $this->queryParams($keyword, $page, $perPage, $visitorIp));
        } catch (ConnectionException) {
            return $this->emptyPayload($page, $perPage, 'connection_failed');
        }

        if (! $response->ok()) {
            return $this->emptyPayload($page, $perPage, 'http_'.$response->status());
        }

        $body = $response->json();

        if (! is_array($body)) {
            return $this->emptyPayload($page, $perPage, 'invalid_json');
        }

        $rows = collect($this->articleRows($body));
        $products = $rows
            ->map(fn (array $row): array => ExternalProductData::fromEedArticle($row)->toArray())
            ->values()
            ->all();
        $total = $this->totalCount($body, $products);

        return [
            'products' => $products,
            'source' => 'eed',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page * $perPage < $total,
                'next_page' => $page * $perPage < $total ? $page + 1 : null,
                'gateway' => 'eed',
            ],
        ];
    }

    private function hasCredentials(): bool
    {
        return filled(config('services.eed.id')) && filled(config('services.eed.session_id'));
    }

    private function queryParams(string $keyword, int $page, int $perPage, ?string $visitorIp): array
    {
        $params = [
            'format' => 'json',
            'id' => config('services.eed.id'),
            'sessionid' => config('services.eed.session_id'),
            'art' => 'artikelsuche',
            'suchbg' => $keyword,
            'seite' => $page,
            'anzahl' => $perPage,
        ];

        if (filled(config('services.eed.shop_url'))) {
            $params['shopurl'] = config('services.eed.shop_url');
        }

        if ($visitorIp) {
            $params['customerip'] = md5($visitorIp);
        }

        return $params;
    }

    private function eedKeyword(string $query): string
    {
        $keyword = preg_replace('/[^a-zA-Z0-9,.\-]/', '', Str::ascii($query)) ?? '';

        return strlen($keyword) >= 3 ? $keyword : '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function articleRows(array $body): array
    {
        foreach (['artikel', 'articles', 'article', 'treffer', 'results', 'result', 'data'] as $key) {
            $rows = $body[$key] ?? null;

            if (is_array($rows)) {
                return array_is_list($rows) ? $rows : [$rows];
            }
        }

        return array_is_list($body) ? $body : [];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function totalCount(array $body, array $products): int
    {
        foreach (['gesamtanzahltreffer', 'gesamtanzahl', 'total', 'anzahl'] as $key) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                return (int) $body[$key];
            }
        }

        return count($products);
    }

    private function emptyPayload(int $page, int $perPage, string $reason = 'empty'): array
    {
        return [
            'products' => [],
            'source' => 'eed',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'has_more' => false,
                'next_page' => null,
                'gateway' => 'eed',
                'empty_reason' => $reason,
            ],
        ];
    }
}
