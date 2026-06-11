const image = (file) => `/catalog-images/${file}`;

const groups = [
    {
        title: 'Top categories in appliance parts',
        tiles: [
            ['Washer pumps', 'Washing machine pump', 'photos/washing-machine-pump.jpg'],
            ['Fridge shelves', 'Refrigerator storage', 'shelf.svg'],
            ['Dishwasher heat', 'Dishwasher heater', 'photos/washing-machine-heater.jpg'],
            ['Door seals', 'Door seal', 'seal.svg'],
        ],
    },
    {
        title: 'Washer, dryer and dishwasher',
        tiles: [
            ['Drain pumps', 'Washing machine pump', 'photos/washing-machine-pump.jpg'],
            ['Dryer belts', 'Dryer belt', 'belt.svg'],
            ['Door locks', 'Door lock and switch', 'lock.svg'],
            ['Heaters', 'Dishwasher heater', 'photos/heater-parts.jpg'],
        ],
    },
    {
        title: 'Cooling and kitchen spares',
        tiles: [
            ['Drawers', 'Refrigerator storage', 'drawer.svg'],
            ['Glass shelves', 'Refrigerator storage', 'shelf.svg'],
            ['Oven elements', 'Oven heating element', 'photos/washing-machine-heater.jpg'],
            ['Coffee parts', 'Coffee machine pump', 'coffee-pump.svg'],
        ],
    },
    {
        title: 'Small parts people search often',
        tiles: [
            ['Vacuum filters', 'Vacuum filter', 'filter.svg'],
            ['Remote controls', 'Remote control', 'remote.svg'],
            ['Cables', 'Cable', 'cable.svg'],
            ['Sensors', 'Thermostat and sensor', 'sensor.svg'],
        ],
    },
];

function bySupplierQuery(products) {
    const grouped = new Map();

    products.forEach((product) => {
        const query = product.source_query || product.brand || product.category?.short_name || 'EED';

        if (!grouped.has(query)) {
            grouped.set(query, []);
        }

        const bucket = grouped.get(query);
        const key = product.category?.short_name || product.sku;

        if (!bucket.some((item) => (item.category?.short_name || item.sku) === key)) {
            bucket.push(product);
        }
    });

    return grouped;
}

function supplierTitle(query) {
    if (query === 'AEG') {
        return 'AEG and Electrolux from EED';
    }

    if (query === 'SONY') {
        return 'Sony parts from EED';
    }

    if (query === 'HDMI') {
        return 'Cable articles from EED';
    }

    return `${query} supplier articles`;
}

function firstProductFor(products, predicate) {
    return products.find(predicate) ?? products[0];
}

function SupplierHomeGrid({ facets, products, onSelectFamily, onSelectBrand, onSelectQuery }) {
    const familyTiles = (facets?.families ?? []).slice(0, 4).map((family) => {
        const product = firstProductFor(products, (item) => item.family === family.value || item.category?.short_name === family.value);

        return {
            key: `family-${family.value}`,
            label: family.label,
            meta: `${family.count.toLocaleString()} items`,
            imageUrl: product?.image_url,
            onClick: () => onSelectFamily(family.value),
        };
    });

    const brandTiles = (facets?.brands ?? []).slice(0, 4).map((brand) => {
        const product = firstProductFor(products, (item) => item.brand === brand.value);

        return {
            key: `brand-${brand.value}`,
            label: brand.label,
            meta: `${brand.count.toLocaleString()} items`,
            imageUrl: product?.image_url,
            onClick: () => onSelectBrand(brand.value),
        };
    });

    const articleTiles = products
        .filter((product) => !product.is_lookup)
        .slice(0, 4)
        .map((product) => ({
            key: `article-${product.id}`,
            label: product.category?.short_name || product.brand,
            meta: product.sku,
            imageUrl: product.image_url,
            onClick: () => onSelectQuery(product.sku),
        }));

    const lookupTiles = products
        .filter((product) => product.is_lookup)
        .slice(0, 4)
        .map((product) => ({
            key: `lookup-${product.id}`,
            label: product.name,
            meta: product.source_label,
            imageUrl: product.image_url,
            onClick: () => onSelectQuery(product.source_query || product.brand || product.name),
        }));

    const grouped = bySupplierQuery(products);
    const sourceTiles = Array.from(grouped.entries()).slice(0, 4).map(([query, items]) => ({
        key: `query-${query}`,
        label: supplierTitle(query),
        meta: `${items.length.toLocaleString()} rows`,
        imageUrl: items[0]?.image_url,
        onClick: () => onSelectQuery(query),
    }));

    const sections = [
        { title: 'EED part families', items: familyTiles },
        { title: 'Supplier brands', items: brandTiles },
        { title: 'EED article rows', items: articleTiles },
        { title: 'EED lookup groups', items: lookupTiles.length > 0 ? lookupTiles : sourceTiles },
    ].filter((section) => section.items.length > 0).slice(0, 4);

    return (
        <section className="home-category-grid" aria-label="Browse EED Test API rows">
            {sections.map((section) => (
                <article className="home-category-card" key={section.title}>
                    <h2>{section.title}</h2>
                    <div className="home-category-tiles">
                        {section.items.map((item) => (
                            <button
                                type="button"
                                key={item.key}
                                onClick={item.onClick}
                            >
                                <img src={item.imageUrl || image('cable.svg')} alt="" loading="lazy" decoding="async" />
                                <span>{item.label}</span>
                                <small>{item.meta}</small>
                            </button>
                        ))}
                    </div>
                </article>
            ))}
        </section>
    );
}

export default function HomeCategoryGrid({ facets, supplierProducts = [], onSelectFamily, onSelectBrand, onSelectQuery }) {
    if (supplierProducts.length > 0 && onSelectQuery) {
        return (
            <SupplierHomeGrid
                facets={facets}
                products={supplierProducts}
                onSelectFamily={onSelectFamily}
                onSelectBrand={onSelectBrand}
                onSelectQuery={onSelectQuery}
            />
        );
    }

    const counts = new Map((facets?.families ?? []).map((item) => [item.value, item.count]));

    return (
        <section className="home-category-grid" aria-label="Browse spare part categories">
            {groups.map((group) => (
                <article className="home-category-card" key={group.title}>
                    <h2>{group.title}</h2>
                    <div className="home-category-tiles">
                        {group.tiles.map(([label, family, file]) => (
                            <button
                                type="button"
                                key={`${group.title}-${label}`}
                                onClick={() => onSelectFamily(family)}
                            >
                                <img src={image(file)} alt="" loading="lazy" />
                                <span>{label}</span>
                                {counts.has(family) && <small>{counts.get(family).toLocaleString()} items</small>}
                            </button>
                        ))}
                    </div>
                </article>
            ))}
        </section>
    );
}
