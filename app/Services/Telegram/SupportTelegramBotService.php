<?php

namespace App\Services\Telegram;

use App\Models\TelegramSupportClient;
use App\Models\TelegramSupportMessage;
use App\Models\TelegramSupportProject;
use App\Models\TelegramSupportRelay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportTelegramBotService
{
    public function token(): string
    {
        return trim((string) config('services.telegram.support_bot_token', ''));
    }

    public function botUsername(): string
    {
        return ltrim(trim((string) config('services.telegram.support_bot_username', '')), '@');
    }

    public function ownerChatId(): int
    {
        return (int) config('services.telegram.support_owner_chat_id', 0);
    }

    /**
     * @return list<string>
     */
    public function programmerUsernames(): array
    {
        $raw = (string) config('services.telegram.support_programmer_usernames', '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($value) => mb_strtolower(ltrim(trim((string) $value), '@')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isProgrammerUsername(?string $username): bool
    {
        $normalized = mb_strtolower(ltrim(trim((string) $username), '@'));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->programmerUsernames(), true);
    }

    /**
     * Programmers are operators, not support clients — remove mistaken client rows.
     */
    public function purgeProgrammerClientRecords(): void
    {
        $usernames = $this->programmerUsernames();
        if ($usernames === []) {
            return;
        }

        TelegramSupportClient::query()
            ->whereNotNull('username')
            ->orderBy('id')
            ->each(function (TelegramSupportClient $client) use ($usernames) {
                $normalized = mb_strtolower(ltrim((string) $client->username, '@'));
                if (! in_array($normalized, $usernames, true)) {
                    return;
                }

                $client->projects()->detach();
                $client->delete();
            });
    }

    public function webAppUrl(): string
    {
        $configured = trim((string) config('services.telegram.support_webapp_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/tma/support';
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->ownerChatId() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMe(): ?array
    {
        if ($this->token() === '') {
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

    /**
     * Validate Telegram Mini App initData and return the Telegram user payload.
     *
     * @return array{id: int, username?: ?string, first_name?: ?string, last_name?: ?string}|null
     */
    public function validateInitData(string $initData): ?array
    {
        if ($this->token() === '' || trim($initData) === '') {
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

    public function findClientByTelegramId(int $telegramUserId): ?TelegramSupportClient
    {
        if ($telegramUserId < 1) {
            return null;
        }

        return TelegramSupportClient::query()
            ->where('telegram_user_id', $telegramUserId)
            ->first();
    }

    /**
     * @param  array{id: int, username?: ?string, first_name?: ?string, last_name?: ?string}  $telegramUser
     * @param  array{name: string, phone?: ?string, company_name?: ?string, message: string}  $payload
     */
    public function submitApplication(array $telegramUser, array $payload): TelegramSupportClient
    {
        $telegramId = (int) ($telegramUser['id'] ?? 0);
        $username = isset($telegramUser['username']) ? ltrim((string) $telegramUser['username'], '@') : null;
        $fallbackName = trim(implode(' ', array_filter([
            (string) ($telegramUser['first_name'] ?? ''),
            (string) ($telegramUser['last_name'] ?? ''),
        ])));

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = $fallbackName !== '' ? $fallbackName : 'Клиент';
        }

        $client = TelegramSupportClient::query()->updateOrCreate(
            ['telegram_user_id' => $telegramId],
            [
                'client_chat_id' => $telegramId,
                'username' => $username,
                'name' => $name,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'company_name' => trim((string) ($payload['company_name'] ?? '')) ?: null,
                'message' => trim((string) ($payload['message'] ?? '')),
                'status' => TelegramSupportClient::STATUS_PENDING,
                'reviewed_at' => null,
            ],
        );

        $this->notifyOwnerAboutApplication($client);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        if (isset($payload['callback_query']) && is_array($payload['callback_query'])) {
            $this->handleCallbackQuery($payload['callback_query']);

            return;
        }

        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $chatId = (int) ($chat['id'] ?? 0);
        $messageId = (int) ($message['message_id'] ?? 0);
        if ($chatId < 1 || $messageId < 1) {
            return;
        }

        $ownerChatId = $this->ownerChatId();

        if ($chatId === $ownerChatId) {
            $this->handleOwnerReply($message);

            return;
        }

        if (($chat['type'] ?? '') !== 'private') {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->sendWelcome($chatId, $from);

            return;
        }

        $telegramUserId = (int) ($from['id'] ?? $chatId);
        $username = isset($from['username']) ? (string) $from['username'] : null;

        if ($this->isProgrammerUsername($username)) {
            $this->setChatMenuButton($chatId, 'Панель', 'web_app');
            $this->sendMessage(
                $chatId,
                "Вы программист сайта — клиентские заявки сюда не пишутся.\n\nОткройте панель: Заявки, Проекты и Входящие.",
                withWebAppButton: true,
                webAppButtonText: 'Открыть панель',
            );

            return;
        }

        $client = $this->findClientByTelegramId($telegramUserId);

        if ($client?->isBlocked()) {
            $this->sendMessage($chatId, '🚫 Доступ к поддержке ограничен. Сообщения не принимаются.');

            return;
        }

        // Clients: first message = application; after accept — support chat (+ project picker).
        if (! $client || ! $client->isAccepted()) {
            $this->handleClientApplicationMessage($chatId, $from, $client, $text);

            return;
        }

        $client->loadMissing('projects');
        $projects = $client->projects;

        if ($projects->isEmpty()) {
            $this->sendMessage(
                $chatId,
                'Ваша заявка принята, но проект ещё не назначен. Подождите — администратор привяжет проект.',
            );

            return;
        }

        $body = $text !== '' ? $text : '[вложение без текста]';

        if ($projects->count() === 1) {
            $this->storeIncomingMessage($client, $projects->first(), $body, $messageId, $from);

            return;
        }

        Cache::put($this->draftCacheKey($chatId), [
            'body' => $body,
            'message_id' => $messageId,
            'username' => isset($from['username']) ? ltrim((string) $from['username'], '@') : $client->username,
            'name' => trim(implode(' ', array_filter([
                (string) ($from['first_name'] ?? ''),
                (string) ($from['last_name'] ?? ''),
            ]))) ?: $client->name,
        ], now()->addHours(2));

        $rows = $projects->map(fn (TelegramSupportProject $project) => [[
            'text' => '📁 '.$project->name,
            'callback_data' => 'support:pickproj:'.$project->id,
        ]])->values()->all();

        $this->sendMessageWithInlineKeyboard(
            $chatId,
            "📁 У вас несколько проектов.\nВыберите, по какому проекту это сообщение:",
            $rows,
        );
    }

    /**
     * First client messages become a support application (no Mini App).
     *
     * @param  array<string, mixed>  $from
     */
    private function handleClientApplicationMessage(
        int $chatId,
        array $from,
        ?TelegramSupportClient $client,
        string $text,
    ): void {
        if ($client?->isPending()) {
            $this->sendMessage(
                $chatId,
                "⏳ Ваша заявка уже на рассмотрении.\n\nКак только её примут — пишите сюда снова, сообщения пойдут в поддержку.",
            );

            return;
        }

        if ($text === '') {
            $this->sendMessage(
                $chatId,
                'Напишите текстом, с чем нужна помощь — это и будет ваша заявка.',
            );

            return;
        }

        $telegramUser = [
            'id' => (int) ($from['id'] ?? $chatId),
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
        ];

        $this->submitApplication($telegramUser, [
            'name' => '',
            'phone' => null,
            'company_name' => null,
            'message' => $text,
        ]);

        $this->sendMessage(
            $chatId,
            "✅ Заявка отправлена.\n\nМы рассмотрим её и напишем сюда. После принятия сможете писать сообщения в поддержку.",
        );
    }

    /**
     * @param  array<string, mixed>  $from
     */
    public function storeIncomingMessage(
        TelegramSupportClient $client,
        TelegramSupportProject $project,
        string $body,
        ?int $clientTelegramMessageId = null,
        array $from = [],
    ): void {
        TelegramSupportMessage::query()->create([
            'telegram_support_client_id' => $client->id,
            'telegram_support_project_id' => $project->id,
            'body' => $body,
            'status' => TelegramSupportMessage::STATUS_OPEN,
            'client_telegram_message_id' => $clientTelegramMessageId,
        ]);

        $username = isset($from['username'])
            ? ltrim((string) $from['username'], '@')
            : $client->username;
        $name = trim(implode(' ', array_filter([
            (string) ($from['first_name'] ?? ''),
            (string) ($from['last_name'] ?? ''),
        ])));
        if ($name === '') {
            $name = (string) ($client->name ?? '');
        }

        $ownerChatId = $this->ownerChatId();
        $header = '📩 Новое обращение · '.$project->name."\n"
            .'От: '.($name !== '' ? $name : 'без имени')
            .($username ? ' (@'.$username.')' : '')
            ."\n— — —\n"
            .$body
            ."\n\n🖥️ Смотрите во «Входящие» в панели бота.";

        $ownerMessageId = $this->sendMessage($ownerChatId, $header);
        if ($ownerMessageId !== null) {
            TelegramSupportRelay::query()->create([
                'owner_chat_id' => $ownerChatId,
                'owner_message_id' => $ownerMessageId,
                'client_chat_id' => (int) $client->client_chat_id,
                'client_message_id' => $clientTelegramMessageId,
                'client_username' => $username,
                'client_name' => $name !== '' ? $name : null,
            ]);
        }

        $this->sendMessage(
            (int) $client->client_chat_id,
            '✅ Сообщение по проекту «'.$project->name.'» передано в поддержку. Мы ответим здесь.',
        );
    }

    private function draftCacheKey(int $chatId): string
    {
        return 'support_draft:'.$chatId;
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleCallbackQuery(array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');
        $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
        $fromId = (int) ($from['id'] ?? 0);

        if (preg_match('/^support:pickproj:(\d+)$/', $data, $matches)) {
            $this->handleProjectPickCallback($callback, (int) $matches[1], $fromId, $callbackId);

            return;
        }

        $this->handleOwnerCallback($callback);
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleProjectPickCallback(array $callback, int $projectId, int $fromId, string $callbackId): void
    {
        $client = $this->findClientByTelegramId($fromId);
        if (! $client || ! $client->isAccepted() || $client->isBlocked()) {
            $this->answerCallbackQuery($callbackId, 'Нет доступа.');

            return;
        }

        $project = $client->projects()->where('telegram_support_projects.id', $projectId)->first();
        if (! $project) {
            $this->answerCallbackQuery($callbackId, 'Проект недоступен.');

            return;
        }

        $draft = Cache::pull($this->draftCacheKey($fromId));
        if (! is_array($draft) || trim((string) ($draft['body'] ?? '')) === '') {
            $this->answerCallbackQuery($callbackId, 'Сообщение устарело. Напишите снова.');

            return;
        }

        $this->storeIncomingMessage(
            $client,
            $project,
            (string) $draft['body'],
            isset($draft['message_id']) ? (int) $draft['message_id'] : null,
            [
                'username' => $draft['username'] ?? null,
                'first_name' => $draft['name'] ?? null,
            ],
        );

        $this->answerCallbackQuery($callbackId, 'Отправлено: '.$project->name);
        $this->editCallbackMessage($callback, '✅ Выбрано: '.$project->name);
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleOwnerCallback(array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');
        $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
        $fromId = (int) ($from['id'] ?? 0);

        if ($fromId !== $this->ownerChatId()) {
            $this->answerCallbackQuery($callbackId, 'Только владелец может принимать заявки.');

            return;
        }

        if (! preg_match('/^support:(accept|reject):(\d+)$/', $data, $matches)) {
            $this->answerCallbackQuery($callbackId, 'Неизвестная команда.');

            return;
        }

        $action = $matches[1];
        $clientId = (int) $matches[2];
        $client = TelegramSupportClient::query()->find($clientId);

        if (! $client) {
            $this->answerCallbackQuery($callbackId, 'Заявка не найдена.');

            return;
        }

        if ($action === 'accept') {
            $client->markAccepted();
            $defaultProject = TelegramSupportProject::query()->firstOrCreate(
                ['name' => 'Общий'],
            );
            if (! $client->projects()->where('telegram_support_projects.id', $defaultProject->id)->exists()) {
                $client->projects()->syncWithoutDetaching([$defaultProject->id]);
            }

            $this->sendMessage(
                (int) $client->client_chat_id,
                "✅ Ваша заявка принята.\n\nТеперь можете писать сюда — сообщения уйдут в поддержку ErlanPro.",
            );
            $this->answerCallbackQuery($callbackId, 'Заявка принята.');
            $this->editCallbackMessage(
                $callback,
                '✅ Заявка #'.$client->id.' принята (проект «Общий»). Назначьте другие проекты в админке при необходимости.',
            );
        } else {
            $client->markRejected();
            $client->projects()->detach();
            $this->sendMessage(
                (int) $client->client_chat_id,
                "❌ Заявка отклонена.\n\nМожете написать сюда новое сообщение — отправим заявку снова.",
            );
            $this->answerCallbackQuery($callbackId, 'Заявка отклонена.');
            $this->editCallbackMessage($callback, '❌ Заявка #'.$client->id.' отклонена.');
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleOwnerReply(array $message): void
    {
        $replyTo = $message['reply_to_message'] ?? null;
        if (! is_array($replyTo)) {
            return;
        }

        $replyToId = (int) ($replyTo['message_id'] ?? 0);
        if ($replyToId < 1) {
            return;
        }

        $relay = TelegramSupportRelay::query()
            ->where('owner_chat_id', $this->ownerChatId())
            ->where('owner_message_id', $replyToId)
            ->first();

        if (! $relay) {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if ($text === '') {
            $this->sendMessage(
                $this->ownerChatId(),
                'Чтобы ответить клиенту, отправьте текстовый reply на сообщение поддержки.',
            );

            return;
        }

        $sent = $this->sendMessage(
            (int) $relay->client_chat_id,
            "💬 Ответ поддержки ErlanPro:\n\n".$text,
        );

        if ($sent !== null) {
            $this->sendMessage($this->ownerChatId(), '✅ Ответ отправлен клиенту.');
        }
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function sendWelcome(int $chatId, array $from = []): void
    {
        $telegramUserId = (int) ($from['id'] ?? $chatId);
        $username = isset($from['username']) ? (string) $from['username'] : null;

        if ($this->isProgrammerUsername($username)) {
            $this->purgeProgrammerClientRecords();
            $this->setChatMenuButton($chatId, 'Панель', 'web_app');
            $this->sendMessage(
                $chatId,
                "👋 Здравствуйте! Вы программист сайта ErlanPro.\n\n"
                ."Откройте панель — Заявки, Проекты и Входящие.\n"
                .'Браузерная админка для бота не используется.',
                withWebAppButton: true,
                webAppButtonText: 'Открыть панель',
            );

            return;
        }

        $this->setChatMenuButton($chatId, '', 'commands');
        $client = $this->findClientByTelegramId($telegramUserId);

        if ($client?->isBlocked()) {
            $this->sendMessage($chatId, '🚫 Доступ к поддержке ограничен.');

            return;
        }

        if ($client?->isAccepted()) {
            $this->sendMessage(
                $chatId,
                "Здравствуйте! Пишите сюда любое сообщение — оно уйдёт в поддержку ErlanPro.\n\n"
                .'Если проектов несколько, бот спросит, о каком идёт речь.',
            );

            return;
        }

        if ($client?->isPending()) {
            $this->sendMessage(
                $chatId,
                "Ваша заявка уже отправлена и ожидает рассмотрения.\n\nКак только её примут — пишите сюда снова.",
            );

            return;
        }

        $this->sendMessage(
            $chatId,
            "Здравствуйте! Это поддержка CRM ErlanPro.\n\n"
            ."Просто напишите сюда сообщение — это и будет заявка.\n"
            .'После принятия сможете писать в поддержку прямо в этом чате.',
        );
    }

    private function notifyOwnerAboutApplication(TelegramSupportClient $client): void
    {
        $ownerChatId = $this->ownerChatId();
        if ($ownerChatId < 1) {
            return;
        }

        $lines = [
            '🆕 Новая заявка в поддержку #'.$client->id,
            'Имя: '.($client->name ?: '—'),
            'Telegram: '.($client->username ? '@'.$client->username : 'id '.$client->telegram_user_id),
            'Телефон: '.($client->phone ?: '—'),
            'Компания: '.($client->company_name ?: '—'),
            '— — —',
            (string) $client->message,
        ];

        $this->sendMessageWithInlineKeyboard(
            $ownerChatId,
            implode("\n", $lines),
            [
                [
                    [
                        'text' => '✅ Принять',
                        'callback_data' => 'support:accept:'.$client->id,
                    ],
                    [
                        'text' => '❌ Отклонить',
                        'callback_data' => 'support:reject:'.$client->id,
                    ],
                ],
            ],
        );
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, web_app?: array{url: string}}>>  $rows
     */
    public function sendMessageWithInlineKeyboard(int $chatId, string $text, array $rows): ?int
    {
        if ($this->token() === '') {
            return null;
        }

        try {
            $json = Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                    'reply_markup' => [
                        'inline_keyboard' => $rows,
                    ],
                ])
                ->throw()
                ->json();

            $messageId = $json['result']['message_id'] ?? null;

            return is_numeric($messageId) ? (int) $messageId : null;
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot keyboard send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function sendMessage(
        int $chatId,
        string $text,
        bool $withWebAppButton = false,
        string $webAppButtonText = '📝 Открыть заявку',
    ): ?int
    {
        if ($this->token() === '') {
            return null;
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
                        'text' => $webAppButtonText,
                        'web_app' => ['url' => $this->webAppUrl()],
                    ],
                ]],
            ];
        }

        try {
            $json = Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/sendMessage', $payload)
                ->throw()
                ->json();

            $messageId = $json['result']['message_id'] ?? null;

            return is_numeric($messageId) ? (int) $messageId : null;
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function setChatMenuButton(?int $chatId = null, string $text = 'Заявка', string $type = 'web_app'): void
    {
        if ($this->token() === '') {
            return;
        }

        $menuButton = $type === 'commands'
            ? ['type' => 'commands']
            : [
                'type' => 'web_app',
                'text' => $text !== '' ? $text : 'Панель',
                'web_app' => ['url' => $this->webAppUrl()],
            ];

        $payload = [
            'menu_button' => $menuButton,
        ];

        if ($chatId !== null && $chatId > 0) {
            $payload['chat_id'] = $chatId;
        }

        try {
            Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/setChatMenuButton', $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot setChatMenuButton failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function editCallbackMessage(array $callback, string $text): void
    {
        $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
        $chatId = (int) (($message['chat']['id'] ?? 0));
        $messageId = (int) ($message['message_id'] ?? 0);

        if ($chatId < 1 || $messageId < 1 || $this->token() === '') {
            return;
        }

        try {
            Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot edit message failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function answerCallbackQuery(string $callbackId, string $text): void
    {
        if ($callbackId === '' || $this->token() === '') {
            return;
        }

        try {
            Http::baseUrl('https://api.telegram.org')
                ->timeout(15)
                ->asJson()
                ->post('/bot'.$this->token().'/answerCallbackQuery', [
                    'callback_query_id' => $callbackId,
                    'text' => $text,
                    'show_alert' => false,
                ])
                ->throw();
        } catch (\Throwable) {
            //
        }
    }

    private function forwardMessage(int $fromChatId, int $messageId, int $toChatId): void
    {
        try {
            Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/forwardMessage', [
                    'chat_id' => $toChatId,
                    'from_chat_id' => $fromChatId,
                    'message_id' => $messageId,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot forward failed', [
                'from' => $fromChatId,
                'to' => $toChatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(): array
    {
        if ($this->token() === '') {
            throw new \RuntimeException('TELEGRAM_SUPPORT_BOT_TOKEN is not set.');
        }

        $secret = trim((string) config('services.telegram.support_webhook_secret', ''));
        if ($secret === '') {
            throw new \RuntimeException('TELEGRAM_SUPPORT_WEBHOOK_SECRET is not set.');
        }

        $url = route('webhooks.telegram-support.handle', ['secret' => $secret]);

        $response = Http::baseUrl('https://api.telegram.org')
            ->timeout(20)
            ->asJson()
            ->post('/bot'.$this->token().'/setWebhook', [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message', 'callback_query'],
                'drop_pending_updates' => false,
            ])
            ->throw()
            ->json();

        $this->setChatMenuButton(null, '', 'commands');

        return is_array($response) ? $response : [];
    }
}
