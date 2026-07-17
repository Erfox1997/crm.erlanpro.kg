<?php

namespace App\Http\Controllers;

use App\Services\Telegram\ManagerTelegramBotService;
use App\Support\CrmPageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TelegramMiniAppController extends Controller
{
    public function __construct(
        private ManagerTelegramBotService $managerBot,
    ) {}

    public function entry(): Response
    {
        return Inertia::render('TelegramMiniApp/Entry', [
            'botConfigured' => $this->managerBot->isConfigured(),
            'botUsername' => $this->managerBot->botUsername(),
        ]);
    }

    public function auth(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $this->managerBot->validateInitData($validated['init_data']);
        if (! $telegramUser) {
            return response()->json([
                'message' => __('Не удалось проверить Telegram. Откройте приложение заново из бота.'),
            ], 422);
        }

        $user = $this->managerBot->resolveUserFromTelegram($telegramUser);
        if (! $user) {
            return response()->json([
                'message' => __('Доступ запрещён. Ваш Telegram не привязан к сотруднику компании в CRM.'),
            ], 403);
        }

        if ($user->isDismissed()) {
            return response()->json([
                'message' => __('Аккаунт отключён. Обратитесь к владельцу компании.'),
            ], 403);
        }

        if (! CrmPageCatalog::userCanAccess($user, 'messenger')) {
            return response()->json([
                'message' => __('У вашей должности нет доступа к мессенджеру.'),
            ], 403);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('telegram_mini_app', true);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('messenger.index', ['mini' => 1]),
            ]);
        }

        return redirect()->route('messenger.index', ['mini' => 1]);
    }
}
