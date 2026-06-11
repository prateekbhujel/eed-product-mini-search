import { CheckCircle2, ShoppingCart, Star } from 'lucide-react';

export default function ProductCard({ product, onAddToCart }) {
    const oem = product.identifiers?.oem?.slice(0, 2) ?? [];
    const detailUrl = product.detail_url ?? (product.slug ? `/products/${product.slug}` : null);
    const hasDetailPage = Boolean(detailUrl);
    const hasRating = Number.isFinite(product.rating);
    const header = (
        <>
            <span className="image-box">
                <img src={product.image_url} alt="" loading="lazy" decoding="async" />
            </span>
            <div>
                <p className="card-kicker">{product.brand} | {product.category.short_name}</p>
                <h2>{product.name}</h2>
            </div>
        </>
    );

    return (
        <article className="product-card">
            {hasDetailPage ? (
                <a className="product-main" href={detailUrl}>
                    {header}
                </a>
            ) : (
                <div className="product-main is-static">
                    {header}
                </div>
            )}

            {hasRating ? (
                <div className="rating-row">
                    <span className="stars" aria-label={`${product.rating.toFixed(1)} stars`}>
                        <Star size={14} aria-hidden="true" />
                        <Star size={14} aria-hidden="true" />
                        <Star size={14} aria-hidden="true" />
                        <Star size={14} aria-hidden="true" />
                        <Star size={14} aria-hidden="true" />
                    </span>
                    <span>{product.rating.toFixed(1)}</span>
                    <span>{product.review_count}</span>
                </div>
            ) : (
                <div className="rating-row supplier-row">
                    <span>{product.source_label ?? 'Supplier item'}</span>
                    <span>{product.sku}</span>
                </div>
            )}

            <div className="card-meta">
                <div className="price-block">
                    <strong>{product.price.display}</strong>
                    {product.price.compare_display && <span>{product.price.compare_display}</span>}
                </div>
                <span className={`stock-badge is-${product.availability.code}`}>
                    <CheckCircle2 size={13} aria-hidden="true" />
                    {product.availability.label}
                </span>
            </div>

            <div className="chip-row">
                {oem.map((value) => <span key={value}>OEM {value}</span>)}
            </div>

            <div className="card-actions">
                {hasDetailPage ? (
                    <a className="text-link" href={detailUrl}>View details</a>
                ) : (
                    <span className="text-link is-muted">EED item</span>
                )}
                <button type="button" className="buy-button" onClick={() => onAddToCart(product)}>
                    <ShoppingCart size={15} aria-hidden="true" />
                    Add
                </button>
            </div>
        </article>
    );
}
