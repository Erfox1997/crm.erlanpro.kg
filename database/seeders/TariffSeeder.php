<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'free', 'name' => 'Бесплатный', 'sort_order' => 1, 'max_managers' => 2, 'max_deals' => 100],
            ['slug' => 'standard', 'name' => 'Стандарт', 'sort_order' => 2, 'max_managers' => 10, 'max_deals' => 5000],
            ['slug' => 'premium', 'name' => 'Премиум', 'sort_order' => 3, 'max_managers' => null, 'max_deals' => null],
        ];

        foreach ($rows as $row) {
            Tariff::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row
            );
        }
    }
}
