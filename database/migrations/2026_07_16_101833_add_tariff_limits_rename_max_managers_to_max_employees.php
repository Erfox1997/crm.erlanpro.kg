<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tariffs', 'max_managers') && ! Schema::hasColumn('tariffs', 'max_employees')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->renameColumn('max_managers', 'max_employees');
            });
        }

        if (! Schema::hasColumn('tariffs', 'message_retention_days')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->unsignedInteger('message_retention_days')->nullable()->after('max_employees');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tariffs', 'message_retention_days')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->dropColumn('message_retention_days');
            });
        }

        if (Schema::hasColumn('tariffs', 'max_employees') && ! Schema::hasColumn('tariffs', 'max_managers')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->renameColumn('max_employees', 'max_managers');
            });
        }
    }
};
