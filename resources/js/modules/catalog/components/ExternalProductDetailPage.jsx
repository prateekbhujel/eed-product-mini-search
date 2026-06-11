import {
    ArrowLeft,
    CheckCircle2,
    PackageCheck,
    ShieldCheck,
    ShoppingCart,
    Truck,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { searchExternalProducts } from '../api.js';
import { normalizeSupplierProduct } from '../productMappers.js';
import ProductGallery from './ProductGallery.jsx';

export default function ExternalProductDetailPage({ articleNumber, query, onAddToCart }) {
    const [product, setProduct] = useState(null);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const searchQuery = query || '';

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        searchExternalProducts({ q: searchQuery, page: 1, per_page: 20 }, controller.signal)
            .then((payload) => {
                const match = (payload.products ?? []).find((item) => String(item.external_id) === articleNumber);
                const selected = match ?? payload.products?.[0];

                if (!selected) {
                    throw new Error('No supplier product found');
                }

                setProduct(normalizeSupplierProduct(selected, 0, searchQuery));
                setMeta(payload.meta ?? {});
                setError('');
            })
            .catch((requestError) => {
                if (requestError.name !== 'AbortError') {
                    setError('Supplier item could not be loaded.');
                }
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [articleNumber, searchQuery]);

    const refs = useMemo(() => product?.identifiers?.oem ?? [], [product]);

    if (loading) {
        return (
            <main className="catalog-shell detail-page">
                <div className="detail-loading">Loading supplier item...</div>
            </main>
        );
    }

    if (error || !product) {
        return (
            <main className="catalog-shell detail-page">
                <a className="back-link" href="/"><ArrowLeft size={16} aria-hidden="true" /> Back to catalog</a>
                <div className="empty-state">
                    <h1>Supplier item not found</h1>
                    <p>Search again by article number, brand or part name.</p>
                </div>
            </main>
        );
    }

    return (
        <main className="catalog-shell detail-page">
            <a className="back-link" href="/">
                <ArrowLeft size={16} aria-hidden="true" />
                Back to EED results
            </a>

            <section className="product-detail-layout">
                <ProductGallery product={product} />

                <section className="detail-main">
                    <p className="detail-brand">{product.brand} | {product.category.short_name}</p>
                    <h1>{product.name}</h1>

                    <div className="supplier-proof">
                        <strong>{meta.gateway === 'eed-live' ? 'EED live API' : 'EED test data'}</strong>
                        <span>Article data loaded through the supplier gateway.</span>
                    </div>

                    <dl className="detail-table">
                        <InfoRow label="Article number" value={product.sku} />
                        <InfoRow label="Brand" value={product.brand} />
                        <InfoRow label="Family" value={product.family} />
                        <InfoRow label="Source query" value={searchQuery || 'AEG, SONY, HDMI'} />
                    </dl>

                    <section className="detail-block">
                        <h2>Supplier references</h2>
                        <div className="model-list">
                            {refs.map((value) => <span key={value}>{value}</span>)}
                        </div>
                    </section>
                </section>

                <aside className="buy-box">
                    <strong className="buy-price">{product.price.display}</strong>
                    <span className={`stock-badge is-${product.availability.code}`}>
                        <CheckCircle2 size={14} aria-hidden="true" />
                        {product.availability.label}
                    </span>

                    <div className="buy-line">
                        <Truck size={17} aria-hidden="true" />
                        <span>{product.availability.delivery}</span>
                    </div>
                    <div className="buy-line">
                        <PackageCheck size={17} aria-hidden="true" />
                        <span>EED article {product.sku}</span>
                    </div>

                    <button className="wide-buy-button" type="button" onClick={() => onAddToCart(product)}>
                        <ShoppingCart size={17} aria-hidden="true" />
                        Add to basket
                    </button>
                    <button className="wide-secondary-button" type="button">Check compatibility</button>

                    <div className="seller-box">
                        <ShieldCheck size={17} aria-hidden="true" />
                        <span>Supplier source: {product.source_label}</span>
                    </div>
                </aside>
            </section>
        </main>
    );
}

function InfoRow({ label, value }) {
    return (
        <div>
            <dt>{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}
