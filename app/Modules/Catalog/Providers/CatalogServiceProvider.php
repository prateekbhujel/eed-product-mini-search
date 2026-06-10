<?php

namespace App\Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(app_path('Modules/Catalog/Routes/web.php'));
    }
}
