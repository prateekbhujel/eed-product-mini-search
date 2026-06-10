import { BadgeCheck, PackageCheck, ShoppingCart, Truck } from 'lucide-react';

export default function ProductDrawer({ product, onClose }) {
    if (!product) {
        return (
            <aside className="detail-panel is-empty">
                <PackageCheck size={28} aria-hidden="true" />
                <h2>Select a part</h2>
                <p>Open a product to check OEM numbers, model compatibility and delivery state.</p>
            </aside>
        );
    }

    const identifiers = product.identifiers ?? {};
    const specs = Object.entries(product.specs ?? {}).filter(([, value]) => Boolean(value));

    return (
        <aside className="detail-panel">
            <div className="detail-head">
                <div>
                    <p>{product.brand}</p>
                    <h2>{product.name}</h2>
                </div>
                <button className="text-close" type="button" onClick={onClose}>Clear</button>
            </div>

            <img className="detail-image" src={product.image_url} alt="" />

            <div className="detail-price-row">
                <strong>{product.price.display}</strong>
                <span>{product.availability.label}</span>
            </div>

            <div className="delivery-box">
                <Truck size={17} aria-hidden="true" />
                <span>{product.availability.delivery}</span>
                <small>{product.availability.stock} units in catalog stock</small>
            </div>

            <section className="detail-section">
                <h3>Part references</h3>
                <dl>
                    <div><dt>SKU</dt><dd>{product.sku}</dd></div>
                    {(identifiers.oem ?? []).length > 0 && <div><dt>OEM</dt><dd>{identifiers.oem.join(', ')}</dd></div>}
                    {(identifiers.ean ?? []).length > 0 && <div><dt>EAN</dt><dd>{identifiers.ean[0]}</dd></div>}
                </dl>
            </section>

            <section className="detail-section">
                <h3>Compatible models</h3>
                <div className="chip-row">
                    {product.compatible_models.map((model) => <span key={model}>{model}</span>)}
                </div>
            </section>

            <section className="detail-section">
                <h3>Specs</h3>
                <dl>
                    {specs.map(([label, value]) => (
                        <div key={label}><dt>{label}</dt><dd>{value}</dd></div>
                    ))}
                </dl>
            </section>

            <div className="trust-row">
                <BadgeCheck size={17} aria-hidden="true" />
                <span>Check model plate before ordering. Exact OEM/model match wins search ranking.</span>
            </div>

            <button type="button" className="wide-buy-button">
                <ShoppingCart size={16} aria-hidden="true" />
                Add to basket
            </button>
        </aside>
    );
}
