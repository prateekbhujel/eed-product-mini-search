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
        ];
    }
}
