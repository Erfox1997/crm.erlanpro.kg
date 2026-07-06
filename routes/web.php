<?php

use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PaymentRequisiteController as AdminPaymentRequisiteController;
use App\Http\Controllers\Admin\TariffController as AdminTariffController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\MessengerQuickReplyController;
use App\Http\Controllers\MetaOAuthController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PipelineTunnelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WappiOutboundMediaController;
use App\Http\Controllers\WappiWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('webhooks.meta.handle');
Route::post('/webhooks/wappi', [WappiWebhookController::class, 'handle'])->name('webhooks.wappi.handle');
Route::post('/webhooks/telegram/{secret}', [TelegramWebhookController::class, 'handle'])
    ->where('secret', '[a-zA-Z0-9]+')
    ->name('webhooks.telegram.handle');
Route::get('/media/wappi-outbound/{filename}', [WappiOutboundMediaController::class, 'show'])
    ->where('filename', '[a-zA-Z0-9._-]+')
    ->name('wappi.outbound-media');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'appName' => config('app.name'),
    ]);
});

Route::get('/privacy', function () {
    return Inertia::render('Privacy', [
        'appName' => config('app.name'),
        'contactEmail' => config('mail.from.address', 'support@erlanpro.kg'),
    ]);
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms', [
        'appName' => config('app.name'),
        'contactEmail' => config('mail.from.address', 'support@erlanpro.kg'),
    ]);
})->name('terms');

Route::middleware(['auth', 'verified', 'platform.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('tariffs', AdminTariffController::class)->except(['show']);
        Route::get('companies', [AdminCompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{company}', [AdminCompanyController::class, 'show'])->name('companies.show');
        Route::put('companies/{company}', [AdminCompanyController::class, 'update'])->name('companies.update');
        Route::get('payment-requisites', [AdminPaymentRequisiteController::class, 'edit'])->name('payment-requisites.edit');
        Route::post('payment-requisites', [AdminPaymentRequisiteController::class, 'update'])->name('payment-requisites.update');
    });

Route::middleware(['auth', 'verified', 'company', 'tenant'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('clients', ClientController::class)->except(['show']);

    Route::get('/messenger', [MessengerController::class, 'index'])->name('messenger.index');
    Route::post('/messenger/sync', [MessengerController::class, 'sync'])->name('messenger.sync');
    Route::get('/messenger/quick-replies', [MessengerQuickReplyController::class, 'index'])->name('messenger.quick-replies.index');
    Route::post('/messenger/quick-replies', [MessengerQuickReplyController::class, 'store'])->name('messenger.quick-replies.store');
    Route::post('/messenger/quick-replies/import', [MessengerQuickReplyController::class, 'import'])->name('messenger.quick-replies.import');
    Route::get('/messenger/quick-replies/sample', [MessengerQuickReplyController::class, 'sample'])->name('messenger.quick-replies.sample');
    Route::get('/messenger/quick-replies/{quickReply}/attachment', [MessengerQuickReplyController::class, 'attachment'])->name('messenger.quick-replies.attachment');
    Route::put('/messenger/quick-replies/{quickReply}', [MessengerQuickReplyController::class, 'update'])->name('messenger.quick-replies.update');
    Route::delete('/messenger/quick-replies/{quickReply}', [MessengerQuickReplyController::class, 'destroy'])->name('messenger.quick-replies.destroy');
    Route::post('/messenger/conversations/{conversation}/messages', [MessengerController::class, 'send'])->name('messenger.send');
    Route::post('/messenger/conversations/{conversation}/quick-replies/{quickReply}', [MessengerController::class, 'sendQuickReply'])->name('messenger.send-quick-reply');
    Route::get('/messenger/messages/{message}/attachments/{index}', [MessengerController::class, 'attachment'])
        ->whereNumber('index')
        ->name('messenger.attachment');
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
    Route::get('/integrations/instagram/oauth', [MetaOAuthController::class, 'redirect'])->defaults('provider', 'instagram')->name('integrations.instagram.oauth');
    Route::get('/integrations/instagram/oauth/callback', [MetaOAuthController::class, 'callback'])->defaults('provider', 'instagram')->name('integrations.instagram.callback');
    Route::get('/integrations/facebook/oauth', [MetaOAuthController::class, 'redirect'])->defaults('provider', 'facebook')->name('integrations.facebook.oauth');
    Route::get('/integrations/facebook/oauth/callback', [MetaOAuthController::class, 'callback'])->defaults('provider', 'facebook')->name('integrations.facebook.callback');
    Route::get('/integrations/meta/oauth/{provider}/select-page', [MetaOAuthController::class, 'selectPage'])->name('integrations.meta.oauth.select-page');
    Route::post('/integrations/meta/oauth/{provider}/select-page', [MetaOAuthController::class, 'storeSelectedPage'])->name('integrations.meta.oauth.select-page.store');
    Route::put('/integrations/{provider}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::get('/tariffs', [TariffController::class, 'index'])->name('tariffs.index');

    Route::get('/deals', function (Request $request) {
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
