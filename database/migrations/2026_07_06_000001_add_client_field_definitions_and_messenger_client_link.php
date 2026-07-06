<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->string('type', 32)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'sort_order']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('notes');
        });

        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('participant_username')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::dropIfExists('client_field_definitions');
    }
};
