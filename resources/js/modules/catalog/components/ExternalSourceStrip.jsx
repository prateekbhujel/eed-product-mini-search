import { ExternalLink } from 'lucide-react';

export default function ExternalSourceStrip({ data, loading }) {
    const products = data?.products ?? [];

    if (loading) {
        return (
            <section className="external-strip">
                <div className="external-strip-head">
                    <h2>External source</h2>
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
                <h2>External source</h2>
                <span>{data.source}</span>
            </div>
            <div className="external-grid">
                {products.map((product) => (
                    <article key={product.external_id} className="external-card">
                        {product.image_url && <img src={product.image_url} alt="" loading="lazy" />}
                        <div>
                            <p>{product.category}</p>
                            <h3>{product.name}</h3>
                            <span>{product.price ? `${product.price} EUR` : 'Price not listed'}</span>
                        </div>
                    </article>
                ))}
            </div>
            <a className="external-doc-link" href="https://dummyjson.com/docs/products" target="_blank" rel="noreferrer">
                <ExternalLink size={14} aria-hidden="true" />
                API docs
            </a>
        </section>
    );
}
