<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Pipeline;
use App\Models\Stage;

class CreateDefaultPipelineForCompany
{
    public static function ensure(Company $company): void
    {
        if (Pipeline::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        self::run($company);
    }

    public static function run(Company $company): Pipeline
    {
        $pipeline = Pipeline::query()->create([
            'company_id' => $company->id,
            'name' => 'Продажи',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        self::seedStandardStages($pipeline);

        return $pipeline;
    }

    /**
     * Стандартные этапы для новой воронки (как при регистрации компании).
     */
    public static function seedStandardStages(Pipeline $pipeline): void
    {
        $definitions = [
            ['name' => 'Новый', 'sort_order' => 0, 'outcome' => null, 'color' => '#94a3b8'],
            ['name' => 'В работе', 'sort_order' => 1, 'outcome' => null, 'color' => '#3b82f6'],
            ['name' => 'Успешно', 'sort_order' => 2, 'outcome' => 'won', 'color' => '#22c55e'],
            ['name' => 'Отказ', 'sort_order' => 3, 'outcome' => 'lost', 'color' => '#ef4444'],
        ];

        foreach ($definitions as $def) {
            Stage::query()->create([
                'company_id' => $pipeline->company_id,
                'pipeline_id' => $pipeline->id,
                'name' => $def['name'],
                'sort_order' => $def['sort_order'],
                'color' => $def['color'],
                'outcome' => $def['outcome'],
            ]);
        }
    }
}
