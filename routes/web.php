<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PipelineTunnelController;
use App\Http\Controllers\StageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('webhooks.meta.handle');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'appName' => config('app.name'),
    ]);
});

Route::middleware(['auth', 'verified', 'company'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('clients', ClientController::class)->except(['show']);

    Route::get('/messenger', [MessengerController::class, 'index'])->name('messenger.index');
    Route::post('/messenger/sync', [MessengerController::class, 'sync'])->name('messenger.sync');
    Route::post('/messenger/conversations/{conversation}/messages', [MessengerController::class, 'send'])->name('messenger.send');
    Route::get('/funnels', [FunnelController::class, 'index'])->name('funnels.index');
    Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::patch('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::post('/pipelines/{pipeline}/default', [PipelineController::class, 'setDefault'])->name('pipelines.default');
    Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');
    Route::post('/pipelines/{pipeline}/stages', [StageController::class, 'store'])->name('stages.store');
    Route::patch('/pipelines/{pipeline}/stages/reorder', [StageController::class, 'reorder'])->name('stages.reorder');
    Route::patch('/stages/{stage}', [StageController::class, 'update'])->name('stages.update');
    Route::delete('/stages/{stage}', [StageController::class, 'destroy'])->name('stages.destroy');
    Route::post('/pipeline-tunnels', [PipelineTunnelController::class, 'store'])->name('pipeline-tunnels.store');
    Route::delete('/pipeline-tunnels/{pipeline_tunnel}', [PipelineTunnelController::class, 'destroy'])->name('pipeline-tunnels.destroy');
    Route::get('/employees', fn () => Inertia::render('Placeholder', ['title' => 'Сотрудники']))->name('employees.index');
    Route::get('/tasks', fn () => Inertia::render('Placeholder', ['title' => 'Задачи']))->name('tasks.index');
    Route::get('/warehouse', fn () => Inertia::render('Placeholder', ['title' => 'Склад']))->name('warehouse.index');
    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::put('/integrations/{provider}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::get('/tariffs', fn () => Inertia::render('Placeholder', ['title' => 'Тарифы']))->name('tariffs.index');

    Route::get('/deals', function (\Illuminate\Http\Request $request) {
        return redirect()->route('funnels.index', $request->query());
    })->name('deals.index');
    Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
    Route::patch('/deals/{deal}/stage', [DealController::class, 'updateStage'])->name('deals.update-stage');
    Route::delete('/deals/{deal}', [DealController::class, 'destroy'])->name('deals.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
