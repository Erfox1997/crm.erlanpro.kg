<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    updates: {
        type: Array,
        default: () => [],
    },
    appName: {
        type: String,
        default: 'CRM',
    },
});
</script>

<template>
    <Head title="Обновления правил" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <PublicSiteHeader />

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl font-bold text-slate-900">
                Обновления правил
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Здесь публикуются изменения
                <Link href="/terms" class="text-indigo-600 hover:underline"
                    >Пользовательского соглашения</Link
                >
                и
                <Link href="/privacy" class="text-indigo-600 hover:underline"
                    >Политики конфиденциальности</Link
                >. Та же запись одновременно уходит в новостной канал Telegram —
                дата сообщения в Telegram служит подтверждением момента
                публикации.
            </p>

            <div class="mt-8 space-y-4">
                <p
                    v-if="updates.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500"
                >
                    Пока нет опубликованных изменений.
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
                            >· также в Telegram</span
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
