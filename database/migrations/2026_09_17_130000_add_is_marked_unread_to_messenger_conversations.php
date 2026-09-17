<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->boolean('is_marked_unread')->default(false)->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->dropColumn('is_marked_unread');
        });
    }
};
