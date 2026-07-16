<?php

namespace App\Services\ChatGpt;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChatGptService
{
    /**
     * Preferred chat models, first available wins.
     *
     * @var list<string>
     */
    public const PREFERRED_MODELS = [
        'gpt-4.1-mini',
        'gpt-4.1',
        'gpt-4o-mini',
        'gpt-4o',
        'gpt-4-turbo',
        'o4-mini',
        'gpt-3.5-turbo',
    ];

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
    public function connectFromToken(
        string $apiToken,
        ?array $existingMetadata = null,
        ?string $preferredModel = null,
    ): array {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new RuntimeException(__('Укажите API-ключ OpenAI.'));
        }

        $available = $this->listChatModelIds($apiToken);
        $model = $this->resolveModel(
            $available,
            $preferredModel,
            is_array($existingMetadata) ? ($existingMetadata['model'] ?? null) : null,
        );

        $metadata = is_array($existingMetadata) ? $existingMetadata : [];
        $metadata['model'] = $model;
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
            ?? config('services.openai.model', 'gpt-4.1-mini'));

        try {
            return $this->complete($integration, $model, $text);
        } catch (RuntimeException $e) {
            if (! $this->isModelAccessError($e->getMessage())) {
                throw $e;
            }
        }

        $available = array_values(array_filter(
            $this->listChatModelIds((string) $integration->api_token),
            fn (string $id) => $id !== $model,
        ));

        if ($available === []) {
            throw new RuntimeException(__('Проект OpenAI не имеет доступа к модели :model. В Limits проекта разрешите нужную модель или выберите другую в «Интеграции».', [
                'model' => $model,
            ]));
        }

        $lastError = null;
        foreach ([...self::PREFERRED_MODELS, ...$available] as $candidate) {
            if (! in_array($candidate, $available, true)) {
                continue;
            }

            try {
                $improved = $this->complete($integration, $candidate, $text);

                $metadata = $integration->metadata ?? [];
                $metadata['model'] = $candidate;
                $integration->forceFill(['metadata' => $metadata])->save();

                return $improved;
            } catch (RuntimeException $e) {
                $lastError = $e;
                if (! $this->isModelAccessError($e->getMessage())) {
                    throw $e;
                }
                $available = array_values(array_filter($available, fn (string $id) => $id !== $candidate));
            }
        }

        throw $lastError ?? new RuntimeException(__('Не удалось подобрать доступную модель OpenAI.'));
    }

    /**
     * @return list<string>
     */
    public function preferredModels(): array
    {
        return self::PREFERRED_MODELS;
    }

    protected function complete(CompanyIntegration $integration, string $model, string $text): string
    {
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

    /**
     * @return list<string>
     */
    protected function listChatModelIds(string $apiToken): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('services.openai.timeout', 30))
            ->withToken($apiToken)
            ->get($this->baseUrl().'/models');

        if ($response->failed()) {
            $message = $response->json('error.message')
                ?? __('OpenAI отклонил API-ключ (HTTP :status).', ['status' => $response->status()]);

            throw new RuntimeException((string) $message);
        }

        $ids = collect($response->json('data') ?? [])
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->filter(fn (string $id) => $this->looksLikeChatModel($id))
            ->values()
            ->all();

        if ($ids === []) {
            throw new RuntimeException(__('У этого API-ключа нет доступных chat-моделей. Проверьте Limits проекта в OpenAI.'));
        }

        return $ids;
    }

    /**
     * @param  list<string>  $available
     */
    protected function resolveModel(array $available, ?string ...$candidates): string
    {
        $availableLookup = array_fill_keys($available, true);

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? trim($candidate) : '';
            if ($candidate !== '' && isset($availableLookup[$candidate])) {
                return $candidate;
            }
        }

        foreach (self::PREFERRED_MODELS as $preferred) {
            if (isset($availableLookup[$preferred])) {
                return $preferred;
            }
        }

        return $available[0];
    }

    protected function looksLikeChatModel(string $id): bool
    {
        $id = strtolower($id);

        if (str_contains($id, 'embedding')
            || str_contains($id, 'whisper')
            || str_contains($id, 'tts')
            || str_contains($id, 'dall-e')
            || str_contains($id, 'realtime')
            || str_contains($id, 'moderation')
            || str_contains($id, 'transcribe')
        ) {
            return false;
        }

        return str_starts_with($id, 'gpt-')
            || str_starts_with($id, 'o1')
            || str_starts_with($id, 'o3')
            || str_starts_with($id, 'o4')
            || str_starts_with($id, 'chatgpt-');
    }

    protected function isModelAccessError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'does not have access to model')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'invalid model');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
    }
}
