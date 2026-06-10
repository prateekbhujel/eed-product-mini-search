import {
    ArrowLeft,
    CheckCircle2,
    PackageCheck,
    ShieldCheck,
    ShoppingCart,
    Star,
    Truck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { getProduct, searchCatalog } from '../api.js';
import ProductCard from './ProductCard.jsx';

export default function ProductDetailPage({ slug, onAddToCart }) {
    const [product, setProduct] = useState(null);
    const [related, setRelated] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        getProduct(slug, controller.signal)
            .then((payload) => {
                setProduct(payload.product);
                setError('');
            })
            .catch((requestError) => {
                if (requestError.name !== 'AbortError') {
                    setError('Product could not be loaded.');
                }
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [slug]);

    useEffect(() => {
        if (!product?.family) {
            return undefined;
        }

        const controller = new AbortController();

        searchCatalog({ family: product.family }, controller.signal)
            .then((payload) => {
                setRelated(payload.products.filter((item) => item.id !== product.id).slice(0, 5));
            })
            .catch(() => setRelated([]));

        return () => controller.abort();
    }, [product?.family, product?.id]);

    if (loading) {
        return (
            <main className="catalog-shell detail-page">
                <div className="detail-loading">Loading product...</div>
            </main>
        );
    }

    if (error || !product) {
        return (
            <main className="catalog-shell detail-page">
                <a className="back-link" href="/"><ArrowLeft size={16} aria-hidden="true" /> Back to catalog</a>
                <div className="empty-state">
                    <h1>Product not found</h1>
                    <p>Search again by model, OEM number or part name.</p>
                </div>
            </main>
        );
    }

    const oem = product.identifiers?.oem ?? [];
    const ean = product.identifiers?.ean?.[0];

    return (
        <main className="catalog-shell detail-page">
            <a className="back-link" href="/">
                <ArrowLeft size={16} aria-hidden="true" />
                Back to results
            </a>

            <section className="product-detail-layout">
                <div className="detail-gallery">
                    <img src={product.image_url} alt="" />
                </div>

                <section className="detail-main">
                    <p className="detail-brand">{product.brand} | {product.category.short_name}</p>
                    <h1>{product.name}</h1>

                    <div className="rating-row detail-rating">
                        <span className="stars" aria-label={`${product.rating.toFixed(1)} stars`}>
                            <Star size={15} aria-hidden="true" />
                            <Star size={15} aria-hidden="true" />
                            <Star size={15} aria-hidden="true" />
                            <Star size={15} aria-hidden="true" />
                            <Star size={15} aria-hidden="true" />
                        </span>
                        <span>{product.rating.toFixed(1)}</span>
                        <a href="#reviews">{product.review_count} ratings</a>
                    </div>

                    <p className="detail-description">{product.description}</p>

                    <dl className="detail-table">
                        <InfoRow label="SKU" value={product.sku} />
                        <InfoRow label="OEM" value={oem.join(', ')} />
                        <InfoRow label="EAN" value={ean} />
                        <InfoRow label="Family" value={product.family} />
                    </dl>

                    <section className="detail-block">
                        <h2>Compatible models</h2>
                        <div className="model-list">
                            {product.compatible_models.map((model) => <span key={model}>{model}</span>)}
                        </div>
                    </section>

                    <section className="detail-block">
                        <h2>Product details</h2>
                        <dl className="spec-grid">
                            {Object.entries(product.specs ?? {}).map(([label, value]) => (
                                <InfoRow key={label} label={label} value={value} />
                            ))}
                        </dl>
                    </section>
                </section>

                <aside className="buy-box">
                    <strong className="buy-price">{product.price.display}</strong>
                    {product.price.compare_display && <span className="compare-price">{product.price.compare_display}</span>}
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
                        <span>{product.availability.stock} units in catalog stock</span>
                    </div>

                    <button className="wide-buy-button" type="button" onClick={() => onAddToCart(product)}>
                        <ShoppingCart size={17} aria-hidden="true" />
                        Add to basket
                    </button>
                    <button className="wide-secondary-button" type="button">Check compatibility</button>

                    <div className="seller-box">
                        <ShieldCheck size={17} aria-hidden="true" />
                        <span>Seller demo: E24 appliance spares</span>
                    </div>
                </aside>
            </section>

            <section className="reviews-section" id="reviews">
                <div className="section-head">
                    <h2>Customer reviews</h2>
                    <span>{product.rating.toFixed(1)} out of 5</span>
                </div>
                <div className="review-list">
                    {(product.reviews ?? []).map((review, index) => (
                        <article className="review-card" key={`${review.author_name}-${index}`}>
                            <div className="review-head">
                                <strong>{review.author_name}</strong>
                                <span>{review.reviewed_on}</span>
                            </div>
                            <div className="rating-row">
                                <span className="stars">
                                    <Star size={13} aria-hidden="true" />
                                    <Star size={13} aria-hidden="true" />
                                    <Star size={13} aria-hidden="true" />
                                    <Star size={13} aria-hidden="true" />
                                    <Star size={13} aria-hidden="true" />
                                </span>
                                <strong>{review.title}</strong>
                            </div>
                            <p>{review.body}</p>
                            {review.verified && <small>Verified purchase</small>}
                        </article>
                    ))}
                </div>
            </section>

            {related.length > 0 && (
                <section className="related-section">
                    <div className="section-head">
                        <h2>More from {product.family}</h2>
                    </div>
                    <div className="related-grid">
                        {related.map((item) => (
                            <ProductCard key={item.id} product={item} onAddToCart={onAddToCart} />
                        ))}
                    </div>
                </section>
            )}
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
