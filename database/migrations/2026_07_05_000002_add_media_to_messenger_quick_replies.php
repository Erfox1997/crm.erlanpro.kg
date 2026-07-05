<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messenger_quick_replies', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->after('title');
            $table->text('body')->nullable()->change();
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_path');
            $table->string('attachment_name', 255)->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        Schema::table('messenger_quick_replies', function (Blueprint $table) {
            $table->dropColumn(['type', 'attachment_path', 'attachment_mime', 'attachment_name']);
            $table->text('body')->nullable(false)->change();
        });
    }
};
