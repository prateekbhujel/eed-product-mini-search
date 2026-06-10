<?php

use App\Modules\Catalog\Http\Controllers\CatalogPageController;
use App\Modules\Catalog\Http\Controllers\ProductSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', CatalogPageController::class)->name('catalog.index');

Route::prefix('api/catalog')->name('catalog.')->group(function (): void {
    Route::get('/search', [ProductSearchController::class, 'search'])->name('search');
    Route::get('/products/{product:slug}', [ProductSearchController::class, 'show'])->name('products.show');
});
