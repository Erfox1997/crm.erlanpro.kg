<?php

namespace App\Services\Telegram;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\User;
use App\Services\Messenger\ChatDistributionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ManagerTelegramBotService
{
    public function __construct(
        private ChatDistributionService $chatDistribution,
    ) {}

    public function token(): string
    {
        return trim((string) config('services.telegram.manager_bot_token', ''));
    }

    public function botUsername(): string
    {
        return ltrim(trim((string) config('services.telegram.manager_bot_username', '')), '@');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMe(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $json = Http::baseUrl('https://api.telegram.org')
                ->timeout(15)
                ->get('/bot'.$this->token().'/getMe')
                ->throw()
                ->json();

            return is_array($json['result'] ?? null) ? $json['result'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '';
    }

    public function webAppUrl(): string
    {
        $configured = trim((string) config('services.telegram.manager_webapp_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/tma';
    }

    /**
     * Validate Telegram Mini App initData and return the Telegram user payload.
     *
     * @return array{id: int, username?: string, first_name?: string, last_name?: string}|null
     */
    public function validateInitData(string $initData): ?array
    {
        if (! $this->isConfigured() || trim($initData) === '') {
            return null;
        }

        parse_str($initData, $data);
        $hash = (string) ($data['hash'] ?? '');
        if ($hash === '') {
            return null;
        }

        unset($data['hash']);
        ksort($data);

        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = $key.'='.$value;
        }
        $dataCheckString = implode("\n", $pairs);

        $secretKey = hash_hmac('sha256', $this->token(), 'WebAppData', true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculated, $hash)) {
            return null;
        }

        $authDate = (int) ($data['auth_date'] ?? 0);
        if ($authDate < 1 || abs(time() - $authDate) > 86400) {
            return null;
        }

        $userJson = (string) ($data['user'] ?? '');
        $user = json_decode($userJson, true);
        if (! is_array($user) || empty($user['id'])) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => isset($user['username']) ? (string) $user['username'] : null,
            'first_name' => isset($user['first_name']) ? (string) $user['first_name'] : null,
            'last_name' => isset($user['last_name']) ? (string) $user['last_name'] : null,
        ];
    }

    /**
     * Resolve CRM user by Telegram identity. Binds telegram_id on first match by username.
     */
    public function resolveUserFromTelegram(array $telegramUser): ?User
    {
        $telegramId = (int) ($telegramUser['id'] ?? 0);
        if ($telegramId < 1) {
            return null;
        }

        $user = User::query()
            ->where('telegram_id', $telegramId)
            ->whereNotNull('company_id')
            ->whereNull('dismissed_at')
            ->first();

        if ($user) {
            $username = $this->normalizeUsername($telegramUser['username'] ?? null);
            if ($username && $user->telegram_username !== $username) {
                $user->forceFill(['telegram_username' => $username])->save();
            }

            return $user;
        }

        $username = $this->normalizeUsername($telegramUser['username'] ?? null);
        if ($username === null) {
            return null;
        }

        $user = User::query()
            ->whereNotNull('company_id')
            ->whereNull('dismissed_at')
            ->whereNull('telegram_id')
            ->whereRaw('LOWER(telegram_username) = ?', [$username])
            ->first();

        if (! $user) {
            return null;
        }

        $user->forceFill([
            'telegram_id' => $telegramId,
            'telegram_username' => $username,
        ])->save();

        return $user;
    }

    public function normalizeUsername(?string $username): ?string
    {
        $username = strtolower(ltrim(trim((string) $username), '@'));

        if ($username === '' || ! preg_match('/^[a-z0-9_]{5,64}$/', $username)) {
            return null;
        }

        return $username;
    }

    public function handleWebhookPayload(array $payload): void
    {
        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chat = $message['chat'] ?? [];
        $from = $message['from'] ?? [];
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = (int) ($chat['id'] ?? $from['id'] ?? 0);
        $username = $this->normalizeUsername($from['username'] ?? null);

        if ($chatId < 1) {
            return;
        }

        if (str_starts_with($text, '/start')) {
            $user = User::query()
                ->where('telegram_id', $chatId)
                ->whereNull('dismissed_at')
                ->first();

            if (! $user && $username) {
                $user = User::query()
                    ->whereNull('telegram_id')
                    ->whereNull('dismissed_at')
                    ->whereRaw('LOWER(telegram_username) = ?', [$username])
                    ->first();

                if ($user) {
                    $user->forceFill([
                        'telegram_id' => $chatId,
                        'telegram_username' => $username,
                    ])->save();
                }
            }

            if ($user) {
                $this->sendMessage(
                    $chatId,
                    "✅ Аккаунт привязан: {$user->name}\nОткройте мессенджер кнопкой ниже.",
                    true,
                );
            } else {
                $this->sendMessage(
                    $chatId,
                    "⛔ Доступ закрыт.\nПопросите владельца CRM добавить ваш Telegram (@username) в разделе «Сотрудники», затем нажмите /start снова.",
                );
            }
        }
    }

    public function notifyNewInboundMessage(MessengerMessage $message): void
    {
        if (! $this->isConfigured() || $message->direction !== 'inbound') {
            return;
        }

        $conversation = $message->conversation()->first();
        if (! $conversation instanceof MessengerConversation) {
            return;
        }

        $recipients = $this->recipientsForConversation($conversation);
        if ($recipients->isEmpty()) {
            return;
        }

        $name = $conversation->participant_name
            ?: $conversation->participant_username
            ?: $conversation->participant_id
            ?: 'Клиент';

        $preview = $message->previewLabel();
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117).'...';
        }

        $text = "💬 Новое сообщение\nот {$name}\n\n{$preview}";

        foreach ($recipients as $user) {
            if (! $user->telegram_id) {
                continue;
            }

            $this->sendMessage((int) $user->telegram_id, $text, true);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function recipientsForConversation(MessengerConversation $conversation)
    {
        $query = User::query()
            ->where('company_id', $conversation->company_id)
            ->whereNotNull('telegram_id');

        if ($conversation->assigned_user_id) {
            return $query->where('id', $conversation->assigned_user_id)->get();
        }

        // Unassigned: notify owners + employees who can see messenger chats.
        return $query
            ->where(function ($inner) {
                $inner->where('company_role', 'owner')
                    ->orWhereNull('company_role')
                    ->orWhere('company_role', 'employee');
            })
            ->get()
            ->filter(function (User $user) use ($conversation) {
                return $this->chatDistribution->userCanViewConversation($user, $conversation);
            })
            ->values();
    }

    public function sendMessage(int $chatId, string $text, bool $withWebAppButton = false): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($withWebAppButton) {
            $payload['reply_markup'] = [
                'inline_keyboard' => [[
                    [
                        'text' => 'Открыть мессенджер',
                        'web_app' => ['url' => $this->webAppUrl()],
                    ],
                ]],
            ];
        }

        try {
            Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/sendMessage', $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Manager Telegram bot send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function setWebhook(): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('TELEGRAM_MANAGER_BOT_TOKEN is not set.');
        }

        $secret = trim((string) config('services.telegram.manager_webhook_secret', ''));
        if ($secret === '') {
            throw new \RuntimeException('TELEGRAM_MANAGER_WEBHOOK_SECRET is not set.');
        }

        $url = route('webhooks.telegram-manager.handle', ['secret' => $secret]);

        $response = Http::baseUrl('https://api.telegram.org')
            ->timeout(20)
            ->asJson()
            ->post('/bot'.$this->token().'/setWebhook', [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message'],
                'drop_pending_updates' => false,
            ])
            ->throw()
            ->json();

        // Menu button → Mini App
        Http::baseUrl('https://api.telegram.org')
            ->timeout(20)
            ->asJson()
            ->post('/bot'.$this->token().'/setChatMenuButton', [
                'menu_button' => [
                    'type' => 'web_app',
                    'text' => 'Мессенджер',
                    'web_app' => ['url' => $this->webAppUrl()],
                ],
            ]);

        return is_array($response) ? $response : [];
    }
}
