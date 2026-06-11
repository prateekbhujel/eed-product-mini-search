<?php

use App\Modules\Catalog\Http\Controllers\CatalogPageController;
use App\Modules\Catalog\Http\Controllers\CatalogSitemapController;
use App\Modules\Catalog\Http\Controllers\ExternalProductSearchController;
use App\Modules\Catalog\Http\Controllers\ProductSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', CatalogPageController::class)->name('catalog.index');
Route::get('/sitemap.xml', CatalogSitemapController::class)->name('catalog.sitemap');
Route::get('/products/{product:slug}', CatalogPageController::class)->name('catalog.products.page');

Route::prefix('api/catalog')->name('catalog.')->middleware('throttle:catalog-search')->group(function (): void {
    Route::get('/search', [ProductSearchController::class, 'search'])->name('search');
    Route::get('/products/{slug}', [ProductSearchController::class, 'show'])->name('products.show');
    Route::get('/external-search', ExternalProductSearchController::class)->name('external-search');
});
