<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCompatibleModel;
use App\Modules\Catalog\Models\ProductIdentifier;
use App\Modules\Catalog\Models\ProductReview;
use App\Modules\Search\Models\SearchSynonym;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $supplier = DB::table('supplier_sources')->updateOrInsert(
                ['code' => 'eed'],
                [
                    'name' => 'ASWO / EED catalog feed',
                    'connector' => 'eed-catalog',
                    'sync_rules' => json_encode(['price_stock_refresh' => 'hourly', 'catalog_refresh' => 'nightly']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $supplierId = DB::table('supplier_sources')->where('code', 'eed')->value('id');
            $categories = $this->seedCategories();
            $this->seedSynonyms();

            foreach ($this->products() as $row) {
                $category = $categories[$row['category']];
                $product = Product::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'category_id' => $category->id,
                        'supplier_source_id' => $supplierId,
                        'slug' => Str::slug($row['brand'].' '.$row['name'].' '.$row['sku']),
                        'brand' => $row['brand'],
                        'name' => $row['name'],
                        'family' => $row['family'],
                        'description' => $row['description'],
                        'price' => $row['price'],
                        'compare_price' => $row['compare_price'] ?? null,
                        'currency' => 'EUR',
                        'availability' => $row['availability'],
                        'delivery_text' => $row['delivery_text'],
                        'delivery_days' => $row['delivery_days'],
                        'stock_quantity' => $row['stock'],
                        'rating' => $row['rating'],
                        'review_count' => $row['review_count'],
                        'image_path' => $row['image_path'],
                        'search_keywords' => $row['keywords'],
                        'specs' => $row['specs'],
                        'is_active' => true,
                        'indexed_at' => now(),
                    ],
                );

                $this->replaceIdentifiers($product, $row['identifiers']);
                $this->replaceModels($product, $row['models']);
                $this->replaceReviews($product);
            }
        });
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $root = Category::query()->updateOrCreate(
            ['slug' => 'appliance-parts'],
            [
                'name' => 'Appliance spare parts',
                'short_name' => 'All parts',
                'path' => 'Appliance spare parts',
                'depth' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $rows = [
            ['washing-machine-pumps', 'Washing machine pumps', 'Washer pumps'],
            ['dishwasher-heaters', 'Dishwasher heaters', 'Dishwasher heat'],
            ['refrigerator-storage', 'Refrigerator shelves and drawers', 'Fridge storage'],
            ['vacuum-filters', 'Vacuum cleaner filters', 'Vacuum filters'],
            ['oven-heating', 'Oven heating elements', 'Oven heat'],
            ['dryer-belts', 'Tumble dryer belts', 'Dryer belts'],
            ['door-seals', 'Door seals and gaskets', 'Door seals'],
            ['coffee-machine-parts', 'Coffee machine parts', 'Coffee parts'],
            ['remote-controls', 'Remote controls', 'Remotes'],
            ['cables-connectors', 'Cables and connectors', 'Cables'],
            ['thermostats-sensors', 'Thermostats and sensors', 'Sensors'],
            ['door-locks-switches', 'Door locks and switches', 'Locks'],
        ];

        return collect($rows)
            ->mapWithKeys(function (array $row, int $index) use ($root): array {
                $category = Category::query()->updateOrCreate(
                    ['slug' => $row[0]],
                    [
                        'parent_id' => $root->id,
                        'name' => $row[1],
                        'short_name' => $row[2],
                        'path' => $root->name.' > '.$row[1],
                        'depth' => 1,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ],
                );

                return [$row[0] => $category];
            })
            ->all();
    }

    private function seedSynonyms(): void
    {
        $rows = [
            ['washer', 'washing machine', 40],
            ['washing pump', 'washing machine drain pump', 38],
            ['fridge', 'refrigerator', 40],
            ['freezer box', 'refrigerator drawer', 30],
            ['shelf', 'drawer storage tray', 24],
            ['hoover', 'vacuum cleaner', 32],
            ['hepa', 'vacuum filter', 35],
            ['heat element', 'heating element', 28],
            ['door rubber', 'door seal gasket', 28],
            ['belt', 'drive belt dryer', 25],
            ['coffee pump', 'espresso machine pump', 26],
            ['remote', 'remote control', 30],
        ];

        foreach ($rows as [$term, $replacement, $weight]) {
            SearchSynonym::query()->updateOrCreate(
                ['term' => $term],
                ['replacement' => $replacement, 'weight' => $weight],
            );
        }
    }

    private function replaceIdentifiers(Product $product, array $identifiers): void
    {
        $product->identifiers()->delete();
        $seen = [];

        foreach ($identifiers as $type => $values) {
            foreach ($values as $value) {
                $normalized = $this->compact($value);
                $key = $type.':'.$normalized;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                ProductIdentifier::query()->create([
                    'product_id' => $product->id,
                    'type' => $type,
                    'value' => $value,
                    'normalized_value' => $normalized,
                ]);
            }
        }
    }

    private function replaceModels(Product $product, array $models): void
    {
        $product->compatibleModels()->delete();

        foreach ($models as $model) {
            ProductCompatibleModel::query()->create([
                'product_id' => $product->id,
                'model_number' => $model,
                'model_family' => Str::before($model, '-'),
                'normalized_model_number' => $this->compact($model),
            ]);
        }
    }

    private function replaceReviews(Product $product): void
    {
        $product->reviews()->delete();

        $rows = [
            [
                'author_name' => 'Daniel K.',
                'rating' => 5,
                'title' => 'Matched the model number',
                'body' => 'Found it by the OEM number, checked the model plate, and the part fitted without extra work.',
                'reviewed_on' => now()->subDays(11)->toDateString(),
            ],
            [
                'author_name' => 'M. Schneider',
                'rating' => $product->availability === 'in_stock' ? 5 : 4,
                'title' => 'Clear enough to order',
                'body' => 'The image and reference numbers were enough to compare with the old part before ordering.',
                'reviewed_on' => now()->subDays(29)->toDateString(),
            ],
            [
                'author_name' => 'Lukas M.',
                'rating' => 4,
                'title' => 'Worked as expected',
                'body' => 'Delivery note was accurate. I still recommend checking both the appliance model and spare-part number.',
                'reviewed_on' => now()->subDays(47)->toDateString(),
            ],
        ];

        foreach ($rows as $row) {
            ProductReview::query()->create([
                'product_id' => $product->id,
                ...$row,
                'verified' => true,
            ]);
        }
    }

    private function compact(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    private function products(): array
    {
        return [
            $this->product('E24-PMP-145787', 'Bosch', 'Drain pump with filter housing', 'Washing machine pump', 'washing-machine-pumps', 28.90, 35.90, 'in_stock', 18, 'Ready to ship', 1, 'pump.svg', ['00145787', '145787'], ['WAE24162', 'WAE28162', 'WAS28440'], ['230V', '2-pin connector', '32 l/min'], 'washer pump drain bosch siemens water outlet'),
            $this->product('E24-PMP-DC310054A', 'Samsung', 'Drain pump motor assembly', 'Washing machine pump', 'washing-machine-pumps', 31.40, null, 'low_stock', 4, 'Only a few left', 1, 'pump.svg', ['DC31-00054A', 'DC31-00030A'], ['WF80F5E5U4W', 'WW90T534DAW', 'WF70F5E0W4W'], ['220-240V', 'magnetic motor', 'rear outlet'], 'samsung washer pump motor dc31'),
            $this->product('E24-PMP-481010584942', 'Whirlpool', 'Askoll drain pump 30W', 'Washing machine pump', 'washing-machine-pumps', 24.80, null, 'in_stock', 32, 'Ready to ship', 1, 'pump.svg', ['481010584942', 'C00311159'], ['AWO/D 41105', 'FSCR80410', 'AWOC0714'], ['30W', 'bayonet lock', 'Askoll type'], 'whirlpool indesit washer pump'),
            $this->product('E24-PMP-1327320505', 'AEG', 'Circulation pump kit', 'Washing machine pump', 'washing-machine-pumps', 62.50, 69.90, 'in_stock', 9, 'Ready to ship', 2, 'pump.svg', ['1327320505', '1327320521'], ['L76475FL', 'L98699FL', 'EWF1487HDW'], ['heat resistant housing', 'gasket included'], 'aeg electrolux circulation pump'),

            $this->product('E24-DW-6260482', 'Miele', 'Flow-through heater with NTC sensor', 'Dishwasher heater', 'dishwasher-heaters', 72.80, 89.00, 'backorder', 0, 'Backorder available', 6, 'heater.svg', ['6260482', '6260481'], ['G1220SC', 'G1530SC', 'G2140'], ['2100W', '230V', 'NTC sensor'], 'dishwasher heating element miele flow heater'),
            $this->product('E24-DW-00651956', 'Bosch', 'Dishwasher heat pump assembly', 'Dishwasher heater', 'dishwasher-heaters', 118.30, null, 'in_stock', 7, 'Ready to ship', 2, 'heater.svg', ['00651956', '651956'], ['SMV50E30EU', 'SN45M582EU', 'SMS50E92EU'], ['heat pump', 'circulation assembly'], 'bosch siemens dishwasher heat pump'),
            $this->product('E24-DW-1527169128', 'Electrolux', 'Dishwasher heating element 1800W', 'Dishwasher heater', 'dishwasher-heaters', 44.20, null, 'low_stock', 3, 'Only a few left', 2, 'heater.svg', ['1527169128', '1527169003'], ['ESF66070XR', 'GA60LV', 'FAVORIT 55002'], ['1800W', 'tube element', 'seal included'], 'electrolux aeg dishwasher heater'),

            $this->product('E24-RF-9791284', 'Liebherr', 'Upper freezer drawer transparent', 'Refrigerator storage', 'refrigerator-storage', 46.20, null, 'in_stock', 14, 'Ready to ship', 1, 'drawer.svg', ['9791284', '979128400'], ['CN4313', 'CN4815', 'CBNef 4835'], ['upper position', 'transparent plastic', '410 mm wide'], 'fridge freezer drawer box tray'),
            $this->product('E24-RF-DA97-13440A', 'Samsung', 'Vegetable drawer front cover', 'Refrigerator storage', 'refrigerator-storage', 38.60, null, 'in_stock', 16, 'Ready to ship', 2, 'drawer.svg', ['DA97-13440A', 'DA63-07186A'], ['RB29FERNCSA', 'RB31FERNDBC', 'RL4363SBABS'], ['crisper cover', 'clear front'], 'samsung fridge shelf crisper drawer'),
            $this->product('E24-RF-2247079255', 'AEG', 'Glass shelf with white trim', 'Refrigerator storage', 'refrigerator-storage', 41.10, 49.90, 'preorder', 0, 'Preorder item', 8, 'shelf.svg', ['2247079255', '2247079206'], ['SANTO 70318', 'SKS58840S0', 'RKB63221DX'], ['safety glass', 'white trim', '470 x 295 mm'], 'aeg refrigerator glass shelf fridge tray'),

            $this->product('E24-VC-97001302', 'Dyson', 'HEPA post motor filter pack', 'Vacuum filter', 'vacuum-filters', 18.60, null, 'in_stock', 48, 'Ready to ship', 1, 'filter.svg', ['970013-02', '97001302'], ['V10', 'SV12', 'Cyclone V10 Absolute'], ['2 pack', 'washable', 'round'], 'dyson vacuum hepa filter hoover'),
            $this->product('E24-VC-9178017731', 'Miele', 'AirClean filter set', 'Vacuum filter', 'vacuum-filters', 22.90, null, 'in_stock', 25, 'Ready to ship', 1, 'filter.svg', ['9178017731', 'SF-HA50'], ['Complete C3', 'S8000', 'Blizzard CX1'], ['HEPA 13', 'sealed frame'], 'miele vacuum filter airclean'),
            $this->product('E24-VC-432200493801', 'Philips', 'Foam filter kit', 'Vacuum filter', 'vacuum-filters', 13.40, null, 'low_stock', 5, 'Only a few left', 1, 'filter.svg', ['432200493801', 'FC8073'], ['PowerPro Compact', 'FC9332', 'FC9330'], ['foam filter', 'motor protection'], 'philips vacuum filter foam'),

            $this->product('E24-OV-3570425015', 'Electrolux', 'Fan oven circular element', 'Oven heating element', 'oven-heating', 29.70, null, 'in_stock', 21, 'Ready to ship', 1, 'oven-element.svg', ['3570425015', '3570425031'], ['EOB3400', 'EOB53000', 'B5741-5'], ['2000W', 'round element', 'three turn'], 'oven heating element fan cooker'),
            $this->product('E24-OV-00470921', 'Bosch', 'Top grill heating element', 'Oven heating element', 'oven-heating', 36.90, null, 'in_stock', 11, 'Ready to ship', 2, 'oven-element.svg', ['00470921', '470921'], ['HBN331E0', 'HBA23B150', 'HEA23B150'], ['2800W', 'upper grill', '230V'], 'bosch oven grill heater element'),
            $this->product('E24-OV-C00199665', 'Indesit', 'Lower oven heating element', 'Oven heating element', 'oven-heating', 24.50, null, 'backorder', 0, 'Backorder available', 5, 'oven-element.svg', ['C00199665', '199665'], ['FIM33KAIX', 'FIE36KB', 'IFW6330IX'], ['1150W', 'bottom heat'], 'indesit hotpoint lower oven element'),

            $this->product('E24-DR-1258288107', 'AEG', 'Tumble dryer drive belt', 'Dryer belt', 'dryer-belts', 16.90, null, 'in_stock', 37, 'Ready to ship', 1, 'belt.svg', ['1258288107', '1258288008'], ['T56840L', 'T59840', 'LAVATHERM 59800'], ['1975 H7', 'elastic belt'], 'dryer belt drive aeg electrolux'),
            $this->product('E24-DR-6602001655', 'Beko', 'Poly-V dryer belt', 'Dryer belt', 'dryer-belts', 14.80, null, 'in_stock', 24, 'Ready to ship', 1, 'belt.svg', ['6602001655', '2953240100'], ['DCU7230', 'DPU8360', 'DS7333'], ['7 rib', '2005 mm'], 'beko dryer belt tumble'),
            $this->product('E24-DR-481235818164', 'Whirlpool', 'Tumble dryer belt 1965H7', 'Dryer belt', 'dryer-belts', 17.40, null, 'low_stock', 2, 'Only a few left', 2, 'belt.svg', ['481235818164', 'C00300793'], ['AWZ 9813', 'HSCX80420', 'AZB7570'], ['1965 H7', 'black rubber'], 'whirlpool dryer belt'),

            $this->product('E24-SEAL-00772658', 'Bosch', 'Washing machine door seal', 'Door seal', 'door-seals', 54.90, null, 'in_stock', 13, 'Ready to ship', 2, 'seal.svg', ['00772658', '772658'], ['WAN28281GB', 'WAT28371GB', 'WAE24462'], ['front gasket', 'with drain hole'], 'door rubber seal gasket washer'),
            $this->product('E24-SEAL-4986ER1004A', 'LG', 'Door boot gasket', 'Door seal', 'door-seals', 49.30, 59.00, 'in_stock', 8, 'Ready to ship', 2, 'seal.svg', ['4986ER1004A', 'MDS38265303'], ['F4J6', 'F12U2QDN0', 'FH4G1BCS2'], ['front load', 'grey rubber'], 'lg washer door seal rubber'),
            $this->product('E24-SEAL-754131', 'Miele', 'Dishwasher lower door seal', 'Door seal', 'door-seals', 21.70, null, 'in_stock', 19, 'Ready to ship', 1, 'seal.svg', ['754131', '07541310'], ['G600', 'G800', 'G1022'], ['lower seal', 'black rubber'], 'miele dishwasher door gasket'),

            $this->product('E24-CF-7313219431', 'DeLonghi', 'Ulka pump for espresso machine', 'Coffee machine pump', 'coffee-machine-parts', 19.90, null, 'in_stock', 29, 'Ready to ship', 1, 'coffee-pump.svg', ['7313219431', 'EP5GW'], ['ECAM22.110', 'ESAM4200', 'EC685'], ['48W', '15 bar', '230V'], 'coffee pump espresso delonghi ulka'),
            $this->product('E24-CF-996530007753', 'Philips', 'Brew group service kit', 'Coffee machine brew unit', 'coffee-machine-parts', 42.60, null, 'in_stock', 10, 'Ready to ship', 2, 'brew-unit.svg', ['996530007753', '421944093711'], ['EP2220', 'EP3246', 'HD8827'], ['seal kit', 'lubricant included'], 'philips saeco brew group service'),
            $this->product('E24-CF-7313235361', 'DeLonghi', 'Water tank with lid', 'Coffee machine tank', 'coffee-machine-parts', 28.40, null, 'preorder', 0, 'Preorder item', 7, 'drawer.svg', ['7313235361', 'AS00005448'], ['ECAM350.55', 'ECAM370.95', 'Dinamica Plus'], ['1.8L', 'transparent'], 'delonghi coffee water tank'),

            $this->product('E24-RC-BN5901315B', 'Samsung', 'Smart TV remote control', 'Remote control', 'remote-controls', 24.90, null, 'in_stock', 41, 'Ready to ship', 1, 'remote.svg', ['BN59-01315B', 'BN5901315B'], ['UE55TU8070', 'QE65Q60T', 'UE43TU7100'], ['Bluetooth', 'voice key'], 'samsung tv remote control'),
            $this->product('E24-RC-RMT-TX300E', 'Sony', 'Bravia remote control', 'Remote control', 'remote-controls', 19.80, null, 'in_stock', 23, 'Ready to ship', 1, 'remote.svg', ['RMT-TX300E', '149331811'], ['KD-55XE9005', 'KD-49XE8005', 'KD-65XF9005'], ['IR remote', 'Netflix key'], 'sony bravia tv remote'),
            $this->product('E24-RC-AKB75095308', 'LG', 'Magic remote compatible handset', 'Remote control', 'remote-controls', 27.60, null, 'low_stock', 4, 'Only a few left', 1, 'remote.svg', ['AKB75095308', 'AN-MR18BA'], ['OLED55C8', '49SK8500', '65UK7550'], ['pointer control', 'Bluetooth'], 'lg magic remote tv'),

            $this->product('E24-CBL-Q509827', 'Generic', 'HDMI cable high speed 2m', 'Cable', 'cables-connectors', 5.60, null, 'in_stock', 120, 'Ready to ship', 1, 'cable.svg', ['Q509827', '4054905509827'], ['HDMI-2M', 'AV-HDMI-HS'], ['2m', 'HDMI 2.0', 'black'], 'hdmi cable high speed television'),
            $this->product('E24-CBL-00605740', 'Bosch', 'Appliance mains cable', 'Cable', 'cables-connectors', 12.70, null, 'in_stock', 33, 'Ready to ship', 1, 'cable.svg', ['00605740', '605740'], ['SMS46MI08E', 'WAN28281GB', 'WTW87560GB'], ['EU plug', '1.5m', '3 core'], 'bosch power cable connector'),
            $this->product('E24-CBL-C00194370', 'Indesit', 'Oven terminal block connector', 'Cable', 'cables-connectors', 9.40, null, 'backorder', 0, 'Backorder available', 5, 'cable.svg', ['C00194370', '194370'], ['FIM33KABK', 'IFW6330WH', 'K3C51'], ['3 way terminal', 'high temperature'], 'oven connector cable block'),

            $this->product('E24-SEN-00611323', 'Bosch', 'Oven temperature sensor', 'Thermostat and sensor', 'thermostats-sensors', 31.20, null, 'in_stock', 18, 'Ready to ship', 1, 'sensor.svg', ['00611323', '611323'], ['HBA13B150', 'HBN331E0', 'HBA43B260'], ['PT500 probe', '600 mm lead'], 'temperature sensor oven thermostat'),
            $this->product('E24-SEN-140000401012', 'AEG', 'Fridge thermostat sensor', 'Thermostat and sensor', 'thermostats-sensors', 26.80, null, 'low_stock', 5, 'Only a few left', 2, 'sensor.svg', ['140000401012', '2262199249'], ['SANTO 72398', 'SKB58221AF', 'RKB638E2MX'], ['NTC probe', 'clip mount'], 'fridge thermostat sensor ntc'),
            $this->product('E24-SEN-481228228335', 'Whirlpool', 'Dryer humidity sensor brush', 'Thermostat and sensor', 'thermostats-sensors', 17.90, null, 'in_stock', 12, 'Ready to ship', 2, 'sensor.svg', ['481228228335', 'C00313047'], ['AWZ 8676', 'AZB7570', 'HSCX80420'], ['carbon brush', 'sensor strip'], 'dryer humidity sensor'),

            $this->product('E24-LCK-00638259', 'Bosch', 'Washing machine door lock', 'Door lock and switch', 'door-locks-switches', 34.30, null, 'in_stock', 20, 'Ready to ship', 1, 'lock.svg', ['00638259', '638259'], ['WAE24462', 'WAN28081GB', 'WAT28371GB'], ['3 pin', 'interlock'], 'washer door lock switch'),
            $this->product('E24-LCK-C00254755', 'Hotpoint', 'Dishwasher door latch', 'Door lock and switch', 'door-locks-switches', 18.20, null, 'in_stock', 15, 'Ready to ship', 1, 'lock.svg', ['C00254755', '254755'], ['LST216A', 'LFT114', 'FDUD43133'], ['latch hook', 'spring included'], 'dishwasher door latch lock'),
            $this->product('E24-LCK-1370069005', 'Electrolux', 'Dryer microswitch assembly', 'Door lock and switch', 'door-locks-switches', 22.40, null, 'preorder', 0, 'Preorder item', 6, 'lock.svg', ['1370069005', '1254253204'], ['EDH3498RDE', 'T59840', 'EDC2086PDW'], ['microswitch', 'two wire'], 'dryer door switch lock'),
        ];
    }

    private function product(
        string $sku,
        string $brand,
        string $name,
        string $family,
        string $category,
        float $price,
        ?float $comparePrice,
        string $availability,
        int $stock,
        string $deliveryText,
        int $deliveryDays,
        string $image,
        array $oem,
        array $models,
        array $specs,
        string $keywords,
    ): array {
        return [
            'sku' => $sku,
            'brand' => $brand,
            'name' => $name,
            'family' => $family,
            'category' => $category,
            'description' => $name.' for compatible '.$brand.' appliance models. Match by OEM number or model before ordering.',
            'price' => $price,
            'compare_price' => $comparePrice,
            'availability' => $availability,
            'stock' => $stock,
            'delivery_text' => $deliveryText,
            'delivery_days' => $deliveryDays,
            'rating' => $this->rating($sku),
            'review_count' => $this->reviewCount($sku),
            'image_path' => 'catalog-images/'.$image,
            'keywords' => $keywords,
            'specs' => [
                'Main spec' => $specs[0] ?? null,
                'Fitment' => $specs[1] ?? null,
                'Note' => $specs[2] ?? 'Check model plate before ordering',
            ],
            'identifiers' => [
                'oem' => $oem,
                'article' => [$sku],
                'ean' => [$this->ean($sku)],
            ],
            'models' => $models,
        ];
    }

    private function ean(string $seed): string
    {
        return '40'.substr(str_pad((string) abs(crc32($seed)), 11, '0', STR_PAD_LEFT), 0, 11);
    }

    private function rating(string $seed): float
    {
        return 4.3 + ((abs(crc32($seed)) % 7) / 10);
    }

    private function reviewCount(string $seed): int
    {
        return 12 + (abs(crc32($seed)) % 130);
    }
}
