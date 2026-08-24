<?php

namespace App\Http\Controllers;

use App\Models\TelegramSupportClient;
use App\Models\TelegramSupportMessage;
use App\Models\TelegramSupportProject;
use App\Services\Telegram\SupportTelegramBotService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelegramSupportProgrammerMiniAppController extends Controller
{
    public function __construct(
        private SupportTelegramBotService $supportBot,
    ) {}

    public function applications(Request $request): JsonResponse
    {
        $this->requireProgrammer($request);

        $filter = (string) $request->input('status', 'pending');
        if (! in_array($filter, ['pending', 'accepted', 'rejected', 'blocked', 'all'], true)) {
            $filter = 'pending';
        }

        $programmerUsernames = $this->supportBot->programmerUsernames();

        $clients = TelegramSupportClient::query()
            ->with('projects:id,name')
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pending')->whereNull('blocked_at'))
            ->when($filter === 'accepted', fn ($q) => $q->where('status', 'accepted')->whereNull('blocked_at'))
            ->when($filter === 'rejected', fn ($q) => $q->where('status', 'rejected')->whereNull('blocked_at'))
            ->when($filter === 'blocked', fn ($q) => $q->whereNotNull('blocked_at'))
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->filter(fn (TelegramSupportClient $client) => ! $this->supportBot->isProgrammerUsername($client->username))
            ->take(50)
            ->values()
            ->map(fn (TelegramSupportClient $client) => $this->clientPayload($client));

        return response()->json([
            'ok' => true,
            'filters' => ['status' => $filter],
            'projects' => TelegramSupportProject::query()->orderBy('name')->get(['id', 'name']),
            'clients' => $clients,
        ]);
    }

    public function accept(Request $request, TelegramSupportClient $supportClient): JsonResponse
    {
        $this->requireProgrammer($request);

        $validated = $request->validate([
            'name' => 'nullable|string|max:120',
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'integer|exists:telegram_support_projects,id',
        ]);

        if (isset($validated['name']) && trim((string) $validated['name']) !== '') {
            $supportClient->forceFill(['name' => trim((string) $validated['name'])])->save();
        }

        $supportClient->markAccepted();
        $supportClient->projects()->sync($validated['project_ids']);

        $this->supportBot->sendMessage(
            (int) $supportClient->client_chat_id,
            '✅ Заявка принята. Пишите сюда.',
        );

        return response()->json([
            'ok' => true,
            'message' => 'Принято.',
            'client' => $this->clientPayload($supportClient->fresh(['projects'])),
        ]);
    }

    public function reject(Request $request, TelegramSupportClient $supportClient): JsonResponse
    {
        $this->requireProgrammer($request);

        $supportClient->markRejected();
        $supportClient->projects()->detach();

        $this->supportBot->sendMessage(
            (int) $supportClient->client_chat_id,
            '❌ Заявка отклонена. Напишите /start, чтобы отправить снова.',
        );

        return response()->json([
            'ok' => true,
            'message' => 'Отклонено.',
            'client' => $this->clientPayload($supportClient->fresh(['projects'])),
        ]);
    }

    public function updateProjects(Request $request, TelegramSupportClient $supportClient): JsonResponse
    {
        $this->requireProgrammer($request);

        $validated = $request->validate([
            'name' => 'nullable|string|max:120',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:telegram_support_projects,id',
        ]);

        if (isset($validated['name']) && trim((string) $validated['name']) !== '') {
            $supportClient->forceFill(['name' => trim((string) $validated['name'])])->save();
        }

        if (array_key_exists('project_ids', $validated)) {
            $supportClient->projects()->sync($validated['project_ids'] ?? []);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Сохранено.',
            'client' => $this->clientPayload($supportClient->fresh(['projects'])),
        ]);
    }

    public function block(Request $request, TelegramSupportClient $supportClient): JsonResponse
    {
        $this->requireProgrammer($request);

        $supportClient->markBlocked();
        $this->supportBot->sendMessage(
            (int) $supportClient->client_chat_id,
            '🚫 Доступ к поддержке ограничен. Сообщения больше не принимаются.',
        );

        return response()->json([
            'ok' => true,
            'message' => 'Клиент заблокирован.',
            'client' => $this->clientPayload($supportClient->fresh(['projects'])),
        ]);
    }

    public function unblock(Request $request, TelegramSupportClient $supportClient): JsonResponse
    {
        $this->requireProgrammer($request);

        $supportClient->markUnblocked();

        return response()->json([
            'ok' => true,
            'message' => 'Клиент разблокирован.',
            'client' => $this->clientPayload($supportClient->fresh(['projects'])),
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $this->requireProgrammer($request);

        $projects = TelegramSupportProject::query()
            ->withCount([
                'clients',
                'messages as open_messages_count' => fn ($q) => $q->where('status', 'open'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (TelegramSupportProject $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'clients_count' => $project->clients_count,
                'open_messages_count' => $project->open_messages_count,
            ]);

        return response()->json([
            'ok' => true,
            'projects' => $projects,
        ]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $this->requireProgrammer($request);

        $validated = $request->validate([
            'name' => 'required|string|max:160',
        ]);

        $project = TelegramSupportProject::query()->create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Проект создан.',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'clients_count' => 0,
                'open_messages_count' => 0,
            ],
        ]);
    }

    public function updateProject(Request $request, TelegramSupportProject $project): JsonResponse
    {
        $this->requireProgrammer($request);

        $validated = $request->validate([
            'name' => 'required|string|max:160',
        ]);

        $project->update(['name' => trim($validated['name'])]);

        return response()->json([
            'ok' => true,
            'message' => 'Проект обновлён.',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
        ]);
    }

    public function destroyProject(Request $request, TelegramSupportProject $project): JsonResponse
    {
        $this->requireProgrammer($request);

        if ($project->messages()->where('status', 'open')->exists()) {
            return response()->json([
                'message' => 'Сначала закройте или удалите открытые сообщения по проекту.',
            ], 422);
        }

        $project->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Проект удалён.',
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $this->requireProgrammer($request);

        $projects = TelegramSupportProject::query()
            ->withCount([
                'messages as open_messages_count' => fn ($q) => $q->where('status', TelegramSupportMessage::STATUS_OPEN),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (TelegramSupportProject $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'open_messages_count' => $project->open_messages_count,
            ]);

        return response()->json([
            'ok' => true,
            'projects' => $projects,
        ]);
    }

    public function inboxShow(Request $request, TelegramSupportProject $project): JsonResponse
    {
        $this->requireProgrammer($request);

        $messages = TelegramSupportMessage::query()
            ->with('client:id,name,username,phone,company_name,telegram_user_id')
            ->where('telegram_support_project_id', $project->id)
            ->where('status', TelegramSupportMessage::STATUS_OPEN)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TelegramSupportMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'media_type' => $message->media_type,
                'media_url' => $message->hasPlayableMedia()
                    ? URL::temporarySignedRoute(
                        'tma.support.programmer.messages.media',
                        now()->addHours(12),
                        ['message' => $message->id],
                    )
                    : null,
                'created_at' => $message->created_at?->format('d.m.Y H:i'),
                'client' => [
                    'id' => $message->client?->id,
                    'name' => $message->client?->name,
                    'username' => $message->client?->username,
                    'phone' => $message->client?->phone,
                    'company_name' => $message->client?->company_name,
                ],
            ]);

        return response()->json([
            'ok' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, TelegramSupportMessage $message): JsonResponse
    {
        $this->requireProgrammer($request);
        abort_unless($message->isOpen(), 404);

        $validated = $request->validate([
            'body' => 'required|string|max:3500',
        ]);

        $client = $message->client;
        abort_unless($client !== null, 404);

        $text = trim($validated['body']);
        $replyTo = $message->client_telegram_message_id
            ? (int) $message->client_telegram_message_id
            : null;

        $sent = $this->supportBot->sendMessage(
            (int) $client->client_chat_id,
            $text,
            replyToMessageId: $replyTo,
        );

        if ($sent === null) {
            return response()->json([
                'message' => 'Не удалось отправить ответ в Telegram.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Отправлено.',
        ]);
    }

    public function complete(Request $request, TelegramSupportMessage $message): JsonResponse
    {
        $this->requireProgrammer($request);
        abort_unless($message->isOpen(), 404);

        $client = $message->client;
        abort_unless($client !== null, 404);

        $replyTo = $message->client_telegram_message_id
            ? (int) $message->client_telegram_message_id
            : null;

        $this->supportBot->sendMessage(
            (int) $client->client_chat_id,
            '✅ Готово',
            replyToMessageId: $replyTo,
        );

        $message->purgeMedia();
        $message->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Готово.',
        ]);
    }

    public function destroyMessage(Request $request, TelegramSupportMessage $message): JsonResponse
    {
        $this->requireProgrammer($request);
        abort_unless($message->isOpen(), 404);

        $message->purgeMedia();
        $message->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Удалено.',
        ]);
    }

    public function media(TelegramSupportMessage $message): StreamedResponse
    {
        abort_unless($message->hasPlayableMedia(), 404);
        abort_unless(Storage::disk('local')->exists($message->media_path), 404);

        return Storage::disk('local')->response(
            $message->media_path,
            basename($message->media_path),
            [
                'Content-Type' => $message->media_mime ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    /**
     * @return array{id: int, username: ?string, first_name: ?string, last_name: ?string}
     */
    private function requireProgrammer(Request $request): array
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $this->supportBot->validateInitData($validated['init_data']);
        if (! $telegramUser) {
            throw new HttpResponseException(response()->json([
                'message' => 'Не удалось проверить Telegram. Откройте мини-приложение из бота.',
            ], 422));
        }

        $username = isset($telegramUser['username']) ? (string) $telegramUser['username'] : null;
        if (! $this->supportBot->isProgrammerUsername($username)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Доступ только для программиста сайта.',
            ], 403));
        }

        return $telegramUser;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(TelegramSupportClient $client): array
    {
        $client->loadMissing('projects:id,name');

        return [
            'id' => $client->id,
            'name' => $client->name,
            'username' => $client->username,
            'phone' => $client->phone,
            'company_name' => $client->company_name,
            'message' => $client->message,
            'status' => $client->status,
            'is_blocked' => $client->isBlocked(),
            'telegram_user_id' => $client->telegram_user_id,
            'project_ids' => $client->projects->pluck('id')->all(),
            'projects' => $client->projects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])->values()->all(),
            'created_at' => $client->created_at?->format('d.m.Y H:i'),
        ];
    }
}
