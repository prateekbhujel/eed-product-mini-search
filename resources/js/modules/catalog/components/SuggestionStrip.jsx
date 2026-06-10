export default function SuggestionStrip({ suggestions = [], onSelect }) {
    if (suggestions.length === 0) {
        return null;
    }

    return (
        <div className="suggestion-strip" aria-label="Search suggestions">
            <span>Try</span>
            {suggestions.map((suggestion) => (
                <button key={suggestion} type="button" onClick={() => onSelect(suggestion)}>
                    {suggestion}
                </button>
            ))}
        </div>
    );
}
