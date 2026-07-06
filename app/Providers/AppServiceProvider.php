<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ClientFieldDefinition;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineTunnel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Route::bind('client', function (string $value) {
            return Client::query()
                ->where('company_id', auth()->user()?->company_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('clientFieldDefinition', function (string $value) {
            return ClientFieldDefinition::query()
                ->where('company_id', auth()->user()?->company_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('deal', function (string $value) {
            return Deal::query()
                ->where('company_id', auth()->user()?->company_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('pipeline', function (string $value) {
            return Pipeline::query()
                ->where('company_id', auth()->user()?->company_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('pipeline_tunnel', function (string $value) {
            return PipelineTunnel::query()
                ->where('company_id', auth()->user()?->company_id)
                ->whereKey($value)
                ->firstOrFail();
        });
    }
}
