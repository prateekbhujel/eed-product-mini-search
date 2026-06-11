<?php

namespace App\Modules\Catalog\Services\ExternalCatalog;

use App\Modules\Catalog\Data\ExternalProductData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EedProductGateway implements SupplierProductGateway
{
    private const REVIEW_QUERIES = ['AEG', 'SONY', 'HDMI'];

    public function search(string $query, int $page = 1, int $perPage = 8, ?string $visitorIp = null): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $perPage = min(20, max(4, $perPage));

        if ($query === '') {
            return $this->searchReviewCatalog($page, $perPage, $visitorIp);
        }

        $keyword = $this->eedKeyword($query);

        if ($keyword === '') {
            return $this->emptyPayload($page, $perPage);
        }

        if (! $this->hasCredentials()) {
            return $this->capturedTestPayload($query, $page, $perPage, 'missing_eed_id');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.eed.timeout', 8))
                ->retry(1, 250)
                ->get((string) config('services.eed.base_url'), $this->queryParams($keyword, $page, $perPage, $visitorIp));
        } catch (ConnectionException) {
            return $this->capturedTestPayload($query, $page, $perPage, 'connection_failed');
        }

        if (! $response->ok()) {
            return $this->capturedTestPayload($query, $page, $perPage, 'http_'.$response->status());
        }

        $body = $response->json();

        if (! is_array($body)) {
            return $this->capturedTestPayload($query, $page, $perPage, 'invalid_json');
        }

        if (($body['fehlernummer'] ?? '0') !== '0') {
            return $this->capturedTestPayload($query, $page, $perPage, 'eed_error_'.$body['fehlernummer']);
        }

        $rows = collect($this->articleRows($body));
        $products = $rows
            ->map(fn (array $row): array => $this->presentArticle($row, 'eed', $query))
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
                'gateway' => 'eed-live',
                'session_id' => $body['neuesessionid'] ?? config('services.eed.session_id', 'auto'),
            ],
        ];
    }

    private function hasCredentials(): bool
    {
        return filled(config('services.eed.id'));
    }

    private function searchReviewCatalog(int $page, int $perPage, ?string $visitorIp): array
    {
        $perQuery = max(4, (int) ceil($perPage / count(self::REVIEW_QUERIES)));
        $payloads = collect(self::REVIEW_QUERIES)
            ->map(fn (string $query): array => $this->search($query, $page, $perQuery, $visitorIp));

        $products = $payloads
            ->flatMap(fn (array $payload): array => $payload['products'] ?? [])
            ->unique('external_id')
            ->take($perPage)
            ->values()
            ->all();
        $total = $payloads->sum(fn (array $payload): int => (int) ($payload['meta']['total'] ?? 0));

        return [
            'products' => $products,
            'source' => 'eed',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page * $perPage < $total,
                'next_page' => $page * $perPage < $total ? $page + 1 : null,
                'gateway' => 'eed-live',
                'review_queries' => self::REVIEW_QUERIES,
            ],
        ];
    }

    private function presentArticle(array $row, string $source, string $sourceQuery): array
    {
        $product = ExternalProductData::fromEedArticle($row, $source)->toArray();
        $product['source_query'] = $sourceQuery;

        return $product;
    }

    private function queryParams(string $keyword, int $page, int $perPage, ?string $visitorIp): array
    {
        $params = [
            'format' => 'json',
            'id' => config('services.eed.id'),
            'sessionid' => config('services.eed.session_id') ?: 'auto',
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
                if (array_is_list($rows)) {
                    return $rows;
                }

                $values = array_values($rows);

                return collect($values)
                    ->filter(fn (mixed $row): bool => is_array($row))
                    ->values()
                    ->all();
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

    private function capturedTestPayload(string $query, int $page, int $perPage, string $reason): array
    {
        $rows = collect($this->capturedTestRows());
        $tokens = Str::of($query)
            ->ascii()
            ->lower()
            ->explode(' ')
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => strlen($token) >= 3)
            ->values();

        if ($tokens->isNotEmpty()) {
            $rows = $rows->filter(function (array $row) use ($tokens): bool {
                $haystack = Str::of(implode(' ', [
                    $row['artikelnummer'] ?? '',
                    $row['artikelbezeichnung'] ?? '',
                    $row['originalnummer'] ?? '',
                    $row['artikelhersteller'] ?? '',
                    $row['vgruppenname'] ?? '',
                    $row['EAN'] ?? '',
                ]))->ascii()->lower()->toString();

                return $tokens->contains(fn (string $token): bool => str_contains($haystack, $token));
            });
        }

        $total = $rows->count();
        $products = $rows
            ->forPage($page, $perPage)
            ->map(fn (array $row): array => $this->presentArticle($row, 'eed-test', $query))
            ->values()
            ->all();

        return [
            'products' => $products,
            'source' => 'eed-test',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page * $perPage < $total,
                'next_page' => $page * $perPage < $total ? $page + 1 : null,
                'gateway' => 'eed-vpn-captured',
                'live_error' => $reason,
                'captured_query' => 'SONY',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function capturedTestRows(): array
    {
        return [
            [
                'artikelnummer' => 'R423020',
                'artikelbezeichnung' => '149335115 SONY AC-ADAPTER (AC-M1215WW',
                'originalnummer' => '149335115',
                'vgruppenname' => 'AC-Adapter',
                'vgruppenid' => '3121790000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '28,05',
                'disposalcost' => '0',
                'ersatzartikel' => 'J',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/7e/d4/7ed42189271d45f504d596a805587658.jpg?eedcheck=FRZWMIIDRU&checksum=0b418774930af179537f9b4f24d424f9',
            ],
            [
                'artikelnummer' => 'X253364',
                'artikelbezeichnung' => 'A5061064A SONY XPERIA 10 V (XQ-DC54) - SIM-KARTEN-/SPEICHERKARTENHALTER SCHWARZ',
                'originalnummer' => 'A5061064A',
                'vgruppenname' => 'Halter fuer Sim-Karten',
                'vgruppenid' => '5403094000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '2,16',
                'disposalcost' => '0',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/a6/27/a62762b51fe46800dcb53374706cd68d.jpg?eedcheck=SGADWQXVZZ&checksum=4dfd1857fe7bf3d907523a5aab92f8cb',
            ],
            [
                'artikelnummer' => 'G921433',
                'artikelbezeichnung' => '1291-0052 U50032455 AKKU SONY TABLET Z4 6000MAH',
                'originalnummer' => 'U50032455',
                'vgruppenname' => 'Tablet-PC Akkus',
                'vgruppenid' => '3121360000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '24,75',
                'disposalcost' => '0,04',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/b1/23/b123c1ce716a1ea05ca4ec20007f2c51.jpg?eedcheck=YKIUQYOYQH&checksum=c7296be9f3620bf5d5701454bad26351',
            ],
            [
                'artikelnummer' => 'G921448',
                'artikelbezeichnung' => '1291-4764 U50031793 SONY TABLET Z4 DICHTUNG FUER DISPLAYMONTAGE',
                'originalnummer' => 'U50031793',
                'vgruppenname' => 'Klebefolie',
                'vgruppenid' => '5503800000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '4,91',
                'disposalcost' => '0',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/96/2d/962d522ee4c591fa4a6ceb8114697d8c.jpg?eedcheck=UXYEHIEEXL&checksum=91558c22d11713e89630a031aa4b58f9',
            ],
            [
                'artikelnummer' => 'H262506',
                'artikelbezeichnung' => '1299-7881 U50042711 SONY XPERIA X DUAL (F5122) KLEBEFOLIE FUER BATTERIE',
                'originalnummer' => 'U50042711',
                'vgruppenname' => 'Klebefolie',
                'vgruppenid' => '5503800000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '0,9',
                'disposalcost' => '0',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/3a/82/3a82a3814bb201dada94182045c500a9.jpg?eedcheck=KPLCZEUSZY&checksum=7c8a392f35f7cd88e080c315a3bdb5f1',
            ],
            [
                'artikelnummer' => 'X258169',
                'artikelbezeichnung' => 'A5060593A AKKUFACHDECKEL SONY XPERIA 1 V (QX-DQ54) SCHWARZ',
                'originalnummer' => 'A5060593A',
                'vgruppenname' => 'Backcover',
                'vgruppenid' => '5202020000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '49,29',
                'disposalcost' => '0',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/08/db/08db981c6dfe9b678e504abbfd4a61f2.jpg?eedcheck=CIGRTHMDXC&checksum=91390fd18fd10f6b8eefffddd914d08b',
            ],
            [
                'artikelnummer' => 'X329435',
                'artikelbezeichnung' => 'A5047156B AKKUFACHDECKEL SONY XPERIA 10IV (XQ-CC54) SCHWARZ',
                'originalnummer' => 'A5047156B',
                'vgruppenname' => 'Backcover',
                'vgruppenid' => '5202020000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '12,75',
                'disposalcost' => '0',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/fd/c9/fdc90b9fa477d20bc542dc2c242c556b.jpg?eedcheck=YJNZLOCOFW&checksum=5aee77d053e019c7e85daad4d56092ef',
            ],
            [
                'artikelnummer' => 'R326895',
                'artikelbezeichnung' => 'SNYSV24 100628311 AKKU SONY XPERIA 10 II',
                'originalnummer' => '100628311',
                'vgruppenname' => 'GSM-AKKUS',
                'vgruppenid' => '3121311000',
                'artikelhersteller' => 'SONY',
                'lieferzeit' => 'sofort lieferbar (innerhalb von 1-2 Tagen)',
                'lieferzeit_in_tagen' => 2,
                'bestellbar' => 'J',
                'ekpreis' => '18,2',
                'disposalcost' => '0,04',
                'ersatzartikel' => 'N',
                'bild' => 'J',
                'morepics' => 'N',
                'thumbnailurl' => 'https://img.spares-accessories-shop-gmbh.de/apics120/57/b7/57b7954df5a4de92f19e6e26d11a0851.jpg?eedcheck=OPFZBFSDQE&checksum=166073ea58df6043182df2dea1144b50',
            ],
        ];
    }
}
