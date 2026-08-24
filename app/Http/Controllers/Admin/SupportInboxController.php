<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramSupportMessage;
use App\Models\TelegramSupportProject;
use App\Services\Telegram\SupportTelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportInboxController extends Controller
{
    public function __construct(
        private SupportTelegramBotService $supportBot,
    ) {}

    public function index(): Response
    {
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

        return Inertia::render('Admin/Support/Inbox', [
            'projects' => $projects,
            'pageTitle' => 'Входящие сообщения',
        ]);
    }

    public function show(TelegramSupportProject $project): Response
    {
        $messages = TelegramSupportMessage::query()
            ->with('client:id,name,username,phone,company_name,telegram_user_id')
            ->where('telegram_support_project_id', $project->id)
            ->where('status', TelegramSupportMessage::STATUS_OPEN)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TelegramSupportMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->format('d.m.Y H:i'),
                'client' => [
                    'id' => $message->client?->id,
                    'name' => $message->client?->name,
                    'username' => $message->client?->username,
                    'phone' => $message->client?->phone,
                    'company_name' => $message->client?->company_name,
                ],
            ]);

        return Inertia::render('Admin/Support/InboxShow', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'messages' => $messages,
            'pageTitle' => $project->name,
        ]);
    }

    public function reply(Request $request, TelegramSupportMessage $message): RedirectResponse
    {
        abort_unless($message->isOpen(), 404);

        $validated = $request->validate([
            'body' => 'required|string|max:3500',
        ]);

        $client = $message->client;
        abort_unless($client !== null, 404);

        $text = "💬 Ответ поддержки ErlanPro:\n\n".trim($validated['body']);
        $sent = $this->supportBot->sendMessage((int) $client->client_chat_id, $text);

        if ($sent === null) {
            return back()->withErrors([
                'body' => __('Не удалось отправить ответ в Telegram.'),
            ]);
        }

        return back()->with('success', __('Ответ отправлен клиенту.'));
    }

    public function complete(TelegramSupportMessage $message): RedirectResponse
    {
        abort_unless($message->isOpen(), 404);

        $client = $message->client;
        abort_unless($client !== null, 404);

        $projectName = $message->project?->name ?? 'проект';
        $doneText = "✅ Готово!\n\n"
            ."Ваше обращение по проекту «{$projectName}» выполнено 🎉\n\n"
            .'Если появятся ещё вопросы — напишите нам снова 🙌';

        $this->supportBot->sendMessage((int) $client->client_chat_id, $doneText);

        $message->update([
            'status' => TelegramSupportMessage::STATUS_DONE,
            'done_at' => now(),
        ]);

        return back()->with('success', __('Отмечено выполненным, клиенту отправлено уведомление.'));
    }

    public function destroy(TelegramSupportMessage $message): RedirectResponse
    {
        abort_unless($message->isOpen(), 404);

        $message->update([
            'status' => TelegramSupportMessage::STATUS_DELETED,
        ]);

        return back()->with('success', __('Сообщение удалено. Клиенту ничего не отправлено.'));
    }
}
