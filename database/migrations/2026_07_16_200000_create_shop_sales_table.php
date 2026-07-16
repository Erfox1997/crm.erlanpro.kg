<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('messenger_conversations')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('shop_document_id');
            $table->string('shop_document_number')->nullable();
            $table->string('status', 32)->default('sold');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'user_id', 'created_at']);
            $table->unique(['company_id', 'shop_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sales');
    }
};
