<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->boolean('show_in_messenger')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('client_field_definitions', function (Blueprint $table) {
            $table->dropColumn('show_in_messenger');
        });
    }
};
