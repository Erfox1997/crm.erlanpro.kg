<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    clients: Object,
    filters: Object,
});

const q = ref(props.filters.q ?? '');

let timeout;
watch(q, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('clients.index'),
            { q: value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Клиенты" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="text-xl font-semibold text-gray-800">Клиенты</h2>
                <Link
                    :href="route('clients.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Добавить клиента
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 max-w-md">
                    <TextInput
                        v-model="q"
                        type="search"
                        class="block w-full"
                        placeholder="Поиск по имени, email, телефону"
                    />
                </div>

                <div
                    class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-gray-900/5"
                >
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                                >
                                    Имя
                                </th>
                                <th
                                    class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell"
                                >
                                    Телефон
                                </th>
                                <th
                                    class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 md:table-cell"
                                >
                                    Email
                                </th>
                                <th class="relative px-4 py-3">
                                    <span class="sr-only">Действия</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="client in clients.data" :key="client.id">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ client.name }}
                                </td>
                                <td
                                    class="hidden whitespace-nowrap px-4 py-3 text-sm text-gray-600 sm:table-cell"
                                >
                                    {{ client.phone || '—' }}
                                </td>
                                <td
                                    class="hidden whitespace-nowrap px-4 py-3 text-sm text-gray-600 md:table-cell"
                                >
                                    {{ client.email || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    <Link
                                        :href="route('clients.edit', client.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        Изменить
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!clients.data.length">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-sm text-gray-500"
                                >
                                    Клиентов пока нет.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="clients.links.length > 3"
                    class="mt-6 flex flex-wrap justify-center gap-1"
                >
                    <template v-for="(link, i) in clients.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded px-3 py-1 text-sm"
                            :class="
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded px-3 py-1 text-sm text-gray-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
