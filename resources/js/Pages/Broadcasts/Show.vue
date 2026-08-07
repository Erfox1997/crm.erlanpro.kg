<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    campaign: { type: Object, required: true },
    recipients: { type: Array, default: () => [] },
    pageTitle: { type: String, default: '' },
});

const page = usePage();

const dateLocaleTag = computed(() => (locale.value === 'en' ? 'en-US' : 'ru-RU'));

const statusClass = {
    scheduled: 'bg-sky-100 text-sky-800',
    queued: 'bg-amber-100 text-amber-800',
    running: 'bg-indigo-100 text-indigo-800',
    completed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-slate-100 text-slate-600',
    failed: 'bg-rose-100 text-rose-800',
    draft: 'bg-slate-100 text-slate-600',
};

const recipientStatusClass = {
    pending: 'text-amber-700',
    sent: 'text-emerald-700',
    failed: 'text-rose-700',
    skipped: 'text-slate-500',
};

function statusLabel(status) {
    const key = status === 'running' ? 'sending' : status;
    return t(`broadcasts.status.${key}`);
}

function recipientStatusLabel(status) {
    if (status === 'failed') {
        return t('broadcasts.status.failed');
    }
    return t(`broadcasts.recipient.${status}`);
}

const isActive = computed(() =>
    ['queued', 'running', 'scheduled'].includes(props.campaign.status),
);

const progressPercent = computed(() => {
    if (!props.campaign.total_recipients) return 0;
    const done =
        props.campaign.sent_count +
        props.campaign.failed_count +
        props.campaign.skipped_count;
    return Math.min(100, Math.round((done / props.campaign.total_recipients) * 100));
});

let pollTimer = null;

function startPolling() {
    if (pollTimer) {
        return;
    }

    pollTimer = setInterval(() => {
        if (!isActive.value) {
            stopPolling();
            return;
        }

        router.reload({
            only: ['campaign', 'recipients'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

onMounted(() => {
    if (isActive.value) {
        startPolling();
    }
});

watch(isActive, (active) => {
    if (active) {
        startPolling();
    } else {
        stopPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString(dateLocaleTag.value, {
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

function cancelCampaign() {
    if (!confirm(t('broadcasts.cancelConfirm'))) {
        return;
    }

    router.post(route('broadcasts.cancel', props.campaign.id), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${pageTitle || t('broadcasts.title')} #${campaign.id}`" />

    <AuthenticatedLayout>
        <div class="bg-slate-100 py-8 sm:py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="mb-4">
                    <Link
                        :href="route('broadcasts.index')"
                        class="text-sm font-medium text-teal-700 hover:text-teal-800"
                    >
                        {{ t('broadcasts.back') }}
                    </Link>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-lg sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-semibold text-slate-900">
                                    {{ campaign.name || t('broadcasts.defaultName', { id: campaign.id }) }}
                                </h1>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusClass[campaign.status] || 'bg-slate-100 text-slate-600'"
                                >
                                    {{ statusLabel(campaign.status) || campaign.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">
                                {{ campaign.channel_label }} · {{ campaign.audience_label }}
                                <template v-if="campaign.pipeline">
                                    · {{ campaign.pipeline.name }}
                                    <template v-if="campaign.stage"> / {{ campaign.stage.name }}</template>
                                </template>
                            </p>
                            <p v-if="campaign.field_filters?.length" class="mt-1 text-sm text-slate-500">
                                {{ t('broadcasts.filters') }}
                                <span
                                    v-for="(filter, idx) in campaign.field_filters"
                                    :key="idx"
                                >
                                    {{ filter.key }} = {{ filter.value }}<span v-if="idx < campaign.field_filters.length - 1">; </span>
                                </span>
                            </p>
                        </div>

                        <DangerButton
                            v-if="campaign.cancellable"
                            type="button"
                            @click="cancelCampaign"
                        >
                            {{ t('broadcasts.cancelAction') }}
                        </DangerButton>
                    </div>

                    <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700 whitespace-pre-wrap">
                        {{ campaign.body }}
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-slate-100 px-3 py-3">
                            <p class="text-xs text-slate-400">{{ t('common.total') }}</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ campaign.total_recipients }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-100 px-3 py-3">
                            <p class="text-xs text-slate-400">{{ t('broadcasts.sentCol') }}</p>
                            <p class="mt-1 text-lg font-semibold text-emerald-700">{{ campaign.sent_count }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-100 px-3 py-3">
                            <p class="text-xs text-slate-400">{{ t('broadcasts.errors') }}</p>
                            <p class="mt-1 text-lg font-semibold text-rose-700">{{ campaign.failed_count }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-100 px-3 py-3">
                            <p class="text-xs text-slate-400">{{ t('broadcasts.skippedCol') }}</p>
                            <p class="mt-1 text-lg font-semibold text-slate-600">{{ campaign.skipped_count }}</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex justify-between text-xs text-slate-500">
                            <span>{{ t('broadcasts.progress') }}</span>
                            <span>{{ progressPercent }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-teal-500 transition-all"
                                :style="{ width: `${progressPercent}%` }"
                            />
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-400">{{ t('broadcasts.delayBetween') }}</dt>
                            <dd>{{ campaign.delay_seconds }} {{ t('common.secondsShort') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ t('broadcasts.scheduledAt') }}</dt>
                            <dd>{{ formatDate(campaign.scheduled_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ t('broadcasts.startedAt') }}</dt>
                            <dd>{{ formatDate(campaign.started_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ t('broadcasts.finishedAt') }}</dt>
                            <dd>{{ formatDate(campaign.completed_at) }}</dd>
                        </div>
                    </dl>

                    <p
                        v-if="campaign.error_message"
                        class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                    >
                        {{ campaign.error_message }}
                    </p>

                    <p v-if="isActive" class="mt-4 text-xs text-slate-400">
                        {{ t('broadcasts.autoRefreshDots') }}
                    </p>
                </div>

                <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-lg">
                    <div class="border-b border-slate-100 px-5 py-3 sm:px-6">
                        <h2 class="text-sm font-semibold text-slate-800">{{ t('broadcasts.recipients') }}</h2>
                        <p class="text-xs text-slate-400">{{ t('broadcasts.first500') }}</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ t('common.client') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('common.status') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('broadcasts.comment') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('broadcasts.time') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in recipients" :key="row.id">
                                    <td class="px-4 py-3 text-slate-800">
                                        {{ row.client?.name || row.conversation?.participant_name || '—' }}
                                        <span
                                            v-if="row.client?.phone"
                                            class="mt-0.5 block text-xs text-slate-400"
                                        >
                                            {{ row.client.phone }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="recipientStatusClass[row.status]">
                                            {{ recipientStatusLabel(row.status) || row.status }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs px-4 py-3 text-xs text-slate-500">
                                        {{ row.error_message || '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-400">
                                        {{ formatDate(row.sent_at) }}
                                    </td>
                                </tr>
                                <tr v-if="recipients.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                        {{ t('broadcasts.noRecipients') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
