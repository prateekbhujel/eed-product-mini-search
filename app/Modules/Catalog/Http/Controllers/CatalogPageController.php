<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CatalogPageController extends Controller
{
    public function __invoke(): View
    {
        return view('catalog.app');
    }
}
