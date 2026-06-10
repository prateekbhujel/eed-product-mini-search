<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ExternalCatalog\DummyJsonProductGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalProductSearchController extends Controller
{
    public function __invoke(Request $request, DummyJsonProductGateway $gateway): JsonResponse
    {
        return response()->json([
            'products' => $gateway->search((string) $request->query('q', ''), 8),
            'source' => 'dummyjson',
        ]);
    }
}
