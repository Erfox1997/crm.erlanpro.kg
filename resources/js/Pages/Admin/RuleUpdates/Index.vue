<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    updates: {
        type: Array,
        default: () => [],
    },
    telegramConfigured: {
        type: Boolean,
        default: false,
    },
    announcementChatId: {
        type: String,
        default: null,
    },
    newsBotUsername: {
        type: String,
        default: null,
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('admin.ruleUpdates.title')" />

    <AdminLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ t('admin.ruleUpdates.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('admin.ruleUpdates.subtitleBefore') }}
                        <Link
                            href="/updates"
                            class="font-medium text-indigo-600 hover:text-indigo-500"
                            target="_blank"
                        >
                            /updates
                        </Link>
                        {{ t('admin.ruleUpdates.subtitleAfter') }}
                    </p>
                </div>
                <Link :href="route('admin.rule-updates.create')">
                    <PrimaryButton type="button">
                        {{ t('admin.ruleUpdates.new') }}
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div
            class="mb-6 rounded-xl border px-4 py-3 text-sm"
            :class="
                telegramConfigured
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-amber-200 bg-amber-50 text-amber-900'
            "
        >
            <template v-if="telegramConfigured">
                {{ t('admin.ruleUpdates.botOk') }}
                <span v-if="newsBotUsername">@{{ newsBotUsername }}</span>
                —
                <code class="rounded bg-white/70 px-1">{{
                    announcementChatId
                }}</code>
            </template>
            <template v-else>
                {{ t('admin.ruleUpdates.botMissing') }}
                <code class="rounded bg-white/70 px-1">.env</code>:
                <code class="rounded bg-white/70 px-1"
                    >TELEGRAM_NEWS_BOT_TOKEN</code
                >
                {{ t('admin.ruleUpdates.botMissingAnd') }}
                <code class="rounded bg-white/70 px-1"
                    >TELEGRAM_ANNOUNCEMENT_CHAT_ID</code
                >
                {{ t('admin.ruleUpdates.botMissingHint') }}
            </template>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ t('admin.ruleUpdates.date') }}</th>
                        <th class="px-4 py-3">{{ t('admin.ruleUpdates.heading') }}</th>
                        <th class="px-4 py-3">Telegram</th>
                        <th class="px-4 py-3">{{ t('admin.ruleUpdates.author') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="updates.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-slate-500"
                        >
                            {{ t('admin.ruleUpdates.empty') }}
                        </td>
                    </tr>
                    <tr v-for="item in updates" :key="item.id">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                            {{ item.published_at_label }}
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900">
                            {{ item.title }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="item.telegram_sent"
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
                            >
                                {{ t('admin.ruleUpdates.sent') }}
                            </span>
                            <span
                                v-else
                                class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700"
                            >
                                {{ t('admin.ruleUpdates.siteOnly') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ item.publisher_name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a
                                :href="item.public_url"
                                target="_blank"
                                class="text-indigo-600 hover:underline"
                            >
                                {{ t('common.open') }}
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
