<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messenger_conversations', 'company_integration_id')) {
            Schema::table('messenger_conversations', function (Blueprint $table) {
                $table->foreignId('company_integration_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('company_integrations')
                    ->nullOnDelete();
            });
        }

        $conversations = DB::table('messenger_conversations')
            ->whereNull('company_integration_id')
            ->orderBy('id')
            ->get(['id', 'company_id', 'channel']);

        foreach ($conversations as $conversation) {
            $integrationId = DB::table('company_integrations')
                ->where('company_id', $conversation->company_id)
                ->where('provider', $conversation->channel)
                ->orderBy('id')
                ->value('id');

            if ($integrationId) {
                DB::table('messenger_conversations')
                    ->where('id', $conversation->id)
                    ->update(['company_integration_id' => $integrationId]);
            }
        }

        $indexes = collect(DB::select('SHOW INDEX FROM messenger_conversations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (in_array('messenger_conversations_company_id_channel_participant_id_unique', $indexes, true)) {
            Schema::table('messenger_conversations', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'channel', 'participant_id']);
            });
        }

        if (! in_array('mc_company_channel_integration_participant_uq', $indexes, true)) {
            Schema::table('messenger_conversations', function (Blueprint $table) {
                $table->unique(
                    ['company_id', 'channel', 'company_integration_id', 'participant_id'],
                    'mc_company_channel_integration_participant_uq',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('messenger_conversations', function (Blueprint $table) {
            $table->dropUnique('mc_company_channel_integration_participant_uq');
            $table->unique(['company_id', 'channel', 'participant_id']);
            $table->dropConstrainedForeignId('company_integration_id');
        });
    }
};
