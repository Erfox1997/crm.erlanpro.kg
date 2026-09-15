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

    public function entry(Request $request): Response
    {
        return Inertia::render('TelegramMiniApp/Entry', [
            'botConfigured' => $this->managerBot->isConfigured(),
            'botUsername' => $this->managerBot->botUsername(),
            'conversationId' => $request->integer('conversation') ?: null,
        ]);
    }

    public function auth(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
            'conversation' => ['nullable', 'integer', 'min:1'],
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
                'message' => __('Доступ запрещён. Ваш Telegram не привязан к сотруднику компании в CRM. Укажите @username в «Сотрудники» / профиле и нажмите /start в боте.'),
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

        $params = ['mini' => 1];
        if (! empty($validated['conversation'])) {
            $params['conversation'] = (int) $validated['conversation'];
        }

        $redirect = route('messenger.index', $params);
        $cookie = cookie('crm_tma', '1', 60 * 24 * 60, '/', null, false, false, false, 'lax'); // 60 days

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => $redirect,
            ])->cookie($cookie);
        }

        return redirect()
            ->route('messenger.index', $params)
            ->cookie($cookie);
    }
}
