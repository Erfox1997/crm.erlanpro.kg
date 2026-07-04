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
            [
                'name' => 'Месячный',
                'description' => 'Стандартный тариф на 30 дней',
                'price' => 2400,
                'original_price' => null,
                'duration_days' => 30,
                'is_free' => false,
                'is_active' => true,
                'sort_order' => 2,
                'max_managers' => 10,
                'max_deals' => 5000,
            ],
        );

        Tariff::query()->updateOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Квартальный',
                'description' => null,
                'price' => 6600,
                'original_price' => 7200,
                'duration_days' => 90,
                'is_free' => false,
                'is_active' => true,
                'sort_order' => 3,
                'max_managers' => null,
                'max_deals' => null,
            ],
        );

        Tariff::query()->updateOrCreate(
            ['slug' => 'yearly'],
            [
                'name' => 'Годовой',
                'description' => null,
                'price' => 21000,
                'original_price' => 28800,
                'duration_days' => 365,
                'is_free' => false,
                'is_active' => true,
                'sort_order' => 4,
                'max_managers' => null,
                'max_deals' => null,
            ],
        );

        Tariff::query()->updateOrCreate(
            ['slug' => 'yearly-vip'],
            [
                'name' => 'Годовой VIP',
                'description' => 'Для крупных магазинов',
                'price' => 30000,
                'original_price' => 36000,
                'duration_days' => 365,
                'is_free' => false,
                'is_active' => true,
                'sort_order' => 5,
                'max_managers' => null,
                'max_deals' => null,
            ],
        );
    }
}
