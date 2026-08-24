<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    projects: { type: Array, default: () => [] },
    pageTitle: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();
const form = useForm({ name: '' });
const editId = ref(null);
const editName = ref('');

function createProject() {
    form.post(route('admin.support.projects.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
}

function startEdit(project) {
    editId.value = project.id;
    editName.value = project.name;
}

function saveEdit(project) {
    router.put(route('admin.support.projects.update', project.id), {
        name: editName.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editId.value = null;
        },
    });
}

function removeProject(project) {
    if (!confirm(`Удалить проект «${project.name}»?`)) {
        return;
    }
    router.delete(route('admin.support.projects.destroy', project.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageTitle || t('admin.supportProjects')" />
    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ pageTitle || t('admin.supportProjects') }}
            </h1>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>

        <form
            class="mb-6 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            @submit.prevent="createProject"
        >
            <TextInput
                v-model="form.name"
                class="min-w-[16rem] flex-1"
                placeholder="Название проекта"
                required
            />
            <PrimaryButton :disabled="form.processing || !form.name.trim()">
                Создать проект
            </PrimaryButton>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Проект</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Клиенты</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Открытые</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="project in projects" :key="project.id">
                        <td class="px-4 py-3">
                            <template v-if="editId === project.id">
                                <TextInput v-model="editName" class="w-full" />
                            </template>
                            <span v-else class="font-medium text-slate-900">{{ project.name }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ project.clients_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ project.open_messages_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <template v-if="editId === project.id">
                                <button type="button" class="text-sm font-semibold text-indigo-600" @click="saveEdit(project)">Сохранить</button>
                                <button type="button" class="text-sm text-slate-500" @click="editId = null">Отмена</button>
                            </template>
                            <template v-else>
                                <button type="button" class="text-sm font-semibold text-indigo-600" @click="startEdit(project)">Изменить</button>
                                <DangerButton type="button" class="!px-2 !py-1 !text-[10px]" @click="removeProject(project)">Удалить</DangerButton>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="projects.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">
                            Пока нет проектов. Создайте первый.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
