<?php

namespace App\Modules\Catalog\Services\ExternalCatalog;

use App\Modules\Catalog\Data\ExternalProductData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EedProductGateway implements SupplierProductGateway
{
    private const REVIEW_QUERIES = ['AEG', 'SONY', 'HDMI'];
    private const EXTENDED_SEARCH_QUERIES = ['SICHERUNG', 'GLAS', 'HOME'];
    private const MANUFACTURER_QUERIES = ['SAMSUNG', 'WHIRLPOOL', 'NORESULT'];
    private const TEST_APPLIANCE_ID = '2119827';

    public function search(string $query, int $page = 1, int $perPage = 8, ?string $visitorIp = null): array
    {
        $query = trim($query);
        $requestedQuery = $query;
        $page = max(1, $page);
        $perPage = min(20, max(4, $perPage));

        if ($query === '') {
            return $this->searchReviewCatalog($page, $perPage, $visitorIp);
        }

        if ($special = $this->searchSpecialTestCommand($query, $page, $perPage, $visitorIp)) {
            return $special;
        }

        [$query, $mapped] = $this->routeDemoQuery($query);
        $keyword = $this->eedKeyword($query);

        if ($keyword === '') {
            return $this->emptyPayload($page, $perPage);
        }

        $call = $this->callEed($this->queryParams($keyword, $page, $perPage), $visitorIp);

        if (! $call['ok']) {
            return $this->capturedTestPayload($query, $page, $perPage, $call['error'], $requestedQuery, $mapped);
        }

        $body = $call['body'];
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
                'requested_query' => $requestedQuery,
                'eed_query' => $query,
                'query_mapped' => $mapped,
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
        $payloads = collect();
        $firstPayload = $this->search('SONY', $page, $perQuery, $visitorIp);
        $payloads->push($firstPayload);

        if ($this->gatewayFailed($firstPayload)) {
            $products = collect($firstPayload['products'] ?? [])
                ->take($perPage)
                ->values()
                ->all();

            return [
                'products' => $products,
                'source' => $firstPayload['source'] ?? 'eed-test',
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => (int) ($firstPayload['meta']['total'] ?? count($products)),
                    'has_more' => false,
                    'next_page' => null,
                    ...($firstPayload['meta'] ?? []),
                    'review_commands' => ['artikelsuche'],
                ],
            ];
        }

        foreach (['AEG', 'HDMI'] as $query) {
            $payloads->push($this->search($query, $page, $perQuery, $visitorIp));
        }

        $payloads->push($this->searchApplianceArticles('TOP', $page, $perQuery, $visitorIp, 'appliance-top'));
        $payloads->push($this->searchApplianceArticles('ELE', $page, $perQuery, $visitorIp, 'appliance-ele'));

        foreach (self::EXTENDED_SEARCH_QUERIES as $query) {
            $payloads->push($this->searchExtendedFamilies($query, $page, $perQuery, $visitorIp));
        }

        foreach (['SAMSUNG', 'WHIRLPOOL'] as $query) {
            $payloads->push($this->searchManufacturers($query, $page, $perQuery, $visitorIp));
        }

        $products = $payloads
            ->flatMap(fn (array $payload): array => $payload['products'] ?? [])
            ->unique('external_id')
            ->take($perPage)
            ->values()
            ->all();
        $total = $payloads->sum(fn (array $payload): int => (int) ($payload['meta']['total'] ?? 0));
        $hasFallbacks = $payloads->contains(fn (array $payload): bool => $this->gatewayFailed($payload));

        return [
            'products' => $products,
            'source' => 'eed',
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page * $perPage < $total,
                'next_page' => $page * $perPage < $total ? $page + 1 : null,
                'gateway' => $hasFallbacks ? 'eed-partial' : 'eed-live',
                'review_queries' => self::REVIEW_QUERIES,
                'eed_query' => implode(',', [...self::REVIEW_QUERIES, ...self::EXTENDED_SEARCH_QUERIES, 'SAMSUNG', 'WHIRLPOOL']),
                'review_commands' => ['artikelsuche', 'artikelsuche_neu', 'geraetehersteller', 'geraeteartikel'],
            ],
        ];
    }

    private function gatewayFailed(array $payload): bool
    {
        return isset($payload['meta']['live_error'])
            || in_array($payload['meta']['gateway'] ?? null, ['eed-vpn-captured', 'eed'], true);
    }

    private function presentArticle(array $row, string $source, string $sourceQuery): array
    {
        $product = ExternalProductData::fromEedArticle($row, $source)->toArray();
        $product['source_query'] = $sourceQuery;

        return $product;
    }

    private function queryParams(string $keyword, int $page, int $perPage): array
    {
        return [
            'art' => 'artikelsuche',
            'suchbg' => $keyword,
            'seite' => $page,
            'anzahl' => $perPage,
        ];
    }

    /**
     * @return array{ok: bool, body?: array<string, mixed>, error?: string}
     */
    private function callEed(array $params, ?string $visitorIp): array
    {
        if (! $this->hasCredentials()) {
            return ['ok' => false, 'error' => 'missing_eed_id'];
        }

        $params = [
            'format' => 'json',
            'id' => config('services.eed.id'),
            'sessionid' => config('services.eed.session_id') ?: 'auto',
            ...$params,
        ];

        if (filled(config('services.eed.shop_url'))) {
            $params['shopurl'] = config('services.eed.shop_url');
        }

        if ($visitorIp) {
            $params['customerip'] = md5($visitorIp);
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.eed.timeout', 8))
                ->retry(1, 250)
                ->get((string) config('services.eed.base_url'), $params);
        } catch (ConnectionException) {
            return ['ok' => false, 'error' => 'connection_failed'];
        }

        if (! $response->ok()) {
            return ['ok' => false, 'error' => 'http_'.$response->status()];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }

        if (($body['fehlernummer'] ?? '0') !== '0') {
            return ['ok' => false, 'error' => 'eed_error_'.$body['fehlernummer']];
        }

        return ['ok' => true, 'body' => $body];
    }

    private function eedKeyword(string $query): string
    {
        $keyword = preg_replace('/[^a-zA-Z0-9,.\-]/', '', Str::ascii($query)) ?? '';

        return strlen($keyword) >= 3 ? $keyword : '';
    }

    /**
     * The public EED test account accepts a very small search set. Keep that
     * limitation inside the adapter so the React storefront can stay API-first.
     *
     * @return array{0: string, 1: bool}
     */
    private function routeDemoQuery(string $query): array
    {
        if (! filter_var(config('services.eed.demo_query_routing', true), FILTER_VALIDATE_BOOL)) {
            return [$query, false];
        }

        $needle = Str::of($query)->ascii()->upper()->toString();
        $compact = preg_replace('/[^A-Z0-9]+/', '', $needle) ?? '';

        if ($compact === 'AEG' || $compact === 'SONY' || $compact === 'HDMI') {
            return [$compact, false];
        }

        $routes = [
            'HDMI' => ['HDMI', 'CABLE', 'KABEL', 'CORD', 'LEAD', 'MAINS'],
            'SONY' => ['SONY', 'XPERIA', 'BRAVIA', 'TABLET', 'AKKU', 'BATTERY', 'ADAPTER', 'REMOTE'],
            'AEG' => [
                'AEG',
                'ELECTROLUX',
                'ZANUSSI',
                'WASHER',
                'WASHING',
                'DISHWASHER',
                'FRIDGE',
                'REFRIGERATOR',
                'KUEHL',
                'KUHL',
                'PUMP',
                'PUMPE',
                'HEATER',
                'SHELF',
                'DRAWER',
                'DOOR',
                'OVEN',
                'THERMOSTAT',
                'VACUUM',
                'FILTER',
                'COFFEE',
            ],
        ];

        foreach ($routes as $eedQuery => $tokens) {
            foreach ($tokens as $token) {
                if (str_contains($needle, $token) || str_contains($compact, $token)) {
                    return [$eedQuery, true];
                }
            }
        }

        return [$query, false];
    }

    private function searchSpecialTestCommand(string $query, int $page, int $perPage, ?string $visitorIp): ?array
    {
        $needle = Str::of($query)->ascii()->upper()->toString();
        $compact = preg_replace('/[^A-Z0-9]+/', '', $needle) ?? '';

        foreach (self::EXTENDED_SEARCH_QUERIES as $keyword) {
            if (str_contains($needle, $keyword)) {
                return $this->searchExtendedFamilies($keyword, $page, $perPage, $visitorIp);
            }
        }

        foreach (self::MANUFACTURER_QUERIES as $keyword) {
            if (str_contains($needle, $keyword)) {
                return $this->searchManufacturers($keyword, $page, $perPage, $visitorIp);
            }
        }

        if ($compact === 'ELE' || str_contains($needle, 'APPLIANCE')) {
            return $this->searchApplianceArticles('ELE', $page, $perPage, $visitorIp, 'appliance-ele');
        }

        return null;
    }

    private function searchExtendedFamilies(string $keyword, int $page, int $perPage, ?string $visitorIp): array
    {
        $call = $this->callEed([
            'art' => 'artikelsuche_neu',
            'suchbg' => $keyword,
            'seite' => $page,
            'anzahl' => $perPage,
        ], $visitorIp);

        if (! $call['ok']) {
            return $this->emptyPayload($page, $perPage, $call['error'] ?? 'extended_search_failed');
        }

        $body = $call['body'];
        $rows = collect($this->familyRows($body));
        $products = $rows
            ->forPage($page, $perPage)
            ->map(fn (array $row): array => $this->presentFamilyHit($row, $keyword))
            ->values()
            ->all();

        return $this->payload($products, $body, $page, $perPage, [
            'gateway' => 'eed-live',
            'eed_command' => 'artikelsuche_neu',
            'eed_query' => $keyword,
            'lookup_type' => 'article_family',
        ]);
    }

    private function searchManufacturers(string $keyword, int $page, int $perPage, ?string $visitorIp): array
    {
        $call = $this->callEed([
            'art' => 'geraetehersteller',
            'suchbg' => $keyword,
            'seite' => $page,
            'anzahl' => $perPage,
        ], $visitorIp);

        if (! $call['ok']) {
            return $this->emptyPayload($page, $perPage, $call['error'] ?? 'manufacturer_search_failed');
        }

        $body = $call['body'];
        $rows = collect($this->articleRows($body));
        $products = $rows
            ->forPage($page, $perPage)
            ->map(fn (array $row): array => $this->presentManufacturerHit($row, $keyword))
            ->values()
            ->all();

        return $this->payload($products, $body, $page, $perPage, [
            'gateway' => 'eed-live',
            'eed_command' => 'geraetehersteller',
            'eed_query' => $keyword,
            'lookup_type' => 'appliance_manufacturer',
        ]);
    }

    private function searchApplianceArticles(string $keyword, int $page, int $perPage, ?string $visitorIp, string $sourceQuery): array
    {
        $params = [
            'art' => 'geraeteartikel',
            'geraeteid' => self::TEST_APPLIANCE_ID,
            'seite' => $page,
            'anzahl' => $perPage,
        ];

        if ($keyword === 'TOP') {
            $params['vgruppe'] = 'TOP';
        } else {
            $params['suchbg'] = $keyword;
        }

        $call = $this->callEed($params, $visitorIp);

        if (! $call['ok']) {
            return $this->emptyPayload($page, $perPage, $call['error'] ?? 'appliance_article_search_failed');
        }

        $body = $call['body'];
        $products = collect($this->articleRows($body))
            ->map(fn (array $row): array => $this->presentArticle($row, 'eed-appliance', $sourceQuery))
            ->values()
            ->all();

        return $this->payload($products, $body, $page, $perPage, [
            'gateway' => 'eed-live',
            'eed_command' => 'geraeteartikel',
            'eed_query' => $keyword,
            'geraeteid' => self::TEST_APPLIANCE_ID,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<string, mixed> $body
     * @param array<string, mixed> $meta
     */
    private function payload(array $products, array $body, int $page, int $perPage, array $meta = []): array
    {
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
                ...$meta,
            ],
        ];
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
     * @return array<int, array<string, mixed>>
     */
    private function familyRows(array $body): array
    {
        foreach (['vgruppentreffer', 'families', 'artikelgruppen', 'treffer', 'results', 'result', 'data'] as $key) {
            $rows = $body[$key] ?? null;

            if (is_array($rows)) {
                if (array_is_list($rows)) {
                    return $rows;
                }

                return collect(array_values($rows))
                    ->filter(fn (mixed $row): bool => is_array($row))
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    private function presentFamilyHit(array $row, string $sourceQuery): array
    {
        $id = $row['vgruppenid'] ?? $row['id'] ?? $row['gruppe'] ?? sha1(json_encode($row, JSON_THROW_ON_ERROR));
        $name = $row['vgruppenname'] ?? $row['name'] ?? $row['bezeichnung'] ?? 'Article family';

        return [
            'external_id' => 'family-'.$id,
            'name' => $name,
            'brand' => 'EED',
            'category' => 'Article family',
            'price' => null,
            'image_url' => null,
            'rating' => null,
            'stock' => null,
            'source' => 'eed-family',
            'source_query' => $sourceQuery,
            'lookup_type' => 'article_family',
            'family_id' => $id,
            'supplier' => [
                'group_id' => $id,
                'group_name' => $name,
            ],
        ];
    }

    private function presentManufacturerHit(array $row, string $sourceQuery): array
    {
        $name = $row['geraetehersteller'] ?? $row['hersteller'] ?? $row['name'] ?? $row['manufacturer'] ?? $sourceQuery;
        $id = $row['herstellerid'] ?? $row['hersteller'] ?? $row['id'] ?? $name;

        return [
            'external_id' => 'manufacturer-'.Str::slug((string) $id),
            'name' => $name,
            'brand' => $name,
            'category' => 'Appliance manufacturer',
            'price' => null,
            'image_url' => null,
            'rating' => null,
            'stock' => null,
            'source' => 'eed-manufacturer',
            'source_query' => $sourceQuery,
            'lookup_type' => 'appliance_manufacturer',
            'manufacturer_id' => $id,
            'supplier' => [
                'manufacturer_id' => $id,
                'manufacturer_name' => $name,
            ],
        ];
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
        $meta = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => 0,
            'has_more' => false,
            'next_page' => null,
            'gateway' => 'eed',
            'empty_reason' => $reason,
        ];

        if ($reason !== 'empty') {
            $meta['live_error'] = $reason;
        }

        return [
            'products' => [],
            'source' => 'eed',
            'meta' => $meta,
        ];
    }

    private function capturedTestPayload(
        string $query,
        int $page,
        int $perPage,
        string $reason,
        ?string $requestedQuery = null,
        bool $mapped = false,
    ): array
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
                'requested_query' => $requestedQuery ?? $query,
                'eed_query' => $query,
                'query_mapped' => $mapped,
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
