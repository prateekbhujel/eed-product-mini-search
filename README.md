# E24 Product Mini Search

Laravel + React spare-parts catalog slice for a supplier-backed appliance parts shop.

The app focuses on the parts of the platform that matter early: catalog modeling, searchable references, category filters, product detail pages, basket flow, reviews, seeded demo data, and a replaceable external supplier/API adapter.

## Current Scope

- Laravel 12 modular monolith structure with Catalog and Search modules.
- React storefront with Amazon-style search header, category drawer, filters, compact product cards, basket drawer, product detail pages, related products, and reviews.
- DB-backed product catalog with categories, brands, OEM numbers, EAN/article numbers, compatible appliance models, specs, prices, stock state, ratings, reviews, and local product images.
- Search ranking for SKU, OEM/reference numbers, model numbers, brand, family, category, specs, and common wording.
- Paginated search API with frontend debounce, request cancellation, load-more/infinite-scroll continuation, and API rate limiting.
- Synonyms for searches such as `washer`, `fridge`, `hoover`, `door rubber`, `coffee pump`, and `shelf`.
- Hot query caching through Laravel's database cache store using a separate SQLite connection when Redis is not available on shared hosting.
- External product gateway example using a DTO boundary, so supplier responses can be normalized before entering the catalog layer.

## Architecture

```text
app/
  Modules/
    Catalog/
      Data/
      Http/Controllers/
      Models/
      Providers/
      Repositories/
      Routes/
      Services/
    Search/
      Models/
      Services/

resources/js/modules/catalog/
  CatalogApp.jsx
  api.js
  components/

database/seeders/Catalog/
  CatalogDemoSeeder.php
```

The demo data is seeded so the app can be reviewed immediately. With real ASWO/EED access, supplier API responses would be handled by a gateway, mapped through DTOs, normalized into catalog tables, then pushed into Elasticsearch/OpenSearch when the catalog grows beyond what the database search layer should handle.

The public external source currently uses DummyJSON because it provides free product search with pagination and no login. It is not a spare-parts supplier. It exists here to show the adapter/cache shape while the real ASWO/EED credentials or reachable test gateway are unavailable.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite database/cache.sqlite
php artisan migrate:fresh --seed
php artisan migrate --database=cache_sqlite --path=database/migrations/0001_01_01_000001_create_cache_table.php --force
npm run build
php artisan serve --host=127.0.0.1 --port=4180
```

Open:

```text
http://127.0.0.1:4180
```

## Useful API Checks

```text
GET /api/catalog/search
GET /api/catalog/search?per_page=12&page=2
GET /api/catalog/search?q=DC31-00054A
GET /api/catalog/search?q=fridge%20shelf
GET /api/catalog/search?q=pump&brand=Bosch
GET /api/catalog/products/{slug}
GET /api/catalog/external-search?q=phone&per_page=4
```

## Notes

SQLite is used for fast review and shared-hosting compatibility. Production catalog storage should be MySQL/MariaDB or PostgreSQL, with Redis or a managed cache when available.

Elasticsearch/OpenSearch is intentionally not required for this mini demo because shared hosting usually cannot run it. The current paginated search service and repository give one replaceable boundary for moving indexing/search into a dedicated engine later.

## Verification

```bash
php artisan test
npm run build
```

Coverage checks homepage rendering, exact OEM search, common wording search, pagination, filtered results, product detail/reviews, cached external adapter normalization, and build output.
