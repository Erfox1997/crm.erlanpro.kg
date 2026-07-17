<?php

namespace App\Http\Controllers;

use App\Models\PlatformRuleUpdate;
use Inertia\Inertia;
use Inertia\Response;

class RuleUpdateController extends Controller
{
    public function index(): Response
    {
        $updates = PlatformRuleUpdate::query()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (PlatformRuleUpdate $update) => $this->toPublicArray($update));

        return Inertia::render('RuleUpdates', [
            'updates' => $updates,
            'appName' => config('app.name'),
        ]);
    }

    public function show(PlatformRuleUpdate $ruleUpdate): Response
    {
        abort_unless($ruleUpdate->published_at !== null, 404);

        return Inertia::render('RuleUpdateShow', [
            'update' => $this->toPublicArray($ruleUpdate),
            'appName' => config('app.name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicArray(PlatformRuleUpdate $update): array
    {
        return [
            'id' => $update->id,
            'title' => $update->title,
            'body' => $update->body,
            'published_at' => $update->published_at?->toIso8601String(),
            'published_at_label' => $update->published_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'telegram_sent' => $update->wasSentToTelegram(),
        ];
    }
}
