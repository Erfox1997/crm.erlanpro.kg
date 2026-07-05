<?php

namespace App\Http\Controllers;

use App\Models\MessengerQuickReply;
use App\Services\Messenger\MessengerQuickReplyImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessengerQuickReplyController extends Controller
{
    public function __construct(
        private MessengerQuickReplyImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $quickReplies = MessengerQuickReply::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (MessengerQuickReply $item) => $this->mapForFrontend($item));

        return Inertia::render('Messenger/QuickReplies', [
            'quickReplies' => $quickReplies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $type = (string) $request->input('type', 'text');

        $validated = $request->validate([
            'type' => 'required|in:text,audio,image',
            'title' => 'required|string|max:120',
            'body' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:16384',
        ]);

        if ($type === 'text' && trim((string) ($validated['body'] ?? '')) === '') {
            return back()->withErrors(['body' => __('Укажите текст шаблона.')]);
        }

        if (in_array($type, ['audio', 'image'], true) && ! $request->hasFile('attachment')) {
            return back()->withErrors(['attachment' => __('Загрузите файл для шаблона.')]);
        }

        $sortOrder = (int) MessengerQuickReply::query()
            ->where('company_id', $companyId)
            ->max('sort_order') + 1;

        $attachmentMeta = in_array($type, ['audio', 'image'], true)
            ? $this->storeAttachment($request->file('attachment'), $companyId, $type)
            : [
                'attachment_path' => null,
                'attachment_mime' => null,
                'attachment_name' => null,
            ];

        MessengerQuickReply::query()->create([
            'company_id' => $companyId,
            'type' => $type,
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'sort_order' => $sortOrder,
            ...$attachmentMeta,
        ]);

        return back()->with('success', __('Быстрый ответ добавлен.'));
    }

    public function update(Request $request, MessengerQuickReply $quickReply): RedirectResponse
    {
        abort_unless($quickReply->company_id === (int) $request->user()->company_id, 403);

        $type = (string) $request->input('type', $quickReply->type);

        $validated = $request->validate([
            'type' => 'required|in:text,audio,image',
            'title' => 'required|string|max:120',
            'body' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:16384',
        ]);

        if ($type === 'text' && trim((string) ($validated['body'] ?? '')) === '') {
            return back()->withErrors(['body' => __('Укажите текст шаблона.')]);
        }

        if (in_array($type, ['audio', 'image'], true)
            && ! $request->hasFile('attachment')
            && ! $quickReply->attachment_path) {
            return back()->withErrors(['attachment' => __('Загрузите файл для шаблона.')]);
        }

        $attachmentMeta = [];
        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($quickReply);
            $attachmentMeta = $this->storeAttachment($request->file('attachment'), $quickReply->company_id, $type);
        } elseif ($type === 'text') {
            $this->deleteAttachment($quickReply);
            $attachmentMeta = [
                'attachment_path' => null,
                'attachment_mime' => null,
                'attachment_name' => null,
            ];
        }

        $quickReply->update([
            'type' => $type,
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            ...$attachmentMeta,
        ]);

        return back()->with('success', __('Быстрый ответ обновлён.'));
    }

    public function destroy(Request $request, MessengerQuickReply $quickReply): RedirectResponse
    {
        abort_unless($quickReply->company_id === (int) $request->user()->company_id, 403);

        $this->deleteAttachment($quickReply);
        $quickReply->delete();

        return back()->with('success', __('Быстрый ответ удалён.'));
    }

    public function import(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        $path = $validated['file']->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => __('Не удалось прочитать файл.')]);
        }

        try {
            $result = $this->importService->importFromSpreadsheet($path, $companyId);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if ($result['imported'] === 0) {
            return back()->withErrors(['file' => __('Не найдено строк для импорта. Проверьте формат: колонка A — название, B — текст.')]);
        }

        return back()->with(
            'success',
            __('Импортировано шаблонов: :count', ['count' => $result['imported']]),
        );
    }

    public function sample(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $path = tempnam(sys_get_temp_dir(), 'qr_sample_');
            if ($path === false) {
                throw new \RuntimeException(__('Не удалось создать файл.'));
            }

            try {
                $this->importService->writeSampleToPath($path);
                echo (string) file_get_contents($path);
            } finally {
                @unlink($path);
            }
        }, 'quick-replies-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function attachment(Request $request, MessengerQuickReply $quickReply): BinaryFileResponse
    {
        abort_unless($quickReply->company_id === (int) $request->user()->company_id, 403);
        abort_unless($quickReply->attachment_path, 404);

        $path = Storage::disk('local')->path($quickReply->attachment_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $quickReply->attachment_mime ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * @return array{id: int, title: string, type: string, body: ?string, attachment_url: ?string, attachment_mime: ?string, attachment_name: ?string}
     */
    protected function mapForFrontend(MessengerQuickReply $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'type' => $item->type,
            'body' => $item->body,
            'attachment_url' => $item->attachment_path
                ? route('messenger.quick-replies.attachment', $item)
                : null,
            'attachment_mime' => $item->attachment_mime,
            'attachment_name' => $item->attachment_name,
        ];
    }

    /**
     * @return array{attachment_path: string, attachment_mime: string, attachment_name: string}
     */
    protected function storeAttachment(?UploadedFile $file, int $companyId, string $type): array
    {
        if (! $file) {
            return [
                'attachment_path' => '',
                'attachment_mime' => '',
                'attachment_name' => '',
            ];
        }

        $extension = $file->getClientOriginalExtension() ?: ($type === 'audio' ? 'm4a' : 'jpg');
        $filename = Str::uuid().'.'.strtolower($extension);
        $directory = "quick-replies/{$companyId}";

        $path = $file->storeAs($directory, $filename, 'local');

        return [
            'attachment_path' => $path,
            'attachment_mime' => $file->getMimeType() ?? 'application/octet-stream',
            'attachment_name' => $file->getClientOriginalName() ?: $filename,
        ];
    }

    protected function deleteAttachment(MessengerQuickReply $quickReply): void
    {
        if ($quickReply->attachment_path) {
            Storage::disk('local')->delete($quickReply->attachment_path);
        }
    }
}
