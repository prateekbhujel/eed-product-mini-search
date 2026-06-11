const image = (file) => `/catalog-images/${file}`;

const groups = [
    {
        title: 'Top categories in appliance parts',
        tiles: [
            ['Washer pumps', 'Washing machine pump', 'photos/washing-machine-pump.jpg'],
            ['Fridge shelves', 'Refrigerator storage', 'shelf.svg'],
            ['Dishwasher heat', 'Dishwasher heater', 'photos/washing-machine-heater.jpg'],
            ['Door seals', 'Door seal', 'seal.svg'],
        ],
    },
    {
        title: 'Washer, dryer and dishwasher',
        tiles: [
            ['Drain pumps', 'Washing machine pump', 'photos/washing-machine-pump.jpg'],
            ['Dryer belts', 'Dryer belt', 'belt.svg'],
            ['Door locks', 'Door lock and switch', 'lock.svg'],
            ['Heaters', 'Dishwasher heater', 'photos/heater-parts.jpg'],
        ],
    },
    {
        title: 'Cooling and kitchen spares',
        tiles: [
            ['Drawers', 'Refrigerator storage', 'drawer.svg'],
            ['Glass shelves', 'Refrigerator storage', 'shelf.svg'],
            ['Oven elements', 'Oven heating element', 'photos/washing-machine-heater.jpg'],
            ['Coffee parts', 'Coffee machine pump', 'coffee-pump.svg'],
        ],
    },
    {
        title: 'Small parts people search often',
        tiles: [
            ['Vacuum filters', 'Vacuum filter', 'filter.svg'],
            ['Remote controls', 'Remote control', 'remote.svg'],
            ['Cables', 'Cable', 'cable.svg'],
            ['Sensors', 'Thermostat and sensor', 'sensor.svg'],
        ],
    },
];

export default function HomeCategoryGrid({ facets, onSelectFamily }) {
    const counts = new Map((facets?.families ?? []).map((item) => [item.value, item.count]));

    return (
        <section className="home-category-grid" aria-label="Browse spare part categories">
            {groups.map((group) => (
                <article className="home-category-card" key={group.title}>
                    <h2>{group.title}</h2>
                    <div className="home-category-tiles">
                        {group.tiles.map(([label, family, file]) => (
                            <button
                                type="button"
                                key={`${group.title}-${label}`}
                                onClick={() => onSelectFamily(family)}
                            >
                                <img src={image(file)} alt="" loading="lazy" />
                                <span>{label}</span>
                                {counts.has(family) && <small>{counts.get(family).toLocaleString()} items</small>}
                            </button>
                        ))}
                    </div>
                </article>
            ))}
        </section>
    );
}
