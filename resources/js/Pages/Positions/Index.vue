<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    positions: {
        type: Array,
        default: () => [],
    },
    pageOptions: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Должности',
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedPosition = ref(null);
const nameInput = ref(null);

const createForm = useForm({
    name: '',
    permissions: [],
});

const editForm = useForm({
    name: '',
    permissions: [],
});

const permissionLabels = computed(() => {
    const map = {};
    props.pageOptions.forEach((page) => {
        map[page.key] = page.label;
    });
    return map;
});

function permissionSummary(permissions) {
    if (!permissions?.length) {
        return 'Нет доступа к страницам';
    }

    if (permissions.length === props.pageOptions.length) {
        return 'Все страницы';
    }

    return permissions
        .map((key) => permissionLabels.value[key] ?? key)
        .slice(0, 4)
        .join(', ') + (permissions.length > 4 ? ` +${permissions.length - 4}` : '');
}

function openCreateModal() {
    createForm.name = '';
    createForm.permissions = [];
    createForm.clearErrors();
    showCreateModal.value = true;
    nextTick(() => nameInput.value?.focus());
}

function openEditModal(position) {
    selectedPosition.value = position;
    editForm.name = position.name;
    editForm.permissions = [...(position.permissions ?? [])];
    editForm.clearErrors();
    showEditModal.value = true;
}

function togglePermission(form, key) {
    const set = new Set(form.permissions);
    if (set.has(key)) {
        set.delete(key);
    } else {
        set.add(key);
    }
    form.permissions = [...set];
}

function selectAll(form) {
    form.permissions = props.pageOptions.map((page) => page.key);
}

function clearAll(form) {
    form.permissions = [];
}

function submitCreate() {
    createForm.post(route('positions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function submitEdit() {
    editForm.put(route('positions.update', selectedPosition.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedPosition.value = null;
        },
    });
}

function destroyPosition(position) {
    if (!confirm(`Удалить должность «${position.name}»?`)) {
        return;
    }

    useForm({}).delete(route('positions.destroy', position.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <div class="bg-slate-100 py-8 sm:py-10">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">Должности</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Создайте должности и выберите, к каким страницам есть доступ.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
                        @click="openCreateModal"
                    >
                        Добавить должность
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-lg">
                    <div
                        v-if="positions.length === 0"
                        class="px-6 py-16 text-center text-sm text-slate-400"
                    >
                        Должности ещё не созданы
                    </div>

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="position in positions"
                            :key="position.id"
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ position.name }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ permissionSummary(position.permissions) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    Сотрудников: {{ position.users_count }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                                    title="Редактировать"
                                    @click="openEditModal(position)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                    title="Удалить"
                                    @click="destroyPosition(position)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="2xl" @close="showCreateModal = false">
            <form class="p-6" @submit.prevent="submitCreate">
                <h3 class="text-lg font-semibold text-slate-900">Новая должность</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Укажите название и отметьте доступные разделы CRM.
                </p>

                <div class="mt-5">
                    <InputLabel for="position-name" value="Название должности" />
                    <TextInput
                        id="position-name"
                        ref="nameInput"
                        v-model="createForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Например: Менеджер"
                    />
                    <InputError class="mt-2" :message="createForm.errors.name" />
                </div>

                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <InputLabel value="Доступ к страницам" />
                        <div class="flex gap-2 text-xs">
                            <button type="button" class="text-indigo-600 hover:text-indigo-700" @click="selectAll(createForm)">
                                Выбрать все
                            </button>
                            <button type="button" class="text-slate-500 hover:text-slate-700" @click="clearAll(createForm)">
                                Сбросить
                            </button>
                        </div>
                    </div>
                    <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-2">
                        <label
                            v-for="page in pageOptions"
                            :key="page.key"
                            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-white"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                :checked="createForm.permissions.includes(page.key)"
                                @change="togglePermission(createForm, page.key)"
                            >
                            {{ page.label }}
                        </label>
                    </div>
                    <InputError class="mt-2" :message="createForm.errors.permissions" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = false">
                        Отмена
                    </SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">
                        Создать
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showEditModal" max-width="2xl" @close="showEditModal = false">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-semibold text-slate-900">Редактировать должность</h3>

                <div class="mt-5">
                    <InputLabel for="edit-position-name" value="Название должности" />
                    <TextInput
                        id="edit-position-name"
                        v-model="editForm.name"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="editForm.errors.name" />
                </div>

                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <InputLabel value="Доступ к страницам" />
                        <div class="flex gap-2 text-xs">
                            <button type="button" class="text-indigo-600 hover:text-indigo-700" @click="selectAll(editForm)">
                                Выбрать все
                            </button>
                            <button type="button" class="text-slate-500 hover:text-slate-700" @click="clearAll(editForm)">
                                Сбросить
                            </button>
                        </div>
                    </div>
                    <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-2">
                        <label
                            v-for="page in pageOptions"
                            :key="page.key"
                            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-white"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                :checked="editForm.permissions.includes(page.key)"
                                @change="togglePermission(editForm, page.key)"
                            >
                            {{ page.label }}
                        </label>
                    </div>
                    <InputError class="mt-2" :message="editForm.errors.permissions" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showEditModal = false">
                        Отмена
                    </SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">
                        Сохранить
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
