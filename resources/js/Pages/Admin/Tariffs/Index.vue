<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    tariffs: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Тарифы',
    },
});

function formatPrice(value, original = null) {
    if (original && original > value) {
        return `${Number(original).toLocaleString('ru-RU')} → ${Number(value).toLocaleString('ru-RU')}`;
    }

    return Number(value).toLocaleString('ru-RU');
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ pageTitle }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">Тариф</p>
                </div>
                <Link
                    :href="route('admin.tariffs.create')"
                    class="inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Создать тариф
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
                            Название
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Цена
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Срок (дней)
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Бесплатный
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Статус
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
                                {{ Number(tariff.original_price).toLocaleString('ru-RU') }}
                            </span>
                            {{ formatPrice(tariff.price, tariff.original_price) }}
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            {{ tariff.duration_days }} дн.
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            {{ tariff.is_free ? 'Да' : 'Нет' }}
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
                                {{ tariff.is_active ? 'Активен' : 'Выключен' }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <Link
                                :href="route('admin.tariffs.edit', tariff.id)"
                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                            >
                                Изменить
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
