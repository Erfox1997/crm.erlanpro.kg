<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramSupportClient;
use App\Models\TelegramSupportProject;
use App\Services\Telegram\SupportTelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportApplicationController extends Controller
{
    public function __construct(
        private SupportTelegramBotService $supportBot,
    ) {}

    public function index(Request $request): Response
    {
        $filter = (string) $request->query('status', 'pending');
        if (! in_array($filter, ['pending', 'accepted', 'rejected', 'blocked', 'all'], true)) {
            $filter = 'pending';
        }

        $clients = TelegramSupportClient::query()
            ->with('projects:id,name')
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pending')->whereNull('blocked_at'))
            ->when($filter === 'accepted', fn ($q) => $q->where('status', 'accepted')->whereNull('blocked_at'))
            ->when($filter === 'rejected', fn ($q) => $q->where('status', 'rejected')->whereNull('blocked_at'))
            ->when($filter === 'blocked', fn ($q) => $q->whereNotNull('blocked_at'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TelegramSupportClient $client) => [
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
            ]);

        return Inertia::render('Admin/Support/Applications', [
            'clients' => $clients,
            'projects' => TelegramSupportProject::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['status' => $filter],
            'pageTitle' => 'Заявки поддержки',
        ]);
    }

    public function accept(Request $request, TelegramSupportClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'integer|exists:telegram_support_projects,id',
        ]);

        $client->markAccepted();
        $client->projects()->sync($validated['project_ids']);

        $this->supportBot->sendMessage(
            (int) $client->client_chat_id,
            "✅ Ваша заявка принята.\n\nТеперь можете писать сюда — сообщения уйдут в поддержку ErlanPro.",
        );

        return back()->with('success', __('Клиент принят и привязан к проекту(ам).'));
    }

    public function reject(TelegramSupportClient $client): RedirectResponse
    {
        $client->markRejected();
        $client->projects()->detach();

        $this->supportBot->sendMessage(
            (int) $client->client_chat_id,
            "❌ Заявка отклонена.\n\nПри необходимости откройте мини-приложение снова и отправьте новую заявку.",
            withWebAppButton: true,
        );

        return back()->with('success', __('Заявка отклонена.'));
    }

    public function updateProjects(Request $request, TelegramSupportClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'integer|exists:telegram_support_projects,id',
        ]);

        if (! $client->isAccepted()) {
            $client->markAccepted();
        }

        $client->projects()->sync($validated['project_ids']);

        return back()->with('success', __('Проекты клиента обновлены.'));
    }

    public function block(TelegramSupportClient $client): RedirectResponse
    {
        $client->markBlocked();

        $this->supportBot->sendMessage(
            (int) $client->client_chat_id,
            '🚫 Доступ к поддержке ограничен. Сообщения больше не принимаются.',
        );

        return back()->with('success', __('Клиент заблокирован.'));
    }

    public function unblock(TelegramSupportClient $client): RedirectResponse
    {
        $client->markUnblocked();

        return back()->with('success', __('Клиент разблокирован.'));
    }
}
