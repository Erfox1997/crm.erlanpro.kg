<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tariffs', 'max_deals')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->dropColumn('max_deals');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tariffs', 'max_deals')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->unsignedInteger('max_deals')->nullable()->after('message_retention_days');
            });
        }
    }
};
