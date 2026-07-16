<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    campaigns: { type: Array, default: () => [] },
    pipelines: { type: Array, default: () => [] },
    clientFields: { type: Array, default: () => [] },
    channels: { type: Array, default: () => [] },
    pageTitle: { type: String, default: 'Рассылка' },
});

const showCreateModal = ref(false);
const preview = ref({ total: 0, sendable: 0, skipped: 0 });
const previewLoading = ref(false);
const previewError = ref('');

const connectedChannels = computed(() =>
    props.channels.filter((channel) => channel.connected),
);

const form = useForm({
    name: '',
    channel: connectedChannels.value[0]?.value ?? props.channels[0]?.value ?? 'wappi',
    audience_type: 'funnel',
    pipeline_id: props.pipelines[0]?.id ?? null,
    stage_id: props.pipelines[0]?.stages?.[0]?.id ?? null,
    field_filters: [{ key: props.clientFields[0]?.key ?? '', value: '' }],
    body: '',
    delay_seconds: 5,
    scheduled_at: '',
    schedule_mode: 'now',
});

const selectedPipeline = computed(() =>
    props.pipelines.find((pipeline) => pipeline.id === Number(form.pipeline_id)) ?? null,
);

const stages = computed(() => selectedPipeline.value?.stages ?? []);

const statusMeta = {
    scheduled: { label: 'Запланирована', class: 'bg-sky-100 text-sky-800' },
    queued: { label: 'В очереди', class: 'bg-amber-100 text-amber-800' },
    running: { label: 'Отправляется', class: 'bg-indigo-100 text-indigo-800' },
    completed: { label: 'Завершена', class: 'bg-emerald-100 text-emerald-800' },
    cancelled: { label: 'Отменена', class: 'bg-slate-100 text-slate-600' },
    failed: { label: 'Ошибка', class: 'bg-rose-100 text-rose-800' },
    draft: { label: 'Черновик', class: 'bg-slate-100 text-slate-600' },
};

function openCreateModal() {
    form.clearErrors();
    form.reset();
    form.channel = connectedChannels.value[0]?.value ?? props.channels[0]?.value ?? 'wappi';
    form.audience_type = 'funnel';
    form.pipeline_id = props.pipelines[0]?.id ?? null;
    form.stage_id = props.pipelines[0]?.stages?.[0]?.id ?? null;
    form.field_filters = [{ key: props.clientFields[0]?.key ?? '', value: '' }];
    form.delay_seconds = 5;
    form.schedule_mode = 'now';
    form.scheduled_at = '';
    preview.value = { total: 0, sendable: 0, skipped: 0 };
    previewError.value = '';
    showCreateModal.value = true;
    refreshPreview();
}

watch(
    () => form.pipeline_id,
    () => {
        const firstStage = stages.value[0]?.id ?? null;
        if (!stages.value.some((stage) => stage.id === Number(form.stage_id))) {
            form.stage_id = firstStage;
        }
    },
);

watch(
    () => [
        form.channel,
        form.audience_type,
        form.pipeline_id,
        form.stage_id,
        JSON.stringify(form.field_filters),
    ],
    () => {
        if (showCreateModal.value) {
            refreshPreview();
        }
    },
);

async function refreshPreview() {
    previewError.value = '';
    previewLoading.value = true;

    try {
        const xsrf = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );

        const response = await fetch(route('broadcasts.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                channel: form.channel,
                audience_type: form.audience_type,
                pipeline_id: form.pipeline_id,
                stage_id: form.stage_id,
                field_filters: form.audience_type === 'client_fields'
                    ? form.field_filters.filter((row) => row.key && row.value)
                    : [],
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            previewError.value = data.message || 'Не удалось посчитать получателей';
            preview.value = { total: 0, sendable: 0, skipped: 0 };
            return;
        }

        preview.value = data;
    } catch (error) {
        previewError.value = 'Не удалось посчитать получателей';
        preview.value = { total: 0, sendable: 0, skipped: 0 };
    } finally {
        previewLoading.value = false;
    }
}

function addFieldFilter() {
    form.field_filters.push({
        key: props.clientFields[0]?.key ?? '',
        value: '',
    });
}

function removeFieldFilter(index) {
    if (form.field_filters.length === 1) {
        form.field_filters[0] = { key: props.clientFields[0]?.key ?? '', value: '' };
        return;
    }
    form.field_filters.splice(index, 1);
}

