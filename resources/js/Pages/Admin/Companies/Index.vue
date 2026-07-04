<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    companies: Object,
    filters: Object,
    pageTitle: {
        type: String,
        default: 'Список клиентов',
    },
});

const q = ref(props.filters.q ?? '');

let timeout;
watch(q, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('admin.companies.index'),
            { q: value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ pageTitle }}
                </h1>
            </div>
        </template>

        <div class="mb-6 max-w-md">
            <TextInput
                v-model="q"
                type="search"
                class="block w-full"
                placeholder="Поиск по компании, имени или email"
            />
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            ID
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Клиент
                        </th>
                        <th
                            class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 md:table-cell"
                        >
                            Email
                        </th>
                        <th
                            class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 lg:table-cell"
                        >
                            Тариф
                        </th>
                        <th
                            class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell"
                        >
                            Действует до
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
                    <tr v-for="company in companies.data" :key="company.id">
                        <td class="px-4 py-4 text-sm text-slate-500">
                            {{ company.id }}
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-sm font-medium text-slate-900">
                                {{ company.name }}
                            </p>
                            <p
                                v-if="company.owner_name"
                                class="text-xs text-slate-500"
                            >
                                {{ company.owner_name }}
                            </p>
                        </td>
                        <td
                            class="hidden px-4 py-4 text-sm text-slate-600 md:table-cell"
                        >
                            {{ company.owner_email ?? '—' }}
                        </td>
                        <td
                            class="hidden px-4 py-4 text-sm text-slate-600 lg:table-cell"
                        >
                            {{ company.tariff_name ?? '—' }}
                        </td>
                        <td
                            class="hidden px-4 py-4 text-sm text-slate-600 sm:table-cell"
                        >
                            {{ company.subscription_ends_at_formatted ?? '—' }}
                        </td>
                        <td class="px-4 py-4">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    company.status_is_active
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-red-100 text-red-700'
                                "
                            >
                                {{ company.status }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <Link
                                :href="route('admin.companies.show', company.id)"
                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                            >
                                Просмотр
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="companies.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-sm text-slate-500"
                        >
                            Компаний пока нет.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
