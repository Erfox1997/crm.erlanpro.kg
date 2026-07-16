<?php

namespace App\Services\ChatGpt;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChatGptService
{
    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::ChatGpt->value)
            ->whereNotNull('api_token')
            ->first();
    }

    /**
     * @return array{api_token: string, metadata: array<string, mixed>}
     */
    public function connectFromToken(string $apiToken, ?array $existingMetadata = null): array
    {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new RuntimeException(__('Укажите API-ключ OpenAI.'));
        }

        $response = Http::acceptJson()
            ->timeout((int) config('services.openai.timeout', 30))
            ->withToken($apiToken)
            ->get($this->baseUrl().'/models');

        if ($response->failed()) {
            $message = $response->json('error.message')
                ?? __('OpenAI отклонил API-ключ (HTTP :status).', ['status' => $response->status()]);

            throw new RuntimeException((string) $message);
        }

        $metadata = is_array($existingMetadata) ? $existingMetadata : [];
        $metadata['model'] = $metadata['model']
            ?? (string) config('services.openai.model', 'gpt-4o-mini');
        $metadata['connected_via'] = 'manual';

        return [
            'api_token' => $apiToken,
            'metadata' => $metadata,
        ];
    }

    public function improveMessage(CompanyIntegration $integration, string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException(__('Сначала введите текст сообщения.'));
        }

        $model = (string) ($integration->metadata['model']
            ?? config('services.openai.model', 'gpt-4o-mini'));

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.openai.timeout', 30))
                ->withToken((string) $integration->api_token)
                ->post($this->baseUrl().'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.7,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'Ты помощник менеджера CRM. Улучши черновик ответа клиенту.',
                                'Исправь грамматику и стиль, сохрани смысл и язык оригинала.',
                                'Добавь 1–3 уместных эмодзи, если это уместно для переписки с клиентом.',
                                'Не добавляй приветствие или подпись, если их не было в исходном тексте.',
                                'Верни только готовый текст сообщения, без кавычек и пояснений.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => $text,
                        ],
                    ],
                ]);
        } catch (RequestException $e) {
            throw new RuntimeException(__('Не удалось связаться с OpenAI: :msg', [
                'msg' => $e->getMessage(),
            ]));
        }

        if ($response->failed()) {
            $message = $response->json('error.message')
                ?? __('OpenAI вернул ошибку (HTTP :status).', ['status' => $response->status()]);

            throw new RuntimeException((string) $message);
        }

        $improved = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($improved === '') {
            throw new RuntimeException(__('OpenAI не вернул текст.'));
        }

        return $improved;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
    }
}
