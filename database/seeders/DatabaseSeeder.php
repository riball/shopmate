<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** php artisan migrate --seed  →  a working site with the fictional demo catalog. */
    public function run(): void
    {
        $this->call([
            PageSeeder::class,
            DemoCatalogSeeder::class,
        ]);
    }
}
