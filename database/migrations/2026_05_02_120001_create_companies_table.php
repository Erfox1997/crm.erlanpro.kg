<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('tariff_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('tariff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
