<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->after('name');
            $table->decimal('original_price', 12, 2)->nullable()->after('price');
            $table->unsignedSmallInteger('duration_days')->default(30)->after('original_price');
            $table->boolean('is_free')->default(false)->after('duration_days');
            $table->boolean('is_active')->default(true)->after('is_free');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('subscription_ends_at')->nullable()->after('tariff_id');
            $table->boolean('is_active')->default(true)->after('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subscription_ends_at', 'is_active']);
        });

        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'original_price',
                'duration_days',
                'is_free',
                'is_active',
            ]);
        });
    }
};
