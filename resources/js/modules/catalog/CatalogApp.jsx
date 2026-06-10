import {
    BadgeCheck,
    ChevronRight,
    PackageCheck,
    RotateCcw,
    Search,
    ShoppingCart,
    SlidersHorizontal,
    Truck,
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

    return (
        <div className="min-h-screen bg-[var(--color-page)] text-[var(--color-ink)]">
            <header className="catalog-shell catalog-topbar">
                <div className="brand-lockup" aria-label="E24 spare parts search">
                    <span className="brand-mark">E24</span>
                    <span>
                        <strong>Spare parts search</strong>
                        <small>Parts, OEM numbers, models</small>
                    </span>
                </div>

                <div className="topbar-actions">
                    <span className="status-pill">
                        <PackageCheck size={16} aria-hidden="true" />
                        Catalog ready
                    </span>
                    <button className="icon-button sm:hidden" type="button" onClick={() => setFiltersOpen(true)} aria-label="Open filters">
                        <SlidersHorizontal size={18} aria-hidden="true" />
                        {activeFilterCount > 0 && <span>{activeFilterCount}</span>}
                    </button>
                </div>
            </header>

            <main className="catalog-shell catalog-layout">
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
                    <div className="search-strip">
                        <label className="search-box">
                            <Search size={20} aria-hidden="true" />
                            <input
                                value={filters.q}
                                type="search"
                                placeholder="Search brand, model, OEM number, pump, fridge shelf..."
                                onChange={(event) => updateFilter('q', event.target.value)}
                                autoComplete="off"
                            />
                        </label>
                        <button className="reset-button" type="button" onClick={resetFilters} disabled={activeFilterCount === 0}>
                            <RotateCcw size={16} aria-hidden="true" />
                            Reset
                        </button>
                    </div>

                    <SuggestionStrip suggestions={data.suggestions} onSelect={applySuggestion} />

                    <div className="result-head">
                        <div>
                            <p>{loading ? 'Searching catalog...' : `${data.meta?.result_count ?? 0} matching parts`}</p>
                            <h1>{filters.q ? `Results for "${filters.q}"` : 'Popular spare parts'}</h1>
                        </div>
                        <button className="secondary-button sm:hidden" type="button" onClick={() => setFiltersOpen(true)}>
                            <SlidersHorizontal size={16} aria-hidden="true" />
                            Filters
                            {activeFilterCount > 0 && <span>{activeFilterCount}</span>}
                        </button>
                    </div>

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
                <span>Laravel catalog module</span>
                <ChevronRight size={14} aria-hidden="true" />
                <span>React storefront search</span>
                <ChevronRight size={14} aria-hidden="true" />
                <span>SQLite cache layer</span>
                <span className="footer-spacer" />
                <Truck size={15} aria-hidden="true" />
                <span>Built for fast part lookup and supplier-backed catalog data.</span>
            </footer>
        </div>
    );
}
