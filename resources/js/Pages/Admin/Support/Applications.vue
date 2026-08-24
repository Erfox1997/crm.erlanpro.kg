<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    clients: { type: Object, required: true },
    projects: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();

const selected = reactive({});
props.clients.data.forEach((client) => {
    selected[client.id] = [...(client.project_ids || [])];
});

function setFilter(status) {
    router.get(route('admin.support.applications.index'), { status }, { preserveState: true, replace: true });
}

function toggleProject(clientId, projectId) {
    const list = selected[clientId] || [];
    const idx = list.indexOf(projectId);
    if (idx === -1) {
        list.push(projectId);
    } else {
        list.splice(idx, 1);
    }
    selected[clientId] = list;
}

function accept(client) {
    if (!(selected[client.id]?.length > 0)) {
        alert('Выберите хотя бы один проект');
        return;
    }
    router.post(route('admin.support.applications.accept', client.id), {
        project_ids: selected[client.id],
    }, { preserveScroll: true });
}

function saveProjects(client) {
    if (!(selected[client.id]?.length > 0)) {
        alert('Выберите хотя бы один проект');
        return;
    }
    router.post(route('admin.support.applications.projects', client.id), {
        project_ids: selected[client.id],
    }, { preserveScroll: true });
}

function reject(client) {
    if (!confirm('Отклонить заявку?')) return;
    router.post(route('admin.support.applications.reject', client.id), {}, { preserveScroll: true });
}

function block(client) {
    if (!confirm('Заблокировать клиента? Его сообщения больше не будут приходить.')) return;
    router.post(route('admin.support.applications.block', client.id), {}, { preserveScroll: true });
}

function unblock(client) {
    router.post(route('admin.support.applications.unblock', client.id), {}, { preserveScroll: true });
}

const tabs = [
    { id: 'pending', label: 'Ожидают' },
    { id: 'accepted', label: 'Принятые' },
    { id: 'rejected', label: 'Отклонённые' },
    { id: 'blocked', label: 'Заблокированные' },
    { id: 'all', label: 'Все' },
];
</script>

<template>
    <Head :title="pageTitle || t('admin.supportApplications')" />
    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ pageTitle || t('admin.supportApplications') }}
            </h1>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="rounded-full px-3 py-1.5 text-sm font-medium"
                :class="filters.status === tab.id
                    ? 'bg-indigo-600 text-white'
                    : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                @click="setFilter(tab.id)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="projects.length === 0" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Сначала создайте проекты во вкладке «Проекты».
            <Link :href="route('admin.support.projects.index')" class="font-semibold underline">Перейти</Link>
        </div>

        <div class="space-y-4">
            <article
                v-for="client in clients.data"
                :key="client.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ client.name || 'Без имени' }}
                            <span v-if="client.username" class="text-sm font-normal text-slate-500">@{{ client.username }}</span>
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ client.phone || '—' }} · {{ client.company_name || 'без компании' }} · {{ client.created_at }}
                        </p>
                        <p class="mt-3 whitespace-pre-wrap text-sm text-slate-800">{{ client.message }}</p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="client.is_blocked
                            ? 'bg-slate-200 text-slate-700'
                            : client.status === 'accepted'
                                ? 'bg-emerald-100 text-emerald-700'
                                : client.status === 'rejected'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-amber-100 text-amber-800'"
                    >
                        {{ client.is_blocked ? 'Заблокирован' : client.status }}
                    </span>
                </div>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Проекты</p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="project in projects"
                            :key="project.id"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="(selected[client.id] || []).includes(project.id)
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-900'
                                : 'border-slate-200 text-slate-700'"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-indigo-600"
                                :checked="(selected[client.id] || []).includes(project.id)"
                                @change="toggleProject(client.id, project.id)"
                            >
                            {{ project.name }}
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <PrimaryButton
                        v-if="client.status === 'pending' && !client.is_blocked"
                        type="button"
                        @click="accept(client)"
                    >
                        Принять
                    </PrimaryButton>
                    <SecondaryButton
                        v-if="client.status === 'accepted' && !client.is_blocked"
                        type="button"
                        @click="saveProjects(client)"
                    >
                        Сохранить проекты
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="client.status === 'pending' && !client.is_blocked"
                        type="button"
                        @click="reject(client)"
                    >
                        Отклонить
                    </SecondaryButton>
                    <DangerButton
                        v-if="!client.is_blocked"
                        type="button"
                        @click="block(client)"
                    >
                        Заблокировать
                    </DangerButton>
                    <SecondaryButton
                        v-else
                        type="button"
                        @click="unblock(client)"
                    >
                        Разблокировать
                    </SecondaryButton>
                </div>
            </article>

            <p v-if="clients.data.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                Заявок пока нет.
            </p>
        </div>
    </AdminLayout>
</template>
