import {
    BadgeCheck,
    CircleUserRound,
    ListFilter,
    MapPin,
    Menu,
    RotateCcw,
    Search,
    ShoppingCart,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { searchCatalog, searchExternalProducts } from './api.js';
import CartDrawer from './components/CartDrawer.jsx';
import CategoryMenu from './components/CategoryMenu.jsx';
import FilterPanel from './components/FilterPanel.jsx';
import HomeCategoryGrid from './components/HomeCategoryGrid.jsx';
import ProductCard from './components/ProductCard.jsx';
import ProductDetailPage from './components/ProductDetailPage.jsx';
import SuggestionStrip from './components/SuggestionStrip.jsx';
import ExternalProductDetailPage from './components/ExternalProductDetailPage.jsx';
import { normalizeSupplierProduct } from './productMappers.js';

const initialFilters = {
    q: '',
    family: '',
    brand: '',
    availability: '',
};

function filtersFromLocation() {
    if (isDetailPath()) {
        return { ...initialFilters };
    }

    const params = new URLSearchParams(window.location.search);

    return {
        q: params.get('q') ?? '',
        family: params.get('family') ?? '',
        brand: params.get('brand') ?? '',
        availability: params.get('availability') ?? '',
    };
}

function isDetailPath() {
    return /^\/products\/[^/]+/.test(window.location.pathname)
        || /^\/eed-products\/[^/]+/.test(window.location.pathname);
}

function detailQueryFromLocation() {
    const params = new URLSearchParams(window.location.search);

    return params.get('q') ?? '';
}

function catalogUrlFromFilters(filters) {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });

    return `/${params.toString() ? `?${params.toString()}` : ''}`;
}

const emptyPagination = {
    current_page: 1,
    per_page: 12,
    total: 0,
    has_more: false,
    next_page: null,
};

function sortFacetOptions(options) {
    return Array.from(options.values())
        .sort((first, second) => second.count - first.count || first.label.localeCompare(second.label));
}

function addFacetOption(map, value, label = value) {
    if (!value) {
        return;
    }

    const key = String(value);
    const current = map.get(key);

    if (current) {
        current.count += 1;
        return;
    }

    map.set(key, { value: key, label: String(label || key), count: 1 });
}

function supplierFacetsFromProducts(products) {
    const families = new Map();
    const brands = new Map();
    const availability = new Map();

    products.forEach((product) => {
        addFacetOption(families, product.family || product.category?.short_name);
        addFacetOption(brands, product.brand);
        addFacetOption(availability, product.availability?.code, product.availability?.label);
    });

    return {
        families: sortFacetOptions(families),
        brands: sortFacetOptions(brands),
        availability: sortFacetOptions(availability),
        categories: sortFacetOptions(families),
    };
}

function productMatchesSupplierFilters(product, filters) {
    if (filters.family && filters.family !== product.family && filters.family !== product.category?.short_name) {
        return false;
    }

    if (filters.brand && filters.brand !== product.brand) {
        return false;
    }

    if (filters.availability && filters.availability !== product.availability?.code) {
        return false;
    }

    return true;
}

function uniqueValues(values, limit = 8) {
    const seen = new Set();
    const unique = [];

    values.forEach((value) => {
        const label = String(value ?? '').trim();

        if (!label || seen.has(label.toLowerCase())) {
            return;
        }

        seen.add(label.toLowerCase());
        unique.push(label);
    });

    return unique.slice(0, limit);
}

function supplierSuggestionsFromData(products, facets, meta) {
    const metaQueries = String(meta?.eed_query ?? '')
        .split(',')
        .map((query) => query.trim())
        .filter(Boolean);

    return uniqueValues([
        ...metaQueries,
        ...products.map((product) => product.source_query),
        ...facets.brands.map((option) => option.label),
        ...facets.families.map((option) => option.label),
        ...products.flatMap((product) => product.identifiers?.oem ?? []),
    ], 10);
}

function supplierNavItemsFromFacets(facets) {
    const familyItems = facets.families.slice(0, 5).map((option) => ({
        type: 'family',
        value: option.value,
        label: option.label,
    }));

    if (familyItems.length >= 5) {
        return familyItems;
    }

    const brandItems = facets.brands.slice(0, 5 - familyItems.length).map((option) => ({
        type: 'brand',
        value: option.value,
        label: option.label,
    }));

    return [...familyItems, ...brandItems];
}

