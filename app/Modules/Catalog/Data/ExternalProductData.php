<?php

namespace App\Modules\Catalog\Data;

class ExternalProductData
{
    public function __construct(
        public readonly int|string|null $externalId,
        public readonly string $name,
        public readonly ?string $brand,
        public readonly ?string $category,
        public readonly ?float $price,
        public readonly ?string $imageUrl,
        public readonly ?float $rating,
        public readonly ?int $stock,
        public readonly string $source,
        public readonly array $supplier = [],
    ) {}

    public static function fromEedArticle(array $row, string $source = 'eed'): self
    {
        $price = $row['ekpreis']
            ?? $row['vkpreis']
            ?? $row['preis']
            ?? $row['price']
            ?? null;

        if (is_string($price)) {
            $price = str_replace(',', '.', $price);
        }

        $stock = null;
        if (isset($row['bestellbar'])) {
            $stock = strtoupper((string) $row['bestellbar']) === 'J' ? 1 : 0;
        }

        $articleFeatures = $row['artikelmerkmal'] ?? null;

        if (is_array($articleFeatures)) {
            $articleFeatures = implode(', ', array_filter(array_map('strval', $articleFeatures)));
        }

        $manufacturerAddress = $row['herstelleradresse'] ?? null;

        if (is_array($manufacturerAddress)) {
            $manufacturerAddress = array_filter([
                'name' => $manufacturerAddress['hersteller']['name'] ?? $manufacturerAddress['name'] ?? null,
                'street' => trim(implode(' ', array_filter([
                    $manufacturerAddress['strasse'] ?? null,
                    $manufacturerAddress['hausnummer'] ?? null,
                ]))),
                'city' => trim(implode(' ', array_filter([
                    $manufacturerAddress['plz'] ?? null,
                    $manufacturerAddress['ort'] ?? null,
                ]))),
                'country' => $manufacturerAddress['land'] ?? null,
                'email' => $manufacturerAddress['email'] ?? null,
                'internet' => $manufacturerAddress['internet'] ?? null,
            ]);
        }

        $supplier = array_filter([
            'article_number' => $row['artikelnummer'] ?? $row['artnr'] ?? $row['id'] ?? null,
            'original_number' => $row['originalnummer'] ?? null,
            'ean' => $row['EAN'] ?? $row['ean'] ?? null,
            'group_id' => $row['vgruppenid'] ?? null,
            'group_name' => $row['vgruppenname'] ?? null,
            'delivery' => $row['lieferzeit'] ?? null,
            'delivery_days' => $row['lieferzeit_in_tagen'] ?? null,
            'orderable' => $row['bestellbar'] ?? null,
            'replacement_article' => $row['ersatzartikel'] ?? null,
            'picture' => $row['bild'] ?? null,
            'more_pictures' => $row['morepics'] ?? null,
            'article_features' => $articleFeatures,
            'disposal_cost' => $row['disposalcost'] ?? null,
            'manufacturer_address' => $manufacturerAddress,
            'description' => $row['artikeltext'] ?? $row['beschreibung'] ?? $row['description'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return new self(
            externalId: $row['artikelnummer'] ?? $row['artnr'] ?? $row['id'] ?? null,
            name: $row['artikelbezeichnung'] ?? $row['bezeichnung'] ?? $row['name'] ?? 'Untitled article',
            brand: $row['artikelhersteller'] ?? $row['hersteller'] ?? $row['manufacturer'] ?? null,
            category: $row['vgruppenname'] ?? $row['warengruppe'] ?? $row['category'] ?? null,
            price: is_numeric($price) ? (float) $price : null,
            imageUrl: $row['thumbnailurl'] ?? $row['bildurl'] ?? $row['thumbnail'] ?? $row['image_url'] ?? null,
            rating: null,
            stock: $stock,
            source: $source,
            supplier: $supplier,
        );
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'price' => $this->price,
            'image_url' => $this->imageUrl,
            'rating' => $this->rating,
            'stock' => $this->stock,
            'source' => $this->source,
            'supplier' => $this->supplier,
        ];
    }
}
