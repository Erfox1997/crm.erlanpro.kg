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
    employees: {
        type: Array,
        default: () => [],
    },
    positions: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Сотрудники',
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedEmployee = ref(null);
const nameInput = ref(null);
const importInput = ref(null);
const searchQuery = ref('');

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    position_id: '',
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    position_id: '',
});

const importForm = useForm({
    file: null,
});

const filteredEmployees = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.employees;
    }

    return props.employees.filter((employee) => {
        return [employee.name, employee.email, employee.position_name]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q));
    });
});

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
    nextTick(() => nameInput.value?.focus());
}

function openEditModal(employee) {
    selectedEmployee.value = employee;
    editForm.name = employee.name;
    editForm.email = employee.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.position_id = employee.position_id ?? '';
    editForm.clearErrors();
    showEditModal.value = true;
}

function submitCreate() {
    createForm.post(route('employees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function submitEdit() {
    editForm.put(route('employees.update', selectedEmployee.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedEmployee.value = null;
            editForm.reset('password', 'password_confirmation');
        },
    });
}

function destroyEmployee(employee) {
    if (!confirm(`Удалить сотрудника «${employee.name}»?`)) {
        return;
    }

    useForm({}).delete(route('employees.destroy', employee.id), {
        preserveScroll: true,
    });
}

function triggerImport() {
    importInput.value?.click();
}

function onImportChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    importForm.file = file;
    importForm.post(route('employees.import'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <div class="bg-slate-100 py-8 sm:py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">Сотрудники</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Создайте аккаунты сотрудников или загрузите их из Excel.
                        </p>
                    </div>
                </div>

                <div
                    v-if="positions.length === 0"
                    class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    Сначала создайте хотя бы одну должность на странице
                    <a :href="route('positions.index')" class="font-medium underline">Должности</a>.
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="relative min-w-0 flex-1">
                                <svg
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Поиск по ФИО, почте или должности..."
                                    class="w-full rounded-xl border-slate-200 bg-white py-2 pl-9 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-400 focus:ring-indigo-400"
                                >
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    :href="route('employees.sample')"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" />
                                    </svg>
                                    Образец Excel
                                </a>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                                    :disabled="importForm.processing || positions.length === 0"
                                    @click="triggerImport"
                                >
                                    <svg class="h-4 w-4 text-slate-500" :class="{ 'animate-spin': importForm.processing }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                    {{ importForm.processing ? 'Импорт...' : 'Импорт Excel' }}
                                </button>
                                <input
                                    ref="importInput"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    class="hidden"
                                    @change="onImportChange"
                                >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 disabled:opacity-50"
                                    :disabled="positions.length === 0"
                                    @click="openCreateModal"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Добавить сотрудника
                                </button>
                            </div>
                        </div>

                        <InputError class="mt-2" :message="importForm.errors.file" />
                        <p class="mt-2 text-xs text-slate-400">
                            Excel: колонки ФИО, Почта, Пароль, Должность (название должно совпадать с созданной должностью).
                        </p>
                    </div>

                    <div v-if="filteredEmployees.length === 0" class="px-6 py-16 text-center text-sm text-slate-400">
                        {{ employees.length === 0 ? 'Сотрудники ещё не добавлены' : 'Ничего не найдено' }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">ФИО</th>
                                    <th class="px-5 py-3">Почта</th>
                                    <th class="px-5 py-3">Должность</th>
                                    <th class="px-5 py-3">Создан</th>
                                    <th class="px-5 py-3 text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="employee in filteredEmployees"
                                    :key="employee.id"
                                    class="bg-white"
                                >
                                    <td class="px-5 py-3 font-medium text-slate-900">
                                        {{ employee.name }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">
                                        {{ employee.email }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">
                                        {{ employee.position_name || '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-500">
                                        {{ employee.created_at || '—' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                                                title="Редактировать"
                                                @click="openEditModal(employee)"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                                title="Удалить"
                                                @click="destroyEmployee(employee)"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="lg" @close="showCreateModal = false">
            <form class="p-6" @submit.prevent="submitCreate">
                <h3 class="text-lg font-semibold text-slate-900">Новый сотрудник</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Аккаунт сразу сможет войти в CRM с указанным паролем.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="employee-name" value="ФИО" />
                        <TextInput
                            id="employee-name"
                            ref="nameInput"
                            v-model="createForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="name"
                        />
                        <InputError class="mt-2" :message="createForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="employee-email" value="Почта" />
                        <TextInput
                            id="employee-email"
                            v-model="createForm.email"
                            type="email"
                            class="mt-1 block w-full"
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="createForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="employee-password" value="Пароль" />
                        <TextInput
                            id="employee-password"
                            v-model="createForm.password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="createForm.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="employee-password-confirmation" value="Повтор пароля" />
                        <TextInput
                            id="employee-password-confirmation"
                            v-model="createForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                    </div>

                    <div>
                        <InputLabel for="employee-position" value="Должность" />
                        <select
                            id="employee-position"
                            v-model="createForm.position_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Выберите должность</option>
                            <option
                                v-for="position in positions"
                                :key="position.id"
                                :value="position.id"
                            >
                                {{ position.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.position_id" />
                    </div>
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

        <Modal :show="showEditModal" max-width="lg" @close="showEditModal = false">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-semibold text-slate-900">Редактировать сотрудника</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="edit-employee-name" value="ФИО" />
                        <TextInput
                            id="edit-employee-name"
                            v-model="editForm.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="editForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-email" value="Почта" />
                        <TextInput
                            id="edit-employee-email"
                            v-model="editForm.email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="editForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-password" value="Новый пароль (необязательно)" />
                        <TextInput
                            id="edit-employee-password"
                            v-model="editForm.password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="editForm.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-password-confirmation" value="Повтор пароля" />
                        <TextInput
                            id="edit-employee-password-confirmation"
                            v-model="editForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-position" value="Должность" />
                        <select
                            id="edit-employee-position"
                            v-model="editForm.position_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Выберите должность</option>
                            <option
                                v-for="position in positions"
                                :key="position.id"
                                :value="position.id"
                            >
                                {{ position.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.position_id" />
                    </div>
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
