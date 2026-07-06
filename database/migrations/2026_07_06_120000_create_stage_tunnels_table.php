<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_tunnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->constrained('stages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('stages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'from_stage_id'], 'stage_tunnels_cmp_from_uq');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_tunnels');
    }
};
