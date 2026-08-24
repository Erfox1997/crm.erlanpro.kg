<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_support_messages', function (Blueprint $table) {
            $table->string('media_type', 16)->nullable()->after('body');
            $table->string('media_path')->nullable()->after('media_type');
            $table->string('media_mime', 64)->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_support_messages', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_path', 'media_mime']);
        });
    }
};
