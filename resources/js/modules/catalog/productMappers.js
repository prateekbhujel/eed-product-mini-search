const euroFormatter = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });

export function normalizeSupplierProduct(product, index, currentQuery = '') {
    const priceValue = Number(product.price ?? 0);
    const externalId = product.external_id ?? `supplier-${index}`;
    const sourceQuery = product.source_query || currentQuery || '';
    const query = sourceQuery ? `?q=${encodeURIComponent(sourceQuery)}` : '';

    return {
        id: `eed-${externalId}`,
        is_external: true,
        source_label: product.source === 'eed' ? 'EED live' : 'EED test',
        detail_url: `/eed-products/${encodeURIComponent(externalId)}${query}`,
        slug: null,
        sku: String(externalId),
        name: product.name,
        brand: product.brand ?? 'Supplier',
        family: product.category ?? 'Supplier article',
        category: { short_name: product.category ?? 'EED' },
        image_url: product.image_url ?? '/catalog-images/cable.svg',
        gallery_urls: [product.image_url].filter(Boolean),
        identifiers: { oem: [String(externalId)], ean: [] },
        rating: null,
        review_count: null,
        price: {
            value: Number.isFinite(priceValue) ? priceValue : 0,
            display: product.price ? euroFormatter.format(product.price) : 'Price not listed',
            compare_display: null,
        },
        availability: {
            code: product.stock === 0 ? 'backorder' : 'in_stock',
            label: product.stock === 0 ? 'Check stock' : 'In stock',
            delivery: 'Supplier delivery',
            stock: product.stock ?? 1,
        },
    };
}
