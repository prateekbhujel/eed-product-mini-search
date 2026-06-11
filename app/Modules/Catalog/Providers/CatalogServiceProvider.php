<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Services\ExternalCatalog\EedProductGateway;
use App\Modules\Catalog\Services\ExternalCatalog\SupplierProductGateway;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SupplierProductGateway::class, EedProductGateway::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(app_path('Modules/Catalog/Routes/web.php'));
    }
}
