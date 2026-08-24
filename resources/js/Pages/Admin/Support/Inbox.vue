<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    projects: { type: Array, default: () => [] },
    pageTitle: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();
</script>

<template>
    <Head :title="pageTitle || t('admin.supportInbox')" />
    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ pageTitle || t('admin.supportInbox') }}
            </h1>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="project in projects"
                :key="project.id"
                :href="route('admin.support.inbox.show', project.id)"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ project.name }}
                    </h2>
                    <span
                        v-if="project.open_messages_count > 0"
                        class="rounded-full bg-rose-500 px-2.5 py-1 text-xs font-bold text-white"
                    >
                        +{{ project.open_messages_count }}
                    </span>
                    <span
                        v-else
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500"
                    >
                        0
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    Открытых обращений: {{ project.open_messages_count }}
                </p>
            </Link>
        </div>

        <p
            v-if="projects.length === 0"
            class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500"
        >
            Нет проектов. Создайте их во вкладке «Проекты».
        </p>
    </AdminLayout>
</template>
