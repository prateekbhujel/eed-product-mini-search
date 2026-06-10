export async function searchCatalog(filters, signal) {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });

    const response = await fetch(`/api/catalog/search?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
        },
        signal,
    });

    if (!response.ok) {
        throw new Error('Search request failed');
    }

    return response.json();
}

export async function getProduct(slug, signal) {
    const response = await fetch(`/api/catalog/products/${slug}`, {
        headers: {
            Accept: 'application/json',
        },
        signal,
    });

    if (!response.ok) {
        throw new Error('Product request failed');
    }

    return response.json();
}

export async function searchExternalProducts({ q, page = 1, per_page = 4 }, signal) {
    const params = new URLSearchParams({
        q,
        page: String(page),
        per_page: String(per_page),
    });
    const response = await fetch(`/api/catalog/external-search?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
        },
        signal,
    });

    if (!response.ok) {
        throw new Error('External product request failed');
    }

    return response.json();
}
