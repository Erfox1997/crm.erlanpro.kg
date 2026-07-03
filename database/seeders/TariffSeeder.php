<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        Tariff::free();

        Tariff::query()->updateOrCreate(
            ['slug' => 'standard'],
            ['name' => 'Стандарт', 'sort_order' => 2, 'max_managers' => 10, 'max_deals' => 5000],
        );

        Tariff::query()->updateOrCreate(
            ['slug' => 'premium'],
            ['name' => 'Премиум', 'sort_order' => 3, 'max_managers' => null, 'max_deals' => null],
        );
    }
}
