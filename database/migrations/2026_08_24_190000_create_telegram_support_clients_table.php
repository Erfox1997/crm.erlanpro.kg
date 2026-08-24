<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_support_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->unsignedBigInteger('client_chat_id')->index();
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('company_name')->nullable();
            $table->text('message');
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_support_clients');
    }
};
