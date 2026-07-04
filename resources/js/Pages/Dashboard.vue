<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const formatMoney = (n) =>
    new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'KGS',
        maximumFractionDigits: 0,
    }).format(n ?? 0);

const cards = [
    {
        key: 'clients_count',
        label: 'Клиенты',
        hint: 'В базе CRM',
        accent: 'from-violet-500 to-indigo-500',
        bg: 'bg-violet-50',
    },
    {
        key: 'deals_count',
        label: 'Все сделки',
        hint: 'За всё время',
        accent: 'from-sky-500 to-cyan-500',
        bg: 'bg-sky-50',
    },
    {
        key: 'open_deals_count',
        label: 'В работе',
        hint: 'Открытые сделки',
        accent: 'from-amber-500 to-orange-500',
        bg: 'bg-amber-50',
    },
    {
        key: 'revenue',
        label: 'Выручка',
        hint: 'Успешные сделки',
        accent: 'from-emerald-500 to-teal-500',
        bg: 'bg-emerald-50',
        money: true,
    },
];
</script>

<template>
    <Head title="Дашборд" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p class="text-sm font-medium text-indigo-600">
                        Добро пожаловать
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                        {{ page.props.auth.user.name }}
                    </h2>
                    <p
                        v-if="company?.name"
                        class="mt-1 text-sm text-slate-500"
                    >
                        {{ company.name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('funnels.index')"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Воронки
                    </Link>
                    <Link
                        :href="route('messenger.index')"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Мессенджер
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="card in cards"
                        :key="card.key"
                        class="group relative overflow-hidden rounded-2xl border border-white/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div
                            class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full opacity-40 blur-2xl transition group-hover:opacity-60"
                            :class="`bg-gradient-to-br ${card.accent}`"
                        />
                        <div class="relative flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-500">
                                    {{ card.label }}
                                </p>
                                <p
                                    class="mt-2 text-3xl font-bold tracking-tight text-slate-900"
                                >
                                    {{
                                        card.money
                                            ? formatMoney(stats[card.key])
                                            : stats[card.key] ?? 0
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ card.hint }}
                                </p>
                            </div>
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg text-white shadow-sm"
                                :class="`bg-gradient-to-br ${card.accent}`"
                            >
                                •
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-8 grid gap-5 lg:grid-cols-2"
                >
                    <div
                        class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm"
                    >
                        <h3 class="text-lg font-semibold text-slate-900">
                            Быстрый старт
                        </h3>
                        <p class="mt-2 text-sm text-slate-500">
                            Подключите каналы и ведите переписку с клиентами в
                            одном окне.
                        </p>
                        <Link
                            :href="route('integrations.index')"
                            class="mt-4 inline-flex rounded-xl bg-gradient-to-r from-indigo-600 to-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
                        >
                            Настроить интеграции
                        </Link>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-900 to-indigo-950 p-6 text-white shadow-sm"
                    >
                        <h3 class="text-lg font-semibold">
                            Сумма в воронке
                        </h3>
                        <p class="mt-2 text-3xl font-bold">
                            {{ formatMoney(stats.pipeline_value) }}
                        </p>
                        <p class="mt-2 text-sm text-slate-300">
                            Открытые сделки, которые ещё можно закрыть.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
