<?php

use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LegalController as AdminLegalController;
use App\Http\Controllers\Admin\PaymentRequisiteController as AdminPaymentRequisiteController;
use App\Http\Controllers\Admin\TariffController as AdminTariffController;
use App\Support\PlatformLegalDetails;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ChatDistributionController;
use App\Http\Controllers\ClientFieldDefinitionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\MessengerQuickReplyController;
use App\Http\Controllers\MetaOAuthController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PipelineTunnelController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StageTunnelController;
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

Route::get('/legal', function () {
    return Inertia::render('Legal', [
        'legal' => PlatformLegalDetails::forFrontend(),
    ]);
})->name('legal');

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
        Route::get('legal', [AdminLegalController::class, 'edit'])->name('legal.edit');
        Route::put('legal', [AdminLegalController::class, 'update'])->name('legal.update');
    });

Route::middleware(['auth', 'verified', 'company', 'tenant', 'page.access'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('clients', ClientController::class)->except(['show']);

    Route::get('/messenger', [MessengerController::class, 'index'])->name('messenger.index');
    Route::post('/messenger/sync', [MessengerController::class, 'sync'])->name('messenger.sync');
    Route::post('/messenger/ai-improve', [MessengerController::class, 'improveWithAi'])->name('messenger.ai-improve');
    Route::get('/comments', [CommentsController::class, 'index'])->name('comments.index');
    Route::post('/comments/sync', [CommentsController::class, 'sync'])->name('comments.sync');
    Route::post('/comments/{comment}/reply', [CommentsController::class, 'reply'])->name('comments.reply');
    Route::get('/client-fields', [ClientFieldDefinitionController::class, 'index'])->name('client-fields.index');
    Route::post('/client-fields', [ClientFieldDefinitionController::class, 'store'])->name('client-fields.store');
    Route::post('/client-fields/batch', [ClientFieldDefinitionController::class, 'storeBatch'])->name('client-fields.store-batch');
    Route::put('/client-fields/{clientFieldDefinition}', [ClientFieldDefinitionController::class, 'update'])->name('client-fields.update');
    Route::delete('/client-fields/{clientFieldDefinition}', [ClientFieldDefinitionController::class, 'destroy'])->name('client-fields.destroy');
    Route::get('/messenger/quick-replies', [MessengerQuickReplyController::class, 'index'])->name('messenger.quick-replies.index');
    Route::post('/messenger/quick-replies', [MessengerQuickReplyController::class, 'store'])->name('messenger.quick-replies.store');
    Route::post('/messenger/quick-replies/import', [MessengerQuickReplyController::class, 'import'])->name('messenger.quick-replies.import');
    Route::get('/messenger/quick-replies/sample', [MessengerQuickReplyController::class, 'sample'])->name('messenger.quick-replies.sample');
    Route::get('/messenger/quick-replies/{quickReply}/attachment', [MessengerQuickReplyController::class, 'attachment'])->name('messenger.quick-replies.attachment');
    Route::put('/messenger/quick-replies/{quickReply}', [MessengerQuickReplyController::class, 'update'])->name('messenger.quick-replies.update');
    Route::delete('/messenger/quick-replies/{quickReply}', [MessengerQuickReplyController::class, 'destroy'])->name('messenger.quick-replies.destroy');
    Route::post('/messenger/conversations/{conversation}/messages', [MessengerController::class, 'send'])->name('messenger.send');
    Route::post('/messenger/conversations/{conversation}/client', [MessengerController::class, 'saveClient'])->name('messenger.save-client');
    Route::patch('/messenger/conversations/{conversation}/deal-stage', [MessengerController::class, 'updateDealStage'])->name('messenger.update-deal-stage');
    Route::post('/messenger/conversations/{conversation}/quick-replies/{quickReply}', [MessengerController::class, 'sendQuickReply'])->name('messenger.send-quick-reply');
    Route::post('/messenger/messages/{message}/quick-reply', [MessengerQuickReplyController::class, 'storeFromMessage'])
        ->name('messenger.messages.quick-reply');
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
    Route::post('/stage-tunnels', [StageTunnelController::class, 'store'])->name('stage-tunnels.store');
    Route::delete('/stage-tunnels/{stage_tunnel}', [StageTunnelController::class, 'destroy'])->name('stage-tunnels.destroy');
    Route::post('/pipeline-tunnels', [PipelineTunnelController::class, 'store'])->name('pipeline-tunnels.store');
    Route::delete('/pipeline-tunnels/{pipeline_tunnel}', [PipelineTunnelController::class, 'destroy'])->name('pipeline-tunnels.destroy');
    Route::get('/broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
    Route::post('/broadcasts/preview', [BroadcastController::class, 'preview'])->name('broadcasts.preview');
    Route::get('/broadcasts/{broadcastCampaign}', [BroadcastController::class, 'show'])->name('broadcasts.show');
    Route::post('/broadcasts/{broadcastCampaign}/cancel', [BroadcastController::class, 'cancel'])->name('broadcasts.cancel');

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

    Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
    Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
    Route::put('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
    Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::get('/employees/sample', [EmployeeController::class, 'sample'])->name('employees.sample');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::get('/chat-distribution', [ChatDistributionController::class, 'index'])->name('chat-distribution.index');
    Route::put('/chat-distribution', [ChatDistributionController::class, 'update'])->name('chat-distribution.update');

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
