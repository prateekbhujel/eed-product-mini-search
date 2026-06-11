import { ExternalLink } from 'lucide-react';

export default function ExternalSourceStrip({ data, loading }) {
    const products = data?.products ?? [];
    const formatter = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });
    const sourceLabel = {
        'eed-live': 'EED live',
        'eed-vpn-captured': 'EED test data',
    }[data?.meta?.gateway] ?? (data?.source ?? 'supplier');

    if (loading) {
        return (
            <section className="external-strip">
                <div className="external-strip-head">
                    <h2>Supplier adapter</h2>
                    <span>Checking...</span>
                </div>
            </section>
        );
    }

    if (products.length === 0) {
        return null;
    }

    return (
        <section className="external-strip">
            <div className="external-strip-head">
                <h2>Supplier adapter</h2>
                <span>{sourceLabel}</span>
            </div>
            <div className="external-grid">
                {products.map((product) => (
                    <article key={product.external_id} className="external-card">
                        {product.image_url && <img src={product.image_url} alt="" loading="lazy" />}
                        <div>
                            <p>{product.category}</p>
                            <h3>{product.name}</h3>
                            <span>{product.price ? formatter.format(product.price) : 'Price not listed'}</span>
                        </div>
                    </article>
                ))}
            </div>
            <a className="external-doc-link" href="https://shop.euras.com/admin/Dok/eed-doku-eng.php" target="_blank" rel="noreferrer">
                <ExternalLink size={14} aria-hidden="true" />
                EED docs
            </a>
        </section>
    );
}
