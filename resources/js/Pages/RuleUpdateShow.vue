<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    update: {
        type: Object,
        required: true,
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
    <Head :title="update.title" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <PublicSiteHeader />

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <p class="text-sm text-slate-500">
                <Link href="/updates" class="text-indigo-600 hover:underline">{{
                    t('updates.back')
                }}</Link>
            </p>

            <article class="mt-6">
                <p class="text-sm text-slate-500">
                    {{ update.published_at_label }}
                    <span
                        v-if="update.telegram_sent"
                        class="ms-2 text-emerald-600"
                        >{{ t('updates.alsoPublishedTelegram') }}</span
                    >
                </p>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">
                    {{ update.title }}
                </h1>
                <div
                    class="mt-6 whitespace-pre-line text-sm leading-relaxed text-slate-600"
                >
                    {{ update.body }}
                </div>
            </article>

            <p class="mt-10 text-sm text-slate-500">
                {{ t('updates.fullDocuments') }}
                <Link href="/terms" class="text-indigo-600 hover:underline">{{
                    t('public.footer.terms')
                }}</Link>
                ·
                <Link href="/privacy" class="text-indigo-600 hover:underline">{{
                    t('public.footer.privacy')
                }}</Link>
                ·
                <a
                    :href="newsGroupUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ t('updates.newsGroupLink') }}</a
                >
            </p>
        </main>

        <PublicSiteFooter />
    </div>
</template>
