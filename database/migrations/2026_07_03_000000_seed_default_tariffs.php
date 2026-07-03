<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            ['slug' => 'free', 'name' => 'Бесплатный', 'sort_order' => 1, 'max_managers' => 2, 'max_deals' => 100],
            ['slug' => 'standard', 'name' => 'Стандарт', 'sort_order' => 2, 'max_managers' => 10, 'max_deals' => 5000],
            ['slug' => 'premium', 'name' => 'Премиум', 'sort_order' => 3, 'max_managers' => null, 'max_deals' => null],
        ];

        foreach ($rows as $row) {
            DB::table('tariffs')->updateOrInsert(
                ['slug' => $row['slug']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        DB::table('tariffs')->whereIn('slug', ['free', 'standard', 'premium'])->delete();
    }
};
