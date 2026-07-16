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
                'max_employees' => 10,
                'message_retention_days' => 90,
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
                'max_employees' => 25,
                'message_retention_days' => 180,
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
                'max_employees' => 50,
                'message_retention_days' => 365,
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
                'max_employees' => null,
                'message_retention_days' => null,
            ],
        );
    }
}