function submitCreate() {
    form
        .transform((data) => {
            const payload = {
                name: data.name || null,
                channel: data.channel,
                audience_type: data.audience_type,
                body: data.body,
                delay_seconds: Number(data.delay_seconds) || 5,
                scheduled_at: data.schedule_mode === 'now' ? null : data.scheduled_at || null,
            };

            if (data.audience_type === 'funnel') {
                payload.pipeline_id = data.pipeline_id;
                payload.stage_id = data.stage_id;
                payload.field_filters = [];
            } else {
                payload.pipeline_id = null;
                payload.stage_id = null;
                payload.field_filters = data.field_filters.filter(
                    (row) => row.key && String(row.value).trim() !== '',
                );
            }

            return payload;
        })
        .post(route('broadcasts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showCreateModal.value = false;
            },
        });
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

function progressPercent(campaign) {
    if (!campaign.total_recipients) return 0;
    const done = campaign.sent_count + campaign.failed_count + campaign.skipped_count;
    return Math.min(100, Math.round((done / campaign.total_recipients) * 100));
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <div class="bg-slate-100 py-8 sm:py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">Рассылка</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Отправка сообщений по воронке или по данным клиента — в фоне, с паузами и по расписанию.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-slate-800 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
                        @click="openCreateModal"
                    >
                        Новая рассылка
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-lg">
                    <div
                        v-if="campaigns.length === 0"
                        class="px-6 py-16 text-center text-sm text-slate-400"
                    >
                        Рассылок пока нет. Создайте первую — страница не будет зависать даже на тысячах получателей.
                    </div>

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="campaign in campaigns"
                            :key="campaign.id"
                            class="px-5 py-4 transition hover:bg-slate-50/80 sm:px-6"
                        >
                            <Link
                                :href="route('broadcasts.show', campaign.id)"
                                class="block"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ campaign.name || `Рассылка #${campaign.id}` }}
                                            </p>
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                                                :class="statusMeta[campaign.status]?.class || 'bg-slate-100 text-slate-600'"
                                            >
                                                {{ statusMeta[campaign.status]?.label || campaign.status }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ campaign.channel_label }} · {{ campaign.audience_label }}
                                            <template v-if="campaign.pipeline">
                                                · {{ campaign.pipeline.name }}
                                                <template v-if="campaign.stage"> / {{ campaign.stage.name }}</template>
                                            </template>
                                            <template v-if="campaign.field_filters?.length">
                                                ·
                                                <span
                                                    v-for="(filter, idx) in campaign.field_filters"
                                                    :key="idx"
                                                >
                                                    {{ filter.key }}={{ filter.value }}<span v-if="idx < campaign.field_filters.length - 1">, </span>
                                                </span>
                                            </template>
                                        </p>
                                        <p class="mt-2 line-clamp-2 text-sm text-slate-600">
                                            {{ campaign.body }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-left sm:text-right">
                                        <p class="text-sm font-medium text-slate-800">
                                            {{ campaign.sent_count }}/{{ campaign.total_recipients }}
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            пауза {{ campaign.delay_seconds }} с
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ formatDate(campaign.scheduled_at || campaign.created_at) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-teal-500 transition-all"
                                        :style="{ width: `${progressPercent(campaign)}%` }"
                                    />
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="2xl" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Новая рассылка</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Сообщения уходят в фоне через очередь — браузер не ждёт отправку всем клиентам.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel for="broadcast-name" value="Название (необязательно)" />
                        <TextInput
                            id="broadcast-name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Например: Акция март — женщины"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel value="Канал" />
                        <select
                            v-model="form.channel"
                            class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        >
                            <option
                                v-for="channel in channels"
                                :key="channel.value"
                                :value="channel.value"
                                :disabled="!channel.connected"
                            >
                                {{ channel.label }}{{ channel.connected ? '' : ' (не подключён)' }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.channel" />
                    </div>

                    <div>
                        <InputLabel value="Тип аудитории" />
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-3 text-sm"
                                :class="form.audience_type === 'funnel' ? 'border-teal-400 bg-teal-50/60' : 'border-slate-200'"
                            >
                                <input v-model="form.audience_type" type="radio" value="funnel" class="mt-0.5" />
                                <span>
                                    <span class="font-medium text-slate-800">По воронке</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Клиенты на выбранном этапе</span>
                                </span>
                            </label>
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-3 text-sm"
                                :class="form.audience_type === 'client_fields' ? 'border-teal-400 bg-teal-50/60' : 'border-slate-200'"
                            >
                                <input v-model="form.audience_type" type="radio" value="client_fields" class="mt-0.5" />
                                <span>
                                    <span class="font-medium text-slate-800">По данным клиента</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Фильтр по полям (пол и т.д.)</span>
                                </span>
                            </label>
                        </div>
                        <InputError class="mt-1" :message="form.errors.audience_type" />
                    </div>

                    <div v-if="form.audience_type === 'funnel'" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Воронка" />
                            <select
                                v-model="form.pipeline_id"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            >
                                <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">
                                    {{ pipeline.name }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.pipeline_id" />
                        </div>
                        <div>
                            <InputLabel value="Этап" />
                            <select
                                v-model="form.stage_id"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            >
                                <option v-for="stage in stages" :key="stage.id" :value="stage.id">
                                    {{ stage.name }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.stage_id" />
                        </div>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-if="clientFields.length === 0"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                        >
                            Сначала создайте поля в разделе «Данные клиента».
                        </div>
                        <div
                            v-for="(row, index) in form.field_filters"
                            :key="index"
                            class="grid gap-2 rounded-lg border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <div>
                                <InputLabel value="Поле" />
                                <select
                                    v-model="row.key"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                >
                                    <option
                                        v-for="field in clientFields"
                                        :key="field.key"
                                        :value="field.key"
                                    >
                                        {{ field.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Значение" />
                                <select
                                    v-if="(clientFields.find((f) => f.key === row.key)?.options || []).length"
                                    v-model="row.value"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                >
                                    <option value="">Выберите…</option>
                                    <option
                                        v-for="opt in clientFields.find((f) => f.key === row.key)?.options || []"
                                        :key="opt"
                                        :value="opt"
                                    >
                                        {{ opt }}
                                    </option>
                                </select>
                                <TextInput
                                    v-else
                                    v-model="row.value"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Например: женский"
                                />
                            </div>
                            <div class="flex items-end">
                                <button
                                    type="button"
                                    class="mb-0.5 rounded-md px-2 py-2 text-xs text-rose-600 hover:bg-rose-50"
                                    @click="removeFieldFilter(index)"
                                >
                                    Убрать
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-sm font-medium text-teal-700 hover:text-teal-800"
                            @click="addFieldFilter"
                        >
                            + Ещё фильтр
                        </button>
                        <InputError :message="form.errors.field_filters" />
                    </div>

                    <div>
                        <InputLabel for="broadcast-body" value="Текст сообщения" />
                        <textarea
                            id="broadcast-body"
                            v-model="form.body"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            placeholder="Текст рассылки…"
                        />
                        <InputError class="mt-1" :message="form.errors.body" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="delay" value="Пауза между сообщениями (сек)" />
                            <TextInput
                                id="delay"
                                v-model="form.delay_seconds"
                                type="number"
                                min="1"
                                max="120"
                                class="mt-1 block w-full"
                            />
                            <p class="mt-1 text-xs text-slate-400">
                                Рекомендуем 3–10 сек, чтобы снизить риск блокировки аккаунта.
                            </p>
                            <InputError class="mt-1" :message="form.errors.delay_seconds" />
                        </div>
                        <div>
                            <InputLabel value="Когда отправить" />
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="form.schedule_mode" type="radio" value="now" />
                                    Сейчас
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="form.schedule_mode" type="radio" value="later" />
                                    Запланировать
                                </label>
                                <TextInput
                                    v-if="form.schedule_mode === 'later'"
                                    v-model="form.scheduled_at"
                                    type="datetime-local"
                                    class="block w-full"
                                />
                            </div>
                            <InputError class="mt-1" :message="form.errors.scheduled_at" />
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <template v-if="previewLoading">
                            Считаем получателей…
                        </template>
                        <template v-else-if="previewError">
                            <span class="text-rose-600">{{ previewError }}</span>
                        </template>
                        <template v-else>
                            Получателей: <strong>{{ preview.total }}</strong>
                            · к отправке: <strong>{{ preview.sendable }}</strong>
                            · без диалога: <strong>{{ preview.skipped }}</strong>
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" @click="showCreateModal = false">
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton :disabled="form.processing || preview.sendable < 1">
                            {{ form.schedule_mode === 'now' ? 'Запустить' : 'Запланировать' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
