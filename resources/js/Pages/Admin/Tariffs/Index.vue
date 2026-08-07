<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { localeTag } from '@/i18n';

defineProps({
    tariffs: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const { t, locale } = useI18n();

function formatPrice(value, original = null) {
    const tag = localeTag(locale.value);
    if (original && original > value) {
        return `${Number(original).toLocaleString(tag)} → ${Number(value).toLocaleString(tag)}`;
    }

    return Number(value).toLocaleString(tag);
}
</script>

<template>
    <Head :title="t('admin.tariffsPlans')" />

    <AdminLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ t('admin.tariffsPlans') }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('admin.tariffs.planLabel') }}
                    </p>
                </div>
                <Link
                    :href="route('admin.tariffs.create')"
                    class="inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    {{ t('admin.tariffs.create') }}
                </Link>
            </div>
        </template>

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('admin.tariffs.name') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('admin.tariffs.price') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('admin.tariffs.durationDays') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('admin.tariffs.employees') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('admin.tariffs.messages') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('admin.tariffs.free') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            {{ t('common.status') }}
                        </th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="tariff in tariffs" :key="tariff.id">
                        <td class="px-4 py-4 text-sm font-medium text-slate-900">
                            {{ tariff.name }}
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            <span
                                v-if="tariff.original_price && tariff.original_price > tariff.price"
                                class="mr-2 text-slate-400 line-through"
                            >
                                {{ Number(tariff.original_price).toLocaleString(localeTag(locale)) }}
                            </span>
                            {{ formatPrice(tariff.price, tariff.original_price) }}
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            {{ tariff.duration_days }} {{ t('common.daysShort') }}
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            {{ tariff.max_employees ?? '∞' }}
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            {{
                                tariff.message_retention_days
                                    ? `${tariff.message_retention_days} ${t('common.daysShort')}`
                                    : '∞'
                            }}
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            {{ tariff.is_free ? t('common.yes') : t('common.no') }}
                        </td>
                        <td class="px-4 py-4">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    tariff.is_active
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-600'
                                "
                            >
                                {{
                                    tariff.is_active
                                        ? t('admin.tariffs.active')
                                        : t('admin.tariffs.disabled')
                                }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <Link
                                :href="route('admin.tariffs.edit', tariff.id)"
                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                            >
                                {{ t('common.edit') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
