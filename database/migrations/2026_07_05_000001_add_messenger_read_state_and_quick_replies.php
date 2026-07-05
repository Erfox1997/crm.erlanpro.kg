<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable()->after('last_message_at');
        });

        Schema::create('messenger_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_quick_replies');

        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->dropColumn('last_read_at');
        });
    }
};
