import { CheckCircle2, ShoppingCart, Star } from 'lucide-react';

export default function ProductCard({ product, selected, onSelect }) {
    const oem = product.identifiers?.oem?.slice(0, 2) ?? [];
    const models = product.compatible_models?.slice(0, 3) ?? [];

    return (
        <article className={`product-card ${selected ? 'is-selected' : ''}`}>
            <button className="product-main" type="button" onClick={onSelect}>
                <span className="image-box">
                    <img src={product.image_url} alt="" loading="lazy" />
                </span>
                <div>
                    <p className="card-kicker">{product.brand} | {product.category.short_name}</p>
                    <h2>{product.name}</h2>
                </div>
            </button>

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

            <p className="delivery-line">{product.availability.delivery}. Match by OEM or model before ordering.</p>

            <div className="chip-row">
                {oem.map((value) => <span key={value}>OEM {value}</span>)}
                {models.map((value) => <span key={value}>{value}</span>)}
            </div>

            <div className="card-actions">
                <button type="button" className="text-link" onClick={onSelect}>View fitment</button>
                <button type="button" className="buy-button">
                    <ShoppingCart size={15} aria-hidden="true" />
                    Add to basket
                </button>
            </div>
        </article>
    );
}
