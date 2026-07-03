<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('external_id')->nullable();
            $table->string('participant_id');
            $table->string('participant_name')->nullable();
            $table->string('participant_username')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'channel', 'participant_id']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('messenger_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('external_id')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 16)->default('received');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['messenger_conversation_id', 'external_id']);
            $table->index(['messenger_conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_messages');
        Schema::dropIfExists('messenger_conversations');
    }
};
