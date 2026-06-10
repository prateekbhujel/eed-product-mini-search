import { RotateCcw } from 'lucide-react';

export default function FilterPanel({ facets, filters, onChange, onReset }) {
    return (
        <div className="filter-panel">
            <div className="filter-panel-head">
                <div>
                    <p>Filter catalog</p>
                    <h2>Narrow by part family, brand and stock</h2>
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

            <div className="filter-note">
                <strong>Search priority</strong>
                <span>Exact SKU, OEM and model hits are ranked above wording matches.</span>
            </div>
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
