<?php

namespace App\Http\Controllers;

use App\Services\Telegram\SupportTelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TelegramSupportMiniAppController extends Controller
{
    public function __construct(
        private SupportTelegramBotService $supportBot,
    ) {}

    public function entry(): Response
    {
        return Inertia::render('TelegramMiniApp/Support', [
            'botConfigured' => $this->supportBot->isConfigured(),
            'botUsername' => $this->supportBot->botUsername(),
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $this->supportBot->validateInitData($validated['init_data']);
        if (! $telegramUser) {
            return response()->json([
                'message' => __('Не удалось проверить Telegram. Откройте мини-приложение из бота.'),
            ], 422);
        }

        $username = isset($telegramUser['username']) ? (string) $telegramUser['username'] : null;
        $isProgrammer = $this->supportBot->isProgrammerUsername($username);

        if ($isProgrammer) {
            $this->supportBot->purgeProgrammerClientRecords();

            return response()->json([
                'ok' => true,
                'is_programmer' => true,
                'user' => [
                    'id' => $telegramUser['id'],
                    'username' => $telegramUser['username'] ?? null,
                    'first_name' => $telegramUser['first_name'] ?? null,
                    'last_name' => $telegramUser['last_name'] ?? null,
                ],
                'application' => null,
            ]);
        }

        $client = $this->supportBot->findClientByTelegramId((int) $telegramUser['id']);

        return response()->json([
            'ok' => true,
            'is_programmer' => false,
            'user' => [
                'id' => $telegramUser['id'],
                'username' => $telegramUser['username'] ?? null,
                'first_name' => $telegramUser['first_name'] ?? null,
                'last_name' => $telegramUser['last_name'] ?? null,
            ],
            'application' => $client ? [
                'status' => $client->status,
                'name' => $client->name,
                'phone' => $client->phone,
                'company_name' => $client->company_name,
                'message' => $client->message,
                'created_at' => $client->created_at?->toIso8601String(),
                'reviewed_at' => $client->reviewed_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:64'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $telegramUser = $this->supportBot->validateInitData($validated['init_data']);
        if (! $telegramUser) {
            return response()->json([
                'message' => __('Не удалось проверить Telegram. Откройте мини-приложение из бота.'),
            ], 422);
        }

        $username = isset($telegramUser['username']) ? (string) $telegramUser['username'] : null;
        if ($this->supportBot->isProgrammerUsername($username)) {
            $this->supportBot->purgeProgrammerClientRecords();

            return response()->json([
                'message' => __('Вы программист сайта — заявка не нужна. Откройте панель поддержки.'),
                'is_programmer' => true,
            ], 422);
        }

        $existing = $this->supportBot->findClientByTelegramId((int) $telegramUser['id']);
        if ($existing?->isAccepted()) {
            return response()->json([
                'message' => __('Заявка уже принята. Пишите прямо в чат бота.'),
                'application' => [
                    'status' => $existing->status,
                ],
            ], 422);
        }

        if ($existing?->isPending()) {
            return response()->json([
                'message' => __('Заявка уже отправлена и ожидает рассмотрения.'),
                'application' => [
                    'status' => $existing->status,
                ],
            ], 422);
        }

        if (! $this->supportBot->isConfigured()) {
            return response()->json([
                'message' => __('Поддержка временно недоступна. Попробуйте позже.'),
            ], 503);
        }

        $client = $this->supportBot->submitApplication($telegramUser, [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'message' => $validated['message'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => __('Заявка отправлена. Ожидайте решения — мы напишем в боте.'),
            'application' => [
                'status' => $client->status,
                'name' => $client->name,
                'phone' => $client->phone,
                'company_name' => $client->company_name,
                'message' => $client->message,
            ],
        ]);
    }
}
