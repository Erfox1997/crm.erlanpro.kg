<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_support_projects')) {
            Schema::create('telegram_support_projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('telegram_support_project_client')) {
            Schema::create('telegram_support_project_client', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('telegram_support_project_id');
                $table->unsignedBigInteger('telegram_support_client_id');
                $table->timestamps();

                $table->foreign('telegram_support_project_id', 'tspc_project_fk')
                    ->references('id')
                    ->on('telegram_support_projects')
                    ->cascadeOnDelete();
                $table->foreign('telegram_support_client_id', 'tspc_client_fk')
                    ->references('id')
                    ->on('telegram_support_clients')
                    ->cascadeOnDelete();
                $table->unique(
                    ['telegram_support_project_id', 'telegram_support_client_id'],
                    'tspc_project_client_uq',
                );
            });
        } else {
            $indexes = collect(DB::select('SHOW INDEX FROM telegram_support_project_client'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (! in_array('tspc_project_fk', $indexes, true)) {
                Schema::table('telegram_support_project_client', function (Blueprint $table) {
                    $table->foreign('telegram_support_project_id', 'tspc_project_fk')
                        ->references('id')
                        ->on('telegram_support_projects')
                        ->cascadeOnDelete();
                });
            }

            if (! in_array('tspc_client_fk', $indexes, true)) {
                Schema::table('telegram_support_project_client', function (Blueprint $table) {
                    $table->foreign('telegram_support_client_id', 'tspc_client_fk')
                        ->references('id')
                        ->on('telegram_support_clients')
                        ->cascadeOnDelete();
                });
            }

            if (! in_array('tspc_project_client_uq', $indexes, true)) {
                Schema::table('telegram_support_project_client', function (Blueprint $table) {
                    $table->unique(
                        ['telegram_support_project_id', 'telegram_support_client_id'],
                        'tspc_project_client_uq',
                    );
                });
            }
        }

        if (! Schema::hasColumn('telegram_support_clients', 'blocked_at')) {
            Schema::table('telegram_support_clients', function (Blueprint $table) {
                $table->timestamp('blocked_at')->nullable()->after('reviewed_at');
                $table->index('blocked_at');
            });
        }

        if (! Schema::hasTable('telegram_support_messages')) {
            Schema::create('telegram_support_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('telegram_support_client_id');
                $table->unsignedBigInteger('telegram_support_project_id');
                $table->text('body');
                $table->string('status', 16)->default('open')->index();
                $table->unsignedBigInteger('client_telegram_message_id')->nullable();
                $table->timestamp('done_at')->nullable();
                $table->timestamps();

                $table->foreign('telegram_support_client_id', 'tsm_client_fk')
                    ->references('id')
                    ->on('telegram_support_clients')
                    ->cascadeOnDelete();
                $table->foreign('telegram_support_project_id', 'tsm_project_fk')
                    ->references('id')
                    ->on('telegram_support_projects')
                    ->cascadeOnDelete();
                $table->index(['telegram_support_project_id', 'status'], 'tsm_project_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_support_messages');

        if (Schema::hasColumn('telegram_support_clients', 'blocked_at')) {
            Schema::table('telegram_support_clients', function (Blueprint $table) {
                $table->dropIndex(['blocked_at']);
                $table->dropColumn('blocked_at');
            });
        }

        Schema::dropIfExists('telegram_support_project_client');
        Schema::dropIfExists('telegram_support_projects');
    }
};
