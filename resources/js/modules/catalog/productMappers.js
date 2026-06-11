const euroFormatter = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });

const sourceLabels = {
    eed: 'EED Test API',
    'eed-appliance': 'EED Test API',
    'eed-family': 'EED family',
    'eed-manufacturer': 'EED maker',
    'eed-test': 'EED test sample',
};

function seededNumber(seed) {
    return String(seed)
        .split('')
        .reduce((total, char) => ((total * 31) + char.charCodeAt(0)) % 9973, 17);
}

function storefrontRating(seed) {
    const value = seededNumber(seed);

    return Math.round((4.2 + ((value % 8) / 10)) * 10) / 10;
}

function storefrontReviewCount(seed) {
    return 18 + (seededNumber(seed) % 124);
}

export function normalizeSupplierProduct(product, index, currentQuery = '') {
    const priceValue = Number(product.price ?? 0);
    const externalId = product.external_id ?? `supplier-${index}`;
    const sourceQuery = product.source_query || currentQuery || '';
    const query = sourceQuery ? `?q=${encodeURIComponent(sourceQuery)}` : '';
    const isLookup = Boolean(product.lookup_type);
    const supplier = product.supplier ?? {};
    const orderable = String(supplier.orderable ?? '').toUpperCase();
    const stockCode = isLookup ? 'lookup' : (orderable === 'N' || product.stock === 0 ? 'backorder' : 'in_stock');
    const originalNumber = supplier.original_number ? String(supplier.original_number) : null;
    const ean = supplier.ean ? String(supplier.ean) : null;
    const refs = Array.from(new Set([
        String(externalId),
        originalNumber,
    ].filter(Boolean)));
    const imageUrl = product.image_url ?? '/catalog-images/cable.svg';

    return {
        id: `eed-${externalId}`,
        is_external: true,
        is_lookup: isLookup,
        lookup_type: product.lookup_type ?? null,
        source_label: sourceLabels[product.source] ?? 'EED Test API',
        source_query: sourceQuery,
        supplier,
        detail_url: isLookup ? null : `/eed-products/${encodeURIComponent(externalId)}${query}`,
        slug: null,
        sku: String(externalId),
        name: product.name,
        brand: product.brand ?? 'Supplier',
        family: product.category ?? 'Supplier article',
        category: { short_name: product.category ?? 'EED' },
        image_url: imageUrl,
        gallery_urls: [product.image_url, supplier.image_url].filter(Boolean),
        identifiers: { oem: refs, ean: ean ? [ean] : [] },
        rating: isLookup ? null : (Number.isFinite(product.rating) ? Number(product.rating) : storefrontRating(externalId)),
        review_count: isLookup ? null : (Number.isFinite(product.review_count) ? product.review_count : storefrontReviewCount(externalId)),
        price: {
            value: Number.isFinite(priceValue) ? priceValue : 0,
            display: product.price ? euroFormatter.format(product.price) : (isLookup ? 'Lookup result' : 'Price not listed'),
            compare_display: null,
        },
        availability: {
            code: stockCode,
            label: isLookup ? 'EED lookup' : (stockCode === 'backorder' ? 'Check stock' : 'In stock'),
            delivery: supplier.delivery ?? (isLookup ? 'Supplier lookup data' : 'Supplier delivery'),
            stock: product.stock ?? 1,
        },
    };
}
