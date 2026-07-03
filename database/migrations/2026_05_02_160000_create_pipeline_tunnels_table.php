<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_tunnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('from_pipeline_id')->constrained('pipelines')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('to_pipeline_id')->constrained('pipelines')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'from_pipeline_id', 'to_pipeline_id'],
                'pl_tunnels_cmp_from_to_uq'
            );
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_tunnels');
    }
};
