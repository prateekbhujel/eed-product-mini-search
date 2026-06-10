import { PackageCheck, ShieldCheck, ShoppingCart, Truck } from 'lucide-react';

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
        <aside className="detail-panel buy-box-panel">
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

            <label className="quantity-row">
                <span>Qty</span>
                <select defaultValue="1">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
            </label>

            <button type="button" className="wide-buy-button">
                <ShoppingCart size={16} aria-hidden="true" />
                Add to basket
            </button>

            <button type="button" className="wide-secondary-button">
                Check compatibility
            </button>

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
                <ShieldCheck size={17} aria-hidden="true" />
                <span>Catalog result is ranked by exact SKU, OEM and appliance model signals.</span>
            </div>
        </aside>
    );
}
