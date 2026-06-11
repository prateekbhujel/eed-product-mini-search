import { RotateCcw } from 'lucide-react';

export default function FilterPanel({ facets, filters, onChange, onReset, title = 'Family, brand, stock' }) {
    return (
        <div className="filter-panel">
            <div className="filter-panel-head">
                <div>
                    <p>Filters</p>
                    <h2>{title}</h2>
                </div>
                <button type="button" className="icon-button" onClick={onReset} aria-label="Reset filters">
                    <RotateCcw size={16} aria-hidden="true" />
                </button>
            </div>

            <FacetSelect
                label="Part family"
                value={filters.family}
                options={facets.families}
                placeholder="All families"
                onChange={(value) => onChange('family', value)}
            />

            <FacetSelect
                label="Brand"
                value={filters.brand}
                options={facets.brands}
                placeholder="All brands"
                onChange={(value) => onChange('brand', value)}
            />

            <FacetSelect
                label="Availability"
                value={filters.availability}
                options={facets.availability}
                placeholder="Any availability"
                onChange={(value) => onChange('availability', value)}
            />
        </div>
    );
}

function FacetSelect({ label, value, options = [], placeholder, onChange }) {
    return (
        <label className="field">
            <span>{label}</span>
            <select value={value} onChange={(event) => onChange(event.target.value)}>
                <option value="">{placeholder}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label} ({option.count})
                    </option>
                ))}
            </select>
        </label>
    );
}
