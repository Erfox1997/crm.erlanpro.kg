<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
});

const formatMoney = (n) =>
    new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'KGS',
        maximumFractionDigits: 0,
    }).format(n);
</script>

<template>
    <Head title="Дашборд" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Дашборд
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            Клиенты
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.clients_count }}
                        </p>
                    </div>
                    <div
                        class="overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            Все сделки
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.deals_count }}
                        </p>
                    </div>
                    <div
                        class="overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            В работе
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ stats.open_deals_count }}
                        </p>
                    </div>
                    <div
                        class="overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            Выручка (успешные)
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ formatMoney(stats.revenue) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
