<?php

namespace App\Http\Controllers;

use App\Models\MessengerConversation;
use App\Models\MessengerTask;
use App\Services\Messenger\ChatDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessengerTaskController extends Controller
{
    public function __construct(
        private ChatDistributionService $chatDistribution,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'filter' => ['nullable', 'in:open,done,all'],
        ]);

        $filter = $validated['filter'] ?? 'open';

        $tasks = MessengerTask::query()
            ->where('company_id', $companyId)
            ->when($filter === 'open', fn ($q) => $q->whereNull('completed_at'))
            ->when($filter === 'done', fn ($q) => $q->whereNotNull('completed_at'))
            ->with([
                'user:id,name',
                'conversation:id,participant_name,participant_username,participant_id,channel',
            ])
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('due_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MessengerTask $task) => $this->serializeTask($task));

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filter' => $filter,
            'pageTitle' => 'Задачи',
        ]);
    }

    public function store(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        abort_unless($conversation->company_id === $companyId, 403);
        abort_unless($this->chatDistribution->userCanViewConversation($user, $conversation), 403);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'due_on' => ['required', 'date'],
        ]);

        MessengerTask::query()->create([
            'company_id' => $companyId,
            'messenger_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'note' => trim($validated['note']),
            'due_on' => $validated['due_on'],
        ]);

        return back()->with('success', __('Задача создана.'));
    }

    public function complete(Request $request, MessengerTask $task): RedirectResponse
    {
        $this->assertCompanyTask($request, $task);

        if ($task->completed_at === null) {
            $task->update(['completed_at' => now()]);
        }

        return back()->with('success', __('Задача выполнена.'));
    }

    public function reopen(Request $request, MessengerTask $task): RedirectResponse
    {
        $this->assertCompanyTask($request, $task);

        $task->update(['completed_at' => null]);

        return back()->with('success', __('Задача снова открыта.'));
    }

    public function destroy(Request $request, MessengerTask $task): RedirectResponse
    {
        $this->assertCompanyTask($request, $task);
        $task->delete();

        return back()->with('success', __('Задача удалена.'));
    }

    protected function assertCompanyTask(Request $request, MessengerTask $task): void
    {
        abort_unless($task->company_id === (int) $request->user()->company_id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTask(MessengerTask $task): array
    {
        $conversation = $task->conversation;

        return [
            'id' => $task->id,
            'note' => $task->note,
            'due_on' => $task->due_on?->format('Y-m-d'),
            'due_on_label' => $task->due_on?->format('d.m.Y'),
            'completed' => $task->isCompleted(),
            'overdue' => $task->isOverdue(),
            'created_at' => $task->created_at?->format('d.m.Y H:i'),
            'author' => $task->user?->name,
            'conversation_id' => $conversation?->id,
            'client_label' => $conversation
                ? ($conversation->participant_name
                    ?: $conversation->participant_username
                    ?: $conversation->participant_id
                    ?: 'Чат #'.$conversation->id)
                : '—',
            'channel' => $conversation?->channel,
        ];
    }
}
