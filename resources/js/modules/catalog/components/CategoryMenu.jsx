import { X } from 'lucide-react';

export default function CategoryMenu({ open, facets, onClose, onSelectFamily, onSelectBrand }) {
    if (!open) {
        return null;
    }

    const families = facets.families ?? [];
    const brands = facets.brands ?? [];

    return (
        <>
            <button className="menu-backdrop" type="button" aria-label="Close categories" onClick={onClose} />
            <aside className="category-menu" aria-label="Browse categories">
                <div className="category-menu-head">
                    <strong>Shop by category</strong>
                    <button className="icon-button" type="button" aria-label="Close categories" onClick={onClose}>
                        <X size={18} aria-hidden="true" />
                    </button>
                </div>

                <div className="category-menu-section">
                    <h2>Part families</h2>
                    {families.map((family) => (
                        <button key={family.value} type="button" onClick={() => onSelectFamily(family.value)}>
                            <span>{family.label}</span>
                            <small>{family.count}</small>
                        </button>
                    ))}
                </div>

                <div className="category-menu-section">
                    <h2>Brands</h2>
                    {brands.slice(0, 10).map((brand) => (
                        <button key={brand.value} type="button" onClick={() => onSelectBrand(brand.value)}>
                            <span>{brand.label}</span>
                            <small>{brand.count}</small>
                        </button>
                    ))}
                </div>
            </aside>
        </>
    );
}
