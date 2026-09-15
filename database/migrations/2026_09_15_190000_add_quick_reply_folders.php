<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_quick_reply_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'sort_order']);
        });

        Schema::table('messenger_quick_replies', function (Blueprint $table) {
            $table->foreignId('folder_id')
                ->nullable()
                ->after('company_id')
                ->constrained('messenger_quick_reply_folders')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['company_id', 'folder_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('messenger_quick_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('messenger_quick_reply_folders');
    }
};
