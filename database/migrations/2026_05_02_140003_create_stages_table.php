<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('color', 32)->nullable();
            /** @var string|null 'won'|'lost' when deal reaches this stage */
            $table->string('outcome', 16)->nullable();
            $table->timestamps();

            $table->index(['pipeline_id', 'sort_order']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
