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
import ExternalSourceStrip from './components/ExternalSourceStrip.jsx';
import FilterPanel from './components/FilterPanel.jsx';
import HomeCategoryGrid from './components/HomeCategoryGrid.jsx';
import ProductCard from './components/ProductCard.jsx';
import ProductDetailPage from './components/ProductDetailPage.jsx';
import SuggestionStrip from './components/SuggestionStrip.jsx';

const initialFilters = {
    q: '',
    family: '',
    brand: '',
    availability: '',
};

function filtersFromLocation() {
    const params = new URLSearchParams(window.location.search);

    return {
        q: params.get('q') ?? '',
        family: params.get('family') ?? '',
        brand: params.get('brand') ?? '',
        availability: params.get('availability') ?? '',
    };
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

export default function CatalogApp() {
    const detailSlug = window.location.pathname.match(/^\/products\/([^/]+)/)?.[1] ?? null;
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

    useEffect(() => {
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
    }, [filters, page]);

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
        if (filters.q.trim().length < 3) {
            setExternalData({ products: [], source: '', meta: {} });
            return undefined;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setExternalLoading(true);
            searchExternalProducts({ q: filters.q, page: 1, per_page: 4 }, controller.signal)
                .then((payload) => setExternalData(payload))
                .catch(() => setExternalData({ products: [], source: '', meta: {} }))
                .finally(() => setExternalLoading(false));
        }, 520);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [filters.q]);

    useEffect(() => {
        const target = loadMoreRef.current;

        if (!target || !data.pagination?.has_more || loading || loadingMore) {
            return undefined;
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                setPage(data.pagination.next_page);
            }
        }, { rootMargin: '420px 0px' });

        observer.observe(target);

        return () => observer.disconnect();
    }, [data.pagination?.has_more, data.pagination?.next_page, loading, loadingMore]);

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

    const resultTitle = useMemo(() => {
        if (filters.q) {
            return `Spare parts matching "${filters.q}"`;
        }

        if (filters.family) {
            return filters.family;
        }

        if (filters.brand) {
            return `${filters.brand} spare parts`;
        }

        return 'Recommended appliance parts';
    }, [filters.brand, filters.family, filters.q]);

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

        if (detailSlug) {
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
        updateFilter('family', value);
        setCategoryOpen(false);
        if (detailSlug) {
            window.location.href = `/?family=${encodeURIComponent(value)}`;
        }
    }

    function selectBrand(value) {
        updateFilter('brand', value);
        setCategoryOpen(false);
        if (detailSlug) {
            window.location.href = `/?brand=${encodeURIComponent(value)}`;
        }
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

    const canLoadMore = Boolean(data.pagination?.has_more && data.pagination?.next_page);
    const showHomeBrowse = !detailSlug && activeFilterCount === 0 && !loading && data.products.length > 0;

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
                            onChange={(event) => updateFilter('family', event.target.value)}
                        >
                            <option value="">All parts</option>
                            {(data.facets.families ?? []).map((option) => (
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
                    <button type="button" onClick={() => updateFilter('family', '')}>All</button>
                    {quickFamilies.map((family) => (
                        <button
                            key={family}
                            type="button"
                            className={filters.family === family ? 'is-active' : ''}
                            onClick={() => updateFilter('family', family)}
                        >
                            {family}
                        </button>
                    ))}
                </nav>
            </header>

            <CategoryMenu
                open={categoryOpen}
                facets={data.facets}
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
                        facets={data.facets}
                        filters={filters}
                        onChange={updateFilter}
                        onReset={resetFilters}
                    />
                </aside>

                {filtersOpen && <button className="sheet-backdrop" type="button" aria-label="Close filters" onClick={() => setFiltersOpen(false)} />}

                <section className="search-workspace">
                    {showHomeBrowse && (
                        <HomeCategoryGrid
                            facets={data.facets}
                            onSelectFamily={selectFamily}
                        />
                    )}

                    <div className="result-head">
                        <div>
                            <p>{loading ? 'Searching catalog...' : `${data.meta?.result_count ?? 0} results`}</p>
                            <h1>{resultTitle}</h1>
                        </div>
                        <div className="result-actions">
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

                    <SuggestionStrip suggestions={data.suggestions} onSelect={applySuggestion} />
                    {data.did_you_mean && filters.q && (
                        <div className="did-you-mean">
                            {'Did you mean '}
                            <button type="button" onClick={() => applySuggestion(data.did_you_mean)}>
                                {data.did_you_mean}
                            </button>
                            {'?'}
                        </div>
                    )}
                    <ExternalSourceStrip data={externalData} loading={externalLoading} />

                    {error && <div className="alert">{error}</div>}

                    {loading ? (
                        <div className="product-grid" aria-busy="true">
                            {Array.from({ length: 8 }).map((_, index) => (
                                <div className="product-card skeleton-card" key={index} />
                            ))}
                        </div>
                    ) : data.products.length > 0 ? (
                        <div className="product-grid">
                            {data.products.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    onAddToCart={addToCart}
                                />
                            ))}
                            {loadingMore && Array.from({ length: 4 }).map((_, index) => (
                                <div className="product-card skeleton-card" key={`page-${page}-${index}`} />
                            ))}
                        </div>
                    ) : (
                        <div className="empty-state">
                            <BadgeCheck size={28} aria-hidden="true" />
                            <h2>No exact match found</h2>
                            <p>Try the appliance model, OEM reference, or a wider part family like pump, filter, heater, seal, cable or remote.</p>
                        </div>
                    )}

                    {canLoadMore && (
                        <div className="load-more-row" ref={loadMoreRef}>
                            <button type="button" onClick={() => setPage(data.pagination.next_page)} disabled={loadingMore}>
                                {loadingMore ? 'Loading...' : 'Load more'}
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
