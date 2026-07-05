<?php

namespace App\Http\Controllers;

use App\Models\MessengerQuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessengerQuickReplyController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $quickReplies = MessengerQuickReply::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'body', 'sort_order']);

        return Inertia::render('Messenger/QuickReplies', [
            'quickReplies' => $quickReplies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:2000',
        ]);

        $sortOrder = (int) MessengerQuickReply::query()
            ->where('company_id', $companyId)
            ->max('sort_order') + 1;

        MessengerQuickReply::query()->create([
            'company_id' => $companyId,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'sort_order' => $sortOrder,
        ]);

        return back()->with('success', __('Быстрый ответ добавлен.'));
    }

    public function update(Request $request, MessengerQuickReply $quickReply): RedirectResponse
    {
        abort_unless($quickReply->company_id === (int) $request->user()->company_id, 403);

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:2000',
        ]);

        $quickReply->update($validated);

        return back()->with('success', __('Быстрый ответ обновлён.'));
    }

    public function destroy(Request $request, MessengerQuickReply $quickReply): RedirectResponse
    {
        abort_unless($quickReply->company_id === (int) $request->user()->company_id, 403);

        $quickReply->delete();

        return back()->with('success', __('Быстрый ответ удалён.'));
    }
}
