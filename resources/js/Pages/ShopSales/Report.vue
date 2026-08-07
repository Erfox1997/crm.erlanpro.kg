<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    month: { type: String, required: true },
    managers: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    pageTitle: { type: String, default: null },
});

const title = computed(() => props.pageTitle || t('shopSales.reportTitle'));

function onMonthChange(event) {
    router.get(route('shop-sales.report'), { month: event.target.value }, {
        preserveState: true,
        replace: true,
    });
}

function money(value) {
    return Number(value || 0).toLocaleString('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined || Number.isNaN(Number(seconds))) {
        return '—';
    }

    const total = Math.max(0, Math.round(Number(seconds)));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    if (hours > 0) {
        return t('shopSales.hoursMinutes', { h: hours, m: minutes });
    }

    if (minutes > 0) {
        return t('shopSales.minutesSeconds', { m: minutes, s: secs });
    }

    return t('shopSales.secondsOnly', { s: secs });
}
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-800">{{ title }}</h2>
                <Link
                    :href="route('shop-sales.index')"
                    class="text-sm font-medium text-amber-700 hover:text-amber-800"
                >
                    {{ t('shopSales.backHistory') }}
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <label class="block text-sm font-medium text-slate-700">{{ t('shopSales.month') }}</label>
                    <input
                        type="month"
                        class="mt-1 rounded-md border-slate-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        :value="month"
                        @change="onMonthChange"
                    >
                    <p class="mt-2 text-xs text-slate-500">
                        {{ t('shopSales.avgReplyFull') }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">{{ t('shopSales.salesCount') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ totals.sales_count }}</p>
                    </div>
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">{{ t('shopSales.sum') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ money(totals.total_amount) }}</p>
                    </div>
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">{{ t('shopSales.avgReply') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            {{ formatDuration(totals.avg_response_seconds) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('shopSales.manager') }}</th>
                                <th class="px-4 py-3">{{ t('shopSales.salesCount') }}</th>
                                <th class="px-4 py-3">{{ t('shopSales.sum') }}</th>
                                <th class="px-4 py-3">{{ t('shopSales.avgReply') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in managers" :key="row.user_id">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                                <td class="px-4 py-3">{{ row.sales_count }}</td>
                                <td class="px-4 py-3 font-medium">{{ money(row.total_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span>{{ formatDuration(row.avg_response_seconds) }}</span>
                                    <span
                                        v-if="row.response_count"
                                        class="ml-1 text-xs text-slate-400"
                                    >
                                        ({{ row.response_count }})
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="managers.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                    {{ t('shopSales.noMonthData') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
