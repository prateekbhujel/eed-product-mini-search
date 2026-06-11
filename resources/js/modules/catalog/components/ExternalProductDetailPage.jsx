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
    const searchQuery = query || articleNumber || '';

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        searchExternalProducts({ q: searchQuery, page: 1, per_page: 20 }, controller.signal)
            .then((payload) => {
                const match = (payload.products ?? []).find((item) => String(item.external_id) === articleNumber);

                if (!match) {
                    throw new Error('No supplier product found');
                }

                setProduct(normalizeSupplierProduct(match, 0, searchQuery));
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
    const supplier = product?.supplier ?? {};
    const eedCommand = meta.eed_command ?? 'artikelsuche';
    const proofText = [
        product?.sku,
        product?.brand,
        supplier.group_name || product?.family,
    ].filter(Boolean).join(' | ');
    const supplierRows = [
        ['Article number', product?.sku],
        ['Original number', supplier.original_number],
        ['EAN', supplier.ean],
        ['Brand', product?.brand],
        ['Family', supplier.group_name || product?.family],
        ['Family ID', supplier.group_id],
        ['Delivery', supplier.delivery],
        ['Delivery days', supplier.delivery_days],
        ['Orderable', supplier.orderable],
        ['Replacement article', supplier.replacement_article],
        ['Article flags', supplier.article_features],
        ['Disposal cost', supplier.disposal_cost],
        ['Image flags', [supplier.picture, supplier.more_pictures].filter(Boolean).join(' / ')],
        ['Source query', searchQuery],
    ].filter(([, value]) => value !== undefined && value !== null && value !== '');
    const manufacturerAddress = supplier.manufacturer_address ?? {};

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
                        <strong>EED Test API</strong>
                        <span>{eedCommand} mapped into storefront fields: {proofText}</span>
                    </div>

                    <dl className="detail-table">
                        {supplierRows.map(([label, value]) => (
                            <InfoRow key={label} label={label} value={value} />
                        ))}
                    </dl>

                    {supplier.description && (
                        <section className="detail-block">
                            <h2>Supplier description</h2>
                            <p className="detail-description">{supplier.description}</p>
                        </section>
                    )}

                    {Object.keys(manufacturerAddress).length > 0 && (
                        <section className="detail-block">
                            <h2>Manufacturer address</h2>
                            <dl className="detail-table">
                                {Object.entries(manufacturerAddress).map(([label, value]) => (
                                    <InfoRow key={label} label={label.replace('_', ' ')} value={value} />
                                ))}
                            </dl>
                        </section>
                    )}

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
