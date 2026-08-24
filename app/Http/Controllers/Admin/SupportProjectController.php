<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramSupportProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportProjectController extends Controller
{
    public function index(): Response
    {
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
                'created_at' => $project->created_at?->format('d.m.Y'),
            ]);

        return Inertia::render('Admin/Support/Projects', [
            'projects' => $projects,
            'pageTitle' => 'Проекты поддержки',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
        ]);

        TelegramSupportProject::query()->create([
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', __('Проект создан.'));
    }

    public function update(Request $request, TelegramSupportProject $project): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
        ]);

        $project->update(['name' => trim($validated['name'])]);

        return back()->with('success', __('Проект обновлён.'));
    }

    public function destroy(TelegramSupportProject $project): RedirectResponse
    {
        if ($project->messages()->where('status', 'open')->exists()) {
            return back()->withErrors([
                'project' => __('Сначала закройте или удалите открытые сообщения по проекту.'),
            ]);
        }

        $project->delete();

        return back()->with('success', __('Проект удалён.'));
    }
}
