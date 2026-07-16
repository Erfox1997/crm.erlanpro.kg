<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_username', 64)->nullable()->after('position_id');
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('telegram_username');
            $table->index('telegram_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['telegram_username']);
            $table->dropUnique(['telegram_id']);
            $table->dropColumn(['telegram_username', 'telegram_id']);
        });
    }
};
