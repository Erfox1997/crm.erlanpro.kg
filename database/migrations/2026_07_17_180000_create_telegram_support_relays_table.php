<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_support_relays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_chat_id');
            $table->unsignedBigInteger('owner_message_id');
            $table->unsignedBigInteger('client_chat_id');
            $table->unsignedBigInteger('client_message_id')->nullable();
            $table->string('client_username')->nullable();
            $table->string('client_name')->nullable();
            $table->timestamps();

            $table->unique(['owner_chat_id', 'owner_message_id']);
            $table->index('client_chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_support_relays');
    }
};
