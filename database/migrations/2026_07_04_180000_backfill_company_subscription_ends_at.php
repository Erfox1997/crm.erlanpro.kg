<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::query()
            ->with('tariff')
            ->whereNull('subscription_ends_at')
            ->each(function (Company $company): void {
                if ($company->tariff === null || $company->created_at === null) {
                    return;
                }

                $company->update([
                    'subscription_ends_at' => $company->created_at
                        ->copy()
                        ->addDays($company->tariff->duration_days),
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
