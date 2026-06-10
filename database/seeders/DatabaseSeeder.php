<?php

namespace Database\Seeders;

use Database\Seeders\Catalog\CatalogDemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogDemoSeeder::class);
    }
}
