<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage_id']);
            $table->index('pipeline_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
