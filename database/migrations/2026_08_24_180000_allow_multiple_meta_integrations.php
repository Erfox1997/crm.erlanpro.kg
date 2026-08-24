<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_integrations', 'external_account_id')) {
            Schema::table('company_integrations', function (Blueprint $table) {
                $table->string('external_account_id', 64)->nullable()->after('provider');
            });
        }

        $rows = DB::table('company_integrations')->orderBy('id')->get();
        foreach ($rows as $row) {
            $metadata = json_decode((string) ($row->metadata ?? ''), true);
            if (! is_array($metadata)) {
                $metadata = [];
            }

            $externalId = match ($row->provider) {
                'facebook' => (string) ($metadata['page_id'] ?? ''),
                'instagram' => (string) ($metadata['instagram_user_id'] ?? $metadata['page_id'] ?? ''),
                default => '',
            };

            DB::table('company_integrations')
                ->where('id', $row->id)
                ->update(['external_account_id' => $externalId]);
        }

        $indexes = collect(DB::select('SHOW INDEX FROM company_integrations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (in_array('company_integrations_company_id_provider_unique', $indexes, true)) {
            Schema::table('company_integrations', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'provider']);
            });
        }

        if (! in_array('ci_company_provider_external_uq', $indexes, true)) {
            Schema::table('company_integrations', function (Blueprint $table) {
                $table->unique(
                    ['company_id', 'provider', 'external_account_id'],
                    'ci_company_provider_external_uq',
                );
            });
        }

        if (! in_array('ci_provider_external_idx', $indexes, true)) {
            Schema::table('company_integrations', function (Blueprint $table) {
                $table->index(['provider', 'external_account_id'], 'ci_provider_external_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('company_integrations', function (Blueprint $table) {
            $table->dropUnique('ci_company_provider_external_uq');
            $table->dropIndex('ci_provider_external_idx');
            $table->unique(['company_id', 'provider']);
            $table->dropColumn('external_account_id');
        });
    }
};
