# E24 Product Mini Search

Laravel + React spare-parts search slice for a supplier-backed appliance parts shop.

This is a small production-style slice, not a homepage mockup. The goal is to show how the catalog layer can work: products live in the database, users can search by brand, model number, OEM reference, part family, and common wording, and repeat searches are cached without requiring Redis.

## What This Shows

- Laravel 12 modular monolith structure inspired by ERP-style app organization.
- React storefront search screen with filters, suggestions, product cards, and detail panel.
- DB-backed product catalog with categories, brands, OEM numbers, EAN/article numbers, compatible models, specs, stock state, and local images.
- Search ranking that prioritizes exact SKU, OEM, and model matches before loose wording matches.
- Synonym handling for natural wording like `washer`, `fridge`, `shelf`, `hoover`, `door rubber`, and `coffee pump`.
- Database cache store using a separate SQLite connection, useful when Redis is not available on shared hosting.

## Architecture

```text
app/
  Modules/
    Catalog/
      Http/Controllers
      Models
      Providers
      Repositories
      Routes
      Services
    Search/
      Models
      Services

resources/js/modules/catalog/
  CatalogApp.jsx
  api.js
  components/

database/seeders/Catalog/
  CatalogDemoSeeder.php
```

The current demo uses seeded catalog data so the app can be reviewed immediately. In a real supplier integration, the same module boundary would accept supplier feeds/API responses, normalize them into the catalog tables, then later push search documents into Elasticsearch/OpenSearch for the 20M-product scale.

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
GET /api/catalog/search?q=DC31-00054A
GET /api/catalog/search?q=fridge%20shelf
GET /api/catalog/search?q=pump&brand=Bosch
GET /api/catalog/products/{slug}
```

## Database Notes

For the demo, SQLite keeps setup fast. For production, switch the main catalog connection to MySQL/MariaDB or PostgreSQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=e24_catalog
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=database
DB_CACHE_CONNECTION=cache_sqlite
DB_CACHE_DATABASE=/absolute/path/to/database/cache.sqlite
```

This keeps the main catalog on MySQL while cache remains lightweight on SQLite when Redis is unavailable.

## Verification

```bash
php artisan test
npm run build
```

Current coverage checks homepage rendering, exact OEM search, common wording search, and filtered ranked results.
