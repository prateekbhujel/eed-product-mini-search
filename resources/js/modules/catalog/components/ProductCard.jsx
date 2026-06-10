import { Info, ShoppingCart, Star } from 'lucide-react';

export default function ProductCard({ product, selected, onSelect }) {
    const oem = product.identifiers?.oem?.slice(0, 2) ?? [];
    const models = product.compatible_models?.slice(0, 3) ?? [];

    return (
        <article className={`product-card ${selected ? 'is-selected' : ''}`}>
            <button className="product-main" type="button" onClick={onSelect}>
                <img src={product.image_url} alt="" loading="lazy" />
                <span className={`stock-badge is-${product.availability.code}`}>{product.availability.label}</span>
                <div>
                    <p className="card-kicker">{product.brand} · {product.category.short_name}</p>
                    <h2>{product.name}</h2>
                    <p className="card-description">{product.description}</p>
                </div>
            </button>

            <div className="card-meta">
                <div className="price-block">
                    <strong>{product.price.display}</strong>
                    {product.price.compare_display && <span>{product.price.compare_display}</span>}
                </div>
                <span className="rating-line">
                    <Star size={14} aria-hidden="true" />
                    {product.rating.toFixed(1)} · {product.review_count}
                </span>
            </div>

            <div className="chip-row">
                {oem.map((value) => <span key={value}>OEM {value}</span>)}
                {models.map((value) => <span key={value}>{value}</span>)}
            </div>

            <div className="card-actions">
                <button type="button" className="secondary-button" onClick={onSelect}>
                    <Info size={15} aria-hidden="true" />
                    Details
                </button>
                <button type="button" className="buy-button">
                    <ShoppingCart size={15} aria-hidden="true" />
                    Add
                </button>
            </div>
        </article>
    );
}
