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
import { useEffect, useMemo, useState } from 'react';
import { searchCatalog } from './api.js';
import FilterPanel from './components/FilterPanel.jsx';
import ProductCard from './components/ProductCard.jsx';
import ProductDrawer from './components/ProductDrawer.jsx';
import SuggestionStrip from './components/SuggestionStrip.jsx';

const initialFilters = {
    q: '',
    family: '',
    brand: '',
    availability: '',
};

export default function CatalogApp() {
    const [filters, setFilters] = useState(initialFilters);
    const [data, setData] = useState({
        products: [],
        facets: { families: [], brands: [], availability: [], categories: [] },
        suggestions: [],
        meta: { result_count: 0 },
    });
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [filtersOpen, setFiltersOpen] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            searchCatalog(filters, controller.signal)
                .then((payload) => {
                    setData(payload);
                    setError('');
                    if (selectedProduct) {
                        const stillVisible = payload.products.find((product) => product.id === selectedProduct.id);
                        setSelectedProduct(stillVisible ?? payload.products[0] ?? null);
                    }
                })
                .catch((searchError) => {
                    if (searchError.name !== 'AbortError') {
                        setError('Search is temporarily unavailable. Please try again.');
                    }
                })
                .finally(() => setLoading(false));
        }, 260);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [filters]);

    const activeFilterCount = useMemo(
        () => Object.values(filters).filter(Boolean).length,
        [filters],
    );

    function updateFilter(key, value) {
        setFilters((current) => ({ ...current, [key]: value }));
    }

    function resetFilters() {
        setFilters(initialFilters);
    }

    function applySuggestion(value) {
        setFilters((current) => ({ ...current, q: value }));
    }

    const quickFamilies = [
        'Washing machine pump',
        'Refrigerator storage',
        'Vacuum filter',
        'Remote control',
        'Door seal',
    ];

    return (
        <div className="market-page">
            <header className="market-header">
                <div className="market-header-main">
                    <button className="menu-button" type="button" aria-label="Open categories">
                        <Menu size={22} aria-hidden="true" />
                    </button>

                    <div className="brand-lockup" aria-label="E24 spare parts search">
                        <span className="brand-mark">E24</span>
                        <span>
                            <strong>Parts</strong>
                            <small>appliance spares</small>
                        </span>
                    </div>

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
                            autoComplete="off"
                        />
                        <button type="button" aria-label="Search catalog">
                            <Search size={22} aria-hidden="true" />
                        </button>
                    </div>

                    <button className="account-button" type="button">
                        <CircleUserRound size={18} aria-hidden="true" />
                        <span>
                            <small>Hello, sign in</small>
                            <strong>Account</strong>
                        </span>
                    </button>

                    <button className="cart-button" type="button">
                        <ShoppingCart size={22} aria-hidden="true" />
                        <strong>Basket</strong>
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
                    <span>OEM and model lookup ready</span>
                </nav>
            </header>

            <main className="catalog-shell market-layout">
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
                    <div className="result-head">
                        <div>
                            <p>{loading ? 'Searching catalog...' : `${data.meta?.result_count ?? 0} results`}</p>
                            <h1>{filters.q ? `Spare parts matching "${filters.q}"` : 'Recommended appliance parts'}</h1>
                            <span>Fast matches across SKU, OEM references, model numbers and common wording.</span>
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
                                    selected={selectedProduct?.id === product.id}
                                    onSelect={() => setSelectedProduct(product)}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="empty-state">
                            <BadgeCheck size={28} aria-hidden="true" />
                            <h2>No exact match found</h2>
                            <p>Try the appliance model, OEM reference, or a wider part family like pump, filter, heater, seal, cable or remote.</p>
                        </div>
                    )}
                </section>

                <ProductDrawer product={selectedProduct ?? data.products[0] ?? null} onClose={() => setSelectedProduct(null)} />
            </main>

            <footer className="catalog-shell footer-bar">
                <span>Secure catalog search</span>
                <span>Model and OEM matching</span>
                <span>Supplier-ready Laravel backend</span>
            </footer>
        </div>
    );
}
