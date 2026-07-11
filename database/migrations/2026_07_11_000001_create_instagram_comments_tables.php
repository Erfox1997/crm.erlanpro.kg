<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->text('caption')->nullable();
            $table->string('media_type')->nullable();
            $table->text('media_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('permalink')->nullable();
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_comment_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_id']);
            $table->index(['company_id', 'last_comment_at']);
        });

        Schema::create('instagram_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instagram_media_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->foreignId('parent_id')->nullable()->constrained('instagram_comments')->nullOnDelete();
            $table->string('author_id')->nullable();
            $table->string('author_username')->nullable();
            $table->string('author_name')->nullable();
            $table->text('body');
            $table->string('direction', 16)->default('inbound');
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_id']);
            $table->index(['instagram_media_id', 'parent_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_comments');
        Schema::dropIfExists('instagram_media');
    }
};
