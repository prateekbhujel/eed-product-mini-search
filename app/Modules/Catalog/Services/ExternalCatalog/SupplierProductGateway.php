<?php

namespace App\Modules\Catalog\Services\ExternalCatalog;

interface SupplierProductGateway
{
    public function search(string $query, int $page = 1, int $perPage = 8, ?string $visitorIp = null): array;
}
