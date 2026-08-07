<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    updates: {
        type: Array,
        default: () => [],
    },
    appName: {
        type: String,
        default: 'CRM',
    },
    newsGroupUrl: {
        type: String,
        default: 'https://t.me/+XAExfDN7j8Q1NWRi',
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('updates.title')" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <PublicSiteHeader />

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl font-bold text-slate-900">
                {{ t('updates.title') }}
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ t('updates.subtitleBefore') }}
                <Link href="/terms" class="text-indigo-600 hover:underline">{{
                    t('terms.title')
                }}</Link>
                {{ t('updates.subtitleAnd') }}
                <Link href="/privacy" class="text-indigo-600 hover:underline">{{
                    t('privacy.title')
                }}</Link
                >. {{ t('updates.subtitleTelegram') }}
                <a
                    :href="newsGroupUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ t('updates.newsGroup') }}</a
                >
                {{ t('updates.subtitleProof') }}
                <a
                    :href="newsGroupUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ t('updates.joinLink') }}</a
                >.
            </p>

            <div class="mt-8 space-y-4">
                <p
                    v-if="updates.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500"
                >
                    {{ t('updates.empty') }}
                </p>

                <article
                    v-for="item in updates"
                    :key="item.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs text-slate-500">
                        {{ item.published_at_label }}
                        <span
                            v-if="item.telegram_sent"
                            class="ms-2 text-emerald-600"
                            >{{ t('updates.alsoTelegram') }}</span
                        >
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        <Link
                            :href="`/updates/${item.id}`"
                            class="hover:text-indigo-600"
                        >
                            {{ item.title }}
                        </Link>
                    </h2>
                    <p
                        class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600"
                    >
                        {{ item.body }}
                    </p>
                </article>
            </div>
        </main>

        <PublicSiteFooter />
    </div>
</template>