export default function CatalogApp() {
    const detailSlug = window.location.pathname.match(/^\/products\/([^/]+)/)?.[1] ?? null;
    const externalArticleNumber = window.location.pathname.match(/^\/eed-products\/([^/]+)/)?.[1] ?? null;
    const isDetailPage = Boolean(detailSlug || externalArticleNumber);
    const detailLookupQuery = useMemo(() => detailQueryFromLocation(), []);
    const [filters, setFilters] = useState(filtersFromLocation);
    const [data, setData] = useState({
        products: [],
        facets: { families: [], brands: [], availability: [], categories: [] },
        suggestions: [],
        did_you_mean: null,
        meta: { result_count: 0 },
        pagination: emptyPagination,
    });
    const [page, setPage] = useState(1);
    const [externalData, setExternalData] = useState({ products: [], source: '', meta: {} });
    const [externalLoadingMore, setExternalLoadingMore] = useState(false);
    const [cartItems, setCartItems] = useState(() => {
        try {
            return JSON.parse(window.localStorage.getItem('e24-cart') ?? '[]');
        } catch {
            return [];
        }
    });
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [externalLoading, setExternalLoading] = useState(false);
    const [error, setError] = useState('');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [cartOpen, setCartOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const loadMoreRef = useRef(null);
    const searchRequestRef = useRef(0);
    const externalRequestRef = useRef(0);

    const supplierMode = !detailSlug && !externalArticleNumber;
    const externalQuery = filters.q.trim().length >= 3 ? filters.q.trim() : '';
    const hasSupplierClientFilters = Boolean(filters.family || filters.brand || filters.availability);
    const supplierBrowse = supplierMode && !externalQuery && !hasSupplierClientFilters;
    const supplierSearch = supplierMode;
    const supplierProducts = useMemo(
        () => (externalData.products ?? []).map((product, index) => normalizeSupplierProduct(product, index, externalQuery)),
        [externalData.products, externalQuery],
    );
    const supplierFacets = useMemo(() => supplierFacetsFromProducts(supplierProducts), [supplierProducts]);
    const filteredSupplierProducts = useMemo(
        () => supplierProducts.filter((product) => productMatchesSupplierFilters(product, filters)),
        [filters, supplierProducts],
    );
    const supplierSuggestions = useMemo(
        () => supplierSuggestionsFromData(supplierProducts, supplierFacets, externalData.meta),
        [externalData.meta, supplierFacets, supplierProducts],
    );
    const supplierNavItems = useMemo(
        () => supplierNavItemsFromFacets(supplierFacets),
        [supplierFacets],
    );
    const usingSupplierMode = supplierMode;
    const activeProducts = usingSupplierMode ? filteredSupplierProducts : data.products;
    const activeFacets = usingSupplierMode ? supplierFacets : data.facets;
    const activePagination = usingSupplierMode && hasSupplierClientFilters
        ? emptyPagination
        : (usingSupplierMode ? (externalData.meta ?? emptyPagination) : data.pagination);
    const activeLoading = usingSupplierMode ? (externalLoading && page === 1) : loading;
    const activeLoadingMore = usingSupplierMode ? externalLoadingMore : loadingMore;

    useEffect(() => {
        if (supplierMode) {
            setLoading(false);
            setLoadingMore(false);

            return undefined;
        }

        const controller = new AbortController();
        const requestId = searchRequestRef.current + 1;
        searchRequestRef.current = requestId;

        if (page === 1) {
            setLoading(true);
            setLoadingMore(false);
            setData((current) => ({
                ...current,
                products: [],
                suggestions: [],
                did_you_mean: null,
                meta: { ...current.meta, result_count: 0 },
                pagination: emptyPagination,
            }));
        } else {
            setLoadingMore(true);
        }

        const timer = window.setTimeout(() => {
            searchCatalog({ ...filters, page, per_page: 12 }, controller.signal)
                .then((payload) => {
                    if (requestId !== searchRequestRef.current) {
                        return;
                    }

                    setData((current) => {
                        if (page === 1) {
                            return payload;
                        }

                        const knownIds = new Set(current.products.map((product) => product.id));
                        const newProducts = payload.products.filter((product) => !knownIds.has(product.id));

                        return {
                            ...payload,
                            products: [...current.products, ...newProducts],
                        };
                    });
                    setError('');
                })
                .catch((searchError) => {
                    if (requestId === searchRequestRef.current && searchError.name !== 'AbortError') {
                        setError('Search is temporarily unavailable. Please try again.');
                    }
                })
                .finally(() => {
                    if (requestId === searchRequestRef.current) {
                        setLoading(false);
                        setLoadingMore(false);
                    }
                });
        }, page === 1 ? 260 : 80);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [filters, page, supplierMode]);

    useEffect(() => {
        const syncFromUrl = () => {
            prepareFreshSearch();
            setPage(1);
            setFilters(filtersFromLocation());
        };

        window.addEventListener('popstate', syncFromUrl);

        return () => window.removeEventListener('popstate', syncFromUrl);
    }, []);

    useEffect(() => {
        if (! supplierSearch) {
            setExternalData({ products: [], source: '', meta: {} });
            return undefined;
        }

        const controller = new AbortController();
        const requestId = externalRequestRef.current + 1;
        externalRequestRef.current = requestId;

        if (page === 1) {
            setExternalData({ products: [], source: '', meta: {} });
            setExternalLoading(true);
            setExternalLoadingMore(false);
        } else {
            setExternalLoadingMore(true);
        }

        const timer = window.setTimeout(() => {
            searchExternalProducts({ q: externalQuery, page, per_page: externalQuery ? 12 : 20 }, controller.signal)
                .then((payload) => {
                    if (requestId !== externalRequestRef.current) {
                        return;
                    }

                    setExternalData((current) => {
                        if (page === 1) {
                            return payload;
                        }

                        const knownIds = new Set((current.products ?? []).map((product) => product.external_id));
                        const newProducts = (payload.products ?? []).filter((product) => !knownIds.has(product.external_id));

                        return {
                            ...payload,
                            products: [...(current.products ?? []), ...newProducts],
                        };
                    });
                })
                .catch(() => {
                    if (requestId === externalRequestRef.current) {
                        setExternalData({ products: [], source: '', meta: {} });
                    }
                })
                .finally(() => {
                    if (requestId === externalRequestRef.current) {
                        setExternalLoading(false);
                        setExternalLoadingMore(false);
                    }
                });
        }, page === 1 ? 360 : 80);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [externalQuery, page, supplierBrowse, supplierSearch]);

    useEffect(() => {
        const target = loadMoreRef.current;

        if (!target || !activePagination?.has_more || activeLoading || activeLoadingMore) {
            return undefined;
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                setPage(activePagination.next_page);
            }
        }, { rootMargin: '420px 0px' });

        observer.observe(target);

        return () => observer.disconnect();
    }, [activePagination?.has_more, activePagination?.next_page, activeLoading, activeLoadingMore]);

    useEffect(() => {
        window.localStorage.setItem('e24-cart', JSON.stringify(cartItems));
    }, [cartItems]);

    const activeFilterCount = useMemo(
        () => Object.values(filters).filter(Boolean).length,
        [filters],
    );

    const cartCount = useMemo(
        () => cartItems.reduce((total, item) => total + item.qty, 0),
        [cartItems],
    );

    function prepareFreshSearch() {
        setLoading(true);
        setLoadingMore(false);
        setError('');
        setData((current) => ({
            ...current,
            products: [],
            suggestions: [],
            did_you_mean: null,
            meta: { ...current.meta, result_count: 0 },
            pagination: emptyPagination,
        }));
    }

    function updateFilter(key, value) {
        if (filters[key] === value && page === 1) {
            return;
        }

        prepareFreshSearch();
        setPage(1);
        setFilters((current) => ({ ...current, [key]: value }));
    }

    function resetFilters() {
        if (activeFilterCount === 0 && page === 1) {
            return;
        }

        prepareFreshSearch();
        setPage(1);
        setFilters(initialFilters);
    }

    function applySuggestion(value) {
        prepareFreshSearch();
        setPage(1);
        setFilters((current) => ({ ...current, q: value }));
    }

    function submitSearch() {
        const nextFilters = { ...filters, q: filters.q.trim() };
        const nextUrl = catalogUrlFromFilters(nextFilters);

        if (isDetailPage) {
            window.location.href = nextUrl;
            return;
        }

        prepareFreshSearch();
        setPage(1);
        setFilters(nextFilters);

        if (`${window.location.pathname}${window.location.search}` !== nextUrl) {
            window.history.pushState({}, '', nextUrl);
        }
    }

    function selectFamily(value) {
        setCategoryOpen(false);
        if (isDetailPage) {
            window.location.href = catalogUrlFromFilters({ ...initialFilters, family: value });
            return;
        }

        updateFilter('family', value);
    }

    function selectBrand(value) {
        setCategoryOpen(false);
        if (isDetailPage) {
            window.location.href = catalogUrlFromFilters({ ...initialFilters, brand: value });
            return;
        }

        updateFilter('brand', value);
    }

    function selectSupplierNavItem(item) {
        if (item.type === 'brand') {
            selectBrand(item.value);
            return;
        }

        if (item.type === 'family') {
            selectFamily(item.value);
            return;
        }

        applySuggestion(item.value);
    }

    function addToCart(product) {
        setCartItems((items) => {
            const existing = items.find((item) => item.product.id === product.id);

            if (existing) {
                return items.map((item) => (
                    item.product.id === product.id ? { ...item, qty: item.qty + 1 } : item
                ));
            }

            return [...items, { product, qty: 1 }];
        });
        setCartOpen(true);
    }

    function updateCartQty(productId, qty) {
        if (qty <= 0) {
            removeFromCart(productId);
            return;
        }

        setCartItems((items) => items.map((item) => (
            item.product.id === productId ? { ...item, qty } : item
        )));
    }

    function removeFromCart(productId) {
        setCartItems((items) => items.filter((item) => item.product.id !== productId));
    }

    const quickFamilies = [
        'Washing machine pump',
        'Refrigerator storage',
        'Vacuum filter',
        'Remote control',
        'Door seal',
    ];

    const canLoadMore = Boolean(activePagination?.has_more && activePagination?.next_page);
    const showHomeBrowse = supplierBrowse && !activeLoading && supplierProducts.length > 0;

    return (
        <div className="market-page">
            <header className="market-header">
                <div className="market-header-main">
                    <button className="menu-button" type="button" aria-label="Open categories" onClick={() => setCategoryOpen(true)}>
                        <Menu size={22} aria-hidden="true" />
                    </button>

                    <a className="brand-lockup" href="/" aria-label="E24 spare parts search">
                        <span className="brand-mark">E24</span>
                        <span>
                            <strong>Parts</strong>
                            <small>appliance spares</small>
                        </span>
                    </a>

                    <div className="ship-line">
                        <MapPin size={17} aria-hidden="true" />
                        <span>
                            <small>Deliver to</small>
                            <strong>Germany & Switzerland</strong>
                        </span>
                    </div>

                    <div className="market-search">
                        <select
                            aria-label="Search family"
                            value={filters.family}
                            onChange={(event) => selectFamily(event.target.value)}
                        >
                            <option value="">All parts</option>
                            {(activeFacets.families ?? []).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <input
                            value={filters.q}
                            type="search"
                            placeholder="Model, OEM, brand or part name"
                            onChange={(event) => updateFilter('q', event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    submitSearch();
                                }
                            }}
                            autoComplete="off"
                        />
                        <button type="button" aria-label="Search catalog" onClick={submitSearch}>
                            <Search size={22} aria-hidden="true" />
                        </button>
                    </div>

                    <div className="account-wrap">
                        <button className="account-button" type="button" onClick={() => setAccountOpen((open) => !open)}>
                            <CircleUserRound size={18} aria-hidden="true" />
                            <span>
                                <small>Hello, sign in</small>
                                <strong>Account</strong>
                            </span>
                        </button>
                        {accountOpen && (
                            <div className="account-menu">
                                <strong>Demo account</strong>
                                <a href="/">Orders</a>
                                <a href="/">Saved models</a>
                                <button type="button" onClick={() => setAccountOpen(false)}>Close</button>
                            </div>
                        )}
                    </div>

                    <button className="cart-button" type="button" onClick={() => setCartOpen(true)}>
                        <ShoppingCart size={22} aria-hidden="true" />
                        <strong>Basket</strong>
                        {cartCount > 0 && <span>{cartCount}</span>}
                    </button>

                    <button className="icon-button mobile-filter-button" type="button" onClick={() => setFiltersOpen(true)} aria-label="Open filters">
                        <SlidersHorizontal size={18} aria-hidden="true" />
                        {activeFilterCount > 0 && <span>{activeFilterCount}</span>}
                    </button>
                </div>

                <nav className="market-subnav" aria-label="Quick categories">
                    <button type="button" onClick={() => selectFamily('')}>All</button>
                    {usingSupplierMode ? (
                        supplierNavItems.map((item) => (
                            <button
                                key={`${item.type}-${item.value}`}
                                type="button"
                                className={
                                    (item.type === 'family' && filters.family === item.value)
                                    || (item.type === 'brand' && filters.brand === item.value)
                                        ? 'is-active'
                                        : ''
                                }
                                onClick={() => selectSupplierNavItem(item)}
                            >
                                {item.label}
                            </button>
                        ))
                    ) : (
                        quickFamilies.map((family) => (
                            <button
                                key={family}
                                type="button"
                                className={filters.family === family ? 'is-active' : ''}
                                onClick={() => selectFamily(family)}
                            >
                                {family}
                            </button>
                        ))
                    )}
                </nav>
            </header>

            <CategoryMenu
                open={categoryOpen}
                facets={activeFacets}
                onClose={() => setCategoryOpen(false)}
                onSelectFamily={selectFamily}
                onSelectBrand={selectBrand}
            />

            <CartDrawer
                open={cartOpen}
                items={cartItems}
                onClose={() => setCartOpen(false)}
                onQtyChange={updateCartQty}
                onRemove={removeFromCart}
            />

            {detailSlug ? (
                <ProductDetailPage slug={decodeURIComponent(detailSlug)} onAddToCart={addToCart} />
            ) : externalArticleNumber ? (
                <ExternalProductDetailPage
                    articleNumber={decodeURIComponent(externalArticleNumber)}
                    query={detailLookupQuery || decodeURIComponent(externalArticleNumber)}
                    onAddToCart={addToCart}
                />
            ) : (
            <main className={`catalog-shell market-layout ${showHomeBrowse ? 'is-home' : ''}`}>
                <aside className={`filter-sheet ${filtersOpen ? 'is-open' : ''}`}>
                    <div className="mobile-sheet-head">
                        <strong>Filters</strong>
                        <button className="icon-button" type="button" onClick={() => setFiltersOpen(false)} aria-label="Close filters">
                            <X size={18} aria-hidden="true" />
                        </button>
                    </div>
                    <FilterPanel
                        facets={activeFacets}
                        filters={filters}
                        onChange={updateFilter}
                        onReset={resetFilters}
                        title={usingSupplierMode ? 'Live supplier fields' : 'Family, brand, stock'}
                    />
                </aside>

                {filtersOpen && <button className="sheet-backdrop" type="button" aria-label="Close filters" onClick={() => setFiltersOpen(false)} />}

                <section className="search-workspace">
                    {showHomeBrowse && (
                        <HomeCategoryGrid
                            facets={activeFacets}
                            supplierProducts={supplierProducts}
                            onSelectFamily={selectFamily}
                            onSelectBrand={selectBrand}
                            onSelectQuery={applySuggestion}
                        />
                    )}

                    <div className="catalog-toolbar">
                        <SuggestionStrip
                            suggestions={usingSupplierMode ? supplierSuggestions : data.suggestions}
                            onSelect={applySuggestion}
                        />
                        <div className="catalog-toolbar-actions">
                            <button className="reset-button" type="button" onClick={resetFilters} disabled={activeFilterCount === 0}>
                                <RotateCcw size={16} aria-hidden="true" />
                                Reset
                            </button>
                            <button className="secondary-button mobile-only" type="button" onClick={() => setFiltersOpen(true)}>
                                <ListFilter size={16} aria-hidden="true" />
                                Filter
                                {activeFilterCount > 0 && <span>{activeFilterCount}</span>}
                            </button>
                        </div>
                    </div>
                    {!usingSupplierMode && data.did_you_mean && filters.q && (
                        <div className="did-you-mean">
                            {'Did you mean '}
                            <button type="button" onClick={() => applySuggestion(data.did_you_mean)}>
                                {data.did_you_mean}
                            </button>
                            {'?'}
                        </div>
                    )}

                    {error && <div className="alert">{error}</div>}

                    {activeLoading ? (
                        <div className="product-grid" aria-busy="true">
                            {Array.from({ length: 8 }).map((_, index) => (
                                <div className="product-card skeleton-card" key={index} />
                            ))}
                        </div>
                    ) : activeProducts.length > 0 ? (
                        <div className="product-grid">
                            {activeProducts.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    onAddToCart={addToCart}
                                />
                            ))}
                            {activeLoadingMore && Array.from({ length: 4 }).map((_, index) => (
                                <div className="product-card skeleton-card" key={`page-${page}-${index}`} />
                            ))}
                        </div>
                    ) : (
                        <div className="empty-state">
                            <BadgeCheck size={28} aria-hidden="true" />
                            <h2>No supplier match found</h2>
                            <p>Try AEG, Sony, HDMI, GLAS, HOME, Samsung or Whirlpool.</p>
                        </div>
                    )}

                    {canLoadMore && (
                        <div className="load-more-row" ref={loadMoreRef}>
                            <button type="button" onClick={() => setPage(activePagination.next_page)} disabled={activeLoadingMore}>
                                {activeLoadingMore ? 'Loading...' : 'Load more'}
                            </button>
                        </div>
                    )}
                </section>
            </main>
            )}

            <footer className="catalog-shell footer-bar">
                <span>Catalog search</span>
                <span>Model and OEM matching</span>
                <span>Laravel backend</span>
            </footer>
        </div>
    );
}
