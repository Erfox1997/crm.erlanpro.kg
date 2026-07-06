<?php

namespace App\Http\Controllers;

use App\Models\ClientFieldDefinition;
use App\Services\Client\ClientFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientFieldDefinitionController extends Controller
{
    public function __construct(
        private ClientFieldService $clientFields,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $fields = ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ClientFieldDefinition $field) => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $field->options ?? [],
                'is_required' => $field->is_required,
                'sort_order' => $field->sort_order,
            ]);

        return Inertia::render('ClientFields/Index', [
            'fields' => $fields,
            'pageTitle' => 'Данные клиента',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'label' => 'required|string|max:120',
            'key' => 'nullable|string|max:64',
            'type' => 'required|in:text,textarea,number,phone,email,date,select',
            'options' => 'nullable|array',
            'options.*' => 'string|max:120',
            'is_required' => 'boolean',
        ]);

        $key = ClientFieldService::normalizeKey($validated['label'], $validated['key'] ?? null);

        if (ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->exists()) {
            return back()->withErrors(['key' => __('Поле с таким ключом уже существует.')]);
        }

        $sortOrder = (int) ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->max('sort_order') + 1;

        ClientFieldDefinition::query()->create([
            'company_id' => $companyId,
            'key' => $key,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['type'] === 'select' ? array_values(array_filter($validated['options'] ?? [])) : null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'sort_order' => $sortOrder,
        ]);

        return back()->with('success', __('Поле добавлено.'));
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'fields' => 'required|array|min:1|max:30',
            'fields.*.label' => 'required|string|max:120',
            'fields.*.key' => 'nullable|string|max:64',
            'fields.*.type' => 'required|in:text,textarea,number,phone,email,date,select',
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*' => 'string|max:120',
            'fields.*.is_required' => 'boolean',
        ]);

        $sortOrder = (int) ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->max('sort_order');

        $keysInBatch = [];

        foreach ($validated['fields'] as $index => $field) {
            $key = ClientFieldService::normalizeKey($field['label'], $field['key'] ?? null);

            if (in_array($key, $keysInBatch, true)) {
                return back()->withErrors([
                    "fields.{$index}.key" => __('Ключ «:key» повторяется в форме.', ['key' => $key]),
                ]);
            }

            if (ClientFieldDefinition::query()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->exists()) {
                return back()->withErrors([
                    "fields.{$index}.key" => __('Поле с ключом «:key» уже существует.', ['key' => $key]),
                ]);
            }

            $keysInBatch[] = $key;
            $sortOrder++;

            ClientFieldDefinition::query()->create([
                'company_id' => $companyId,
                'key' => $key,
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['type'] === 'select'
                    ? array_values(array_filter($field['options'] ?? []))
                    : null,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'sort_order' => $sortOrder,
            ]);
        }

        $count = count($validated['fields']);

        return back()->with('success', trans_choice(
            '{1} Поле добавлено.|[2,*] Добавлено полей: :count.',
            $count,
            ['count' => $count],
        ));
    }

    public function update(Request $request, ClientFieldDefinition $clientFieldDefinition): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($clientFieldDefinition->company_id === $companyId, 403);

        $validated = $request->validate([
            'label' => 'required|string|max:120',
            'key' => 'required|string|max:64',
            'type' => 'required|in:text,textarea,number,phone,email,date,select',
            'options' => 'nullable|array',
            'options.*' => 'string|max:120',
            'is_required' => 'boolean',
        ]);

        $key = ClientFieldService::normalizeKey($validated['label'], $validated['key']);

        abort_if(
            ClientFieldDefinition::query()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->whereKeyNot($clientFieldDefinition->id)
                ->exists(),
            422,
            __('Поле с таким ключом уже существует.'),
        );

        $clientFieldDefinition->update([
            'key' => $key,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['type'] === 'select' ? array_values(array_filter($validated['options'] ?? [])) : null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
        ]);

        return back()->with('success', __('Поле обновлено.'));
    }

    public function destroy(Request $request, ClientFieldDefinition $clientFieldDefinition): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($clientFieldDefinition->company_id === $companyId, 403);

        $clientFieldDefinition->delete();

        return back()->with('success', __('Поле удалено.'));
    }
}
