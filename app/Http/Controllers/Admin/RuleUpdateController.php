<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformRuleUpdate;
use App\Services\Telegram\NewsTelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RuleUpdateController extends Controller
{
    public function index(NewsTelegramBotService $newsBot): Response
    {
        $updates = PlatformRuleUpdate::query()
            ->with('publisher:id,name,email')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (PlatformRuleUpdate $update) => $this->toAdminArray($update));

        return Inertia::render('Admin/RuleUpdates/Index', [
            'updates' => $updates,
            'telegramConfigured' => $newsBot->isConfigured(),
            'announcementChatId' => $newsBot->announcementChatId() ?: null,
            'newsBotUsername' => $newsBot->botUsername() ?: null,
            'pageTitle' => 'Обновления правил',
        ]);
    }

    public function create(NewsTelegramBotService $newsBot): Response
    {
        return Inertia::render('Admin/RuleUpdates/Create', [
            'telegramConfigured' => $newsBot->isConfigured(),
            'announcementChatId' => $newsBot->announcementChatId() ?: null,
            'newsBotUsername' => $newsBot->botUsername() ?: null,
            'pageTitle' => 'Новое обновление правил',
        ]);
    }

    public function store(Request $request, NewsTelegramBotService $newsBot): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:8000',
        ]);

        $update = PlatformRuleUpdate::query()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'published_at' => now(),
            'published_by' => $request->user()?->id,
        ]);

        $siteUrl = rtrim((string) config('app.url'), '/').'/updates/'.$update->id;
        $telegramText = $this->formatTelegramMessage($update, $siteUrl);

        if (! $newsBot->isConfigured()) {
            return redirect()
                ->route('admin.rule-updates.index')
                ->with(
                    'success',
                    __('Обновление сохранено на сайте, но news-бот не настроен (TELEGRAM_NEWS_BOT_TOKEN / TELEGRAM_ANNOUNCEMENT_CHAT_ID).'),
                );
        }

        try {
            $messageId = $newsBot->sendAnnouncement($telegramText);
            $update->update([
                'telegram_chat_id' => $newsBot->announcementChatId(),
                'telegram_message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.rule-updates.index')
                ->with(
                    'success',
                    __('Обновление сохранено на сайте, но в Telegram не отправилось: :error', [
                        'error' => $e->getMessage(),
                    ]),
                );
        }

        return redirect()
            ->route('admin.rule-updates.index')
            ->with('success', __('Обновление опубликовано на сайте и в Telegram.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function toAdminArray(PlatformRuleUpdate $update): array
    {
        return [
            'id' => $update->id,
            'title' => $update->title,
            'body' => $update->body,
            'published_at' => $update->published_at?->toIso8601String(),
            'published_at_label' => $update->published_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'telegram_sent' => $update->wasSentToTelegram(),
            'telegram_message_id' => $update->telegram_message_id,
            'publisher_name' => $update->publisher?->name,
            'public_url' => url('/updates/'.$update->id),
        ];
    }

    private function formatTelegramMessage(PlatformRuleUpdate $update, string $siteUrl): string
    {
        $text = "📋 Изменение правил CRM ErlanPro\n\n"
            ."{$update->title}\n\n"
            .$update->body
            ."\n\n"
            ."🔗 На сайте: {$siteUrl}\n"
            .'Дата публикации зафиксирована этим сообщением Telegram.';

        if (mb_strlen($text) <= 4000) {
            return $text;
        }

        $truncatedBody = Str::limit($update->body, 2800, '…');

        return "📋 Изменение правил CRM ErlanPro\n\n"
            ."{$update->title}\n\n"
            .$truncatedBody
            ."\n\n"
            ."🔗 Полный текст: {$siteUrl}\n"
            .'Дата публикации зафиксирована этим сообщением Telegram.';
    }
}
