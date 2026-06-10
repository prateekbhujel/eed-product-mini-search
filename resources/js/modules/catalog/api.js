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
