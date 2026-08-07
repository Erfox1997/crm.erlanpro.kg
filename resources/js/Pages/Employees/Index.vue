<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    employees: {
        type: Array,
        default: () => [],
    },
    positions: {
        type: Array,
        default: () => [],
    },
    limits: {
        type: Object,
        default: () => ({
            max_employees: null,
            employees_used: 0,
            can_add: true,
        }),
    },
    filter: {
        type: String,
        default: 'active',
    },
    pageTitle: {
        type: String,
        default: null,
    },
    managerBotUsername: {
        type: String,
        default: '',
    },
});

const title = computed(() => props.pageTitle || t('employees.title'));

const limitLabel = computed(() => {
    if (props.limits.max_employees == null) {
        return t('employees.countUnlimited', { n: props.limits.employees_used });
    }

    return t('employees.countLimited', {
        used: props.limits.employees_used,
        max: props.limits.max_employees,
    });
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
    telegram_username: '',
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    position_id: '',
    telegram_username: '',
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
        return [employee.name, employee.email, employee.position_name, employee.telegram_username]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q));
    });
});

function openCreateModal() {
    if (!props.limits.can_add) {
        return;
    }

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
    editForm.telegram_username = employee.telegram_username || '';
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

function setFilter(value) {
    router.get(route('employees.index'), { filter: value }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

function dismissEmployee(employee) {
    if (!confirm(t('employees.confirmFireDetail', { name: employee.name }))) {
        return;
    }

    useForm({}).post(route('employees.dismiss', employee.id), {
        preserveScroll: true,
    });
}

function restoreEmployee(employee) {
    if (!confirm(t('employees.confirmRestore', { name: employee.name }))) {
        return;
    }

    useForm({}).post(route('employees.restore', employee.id), {
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
    <Head :title="title" />

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
                        <h1 class="text-xl font-semibold text-slate-900">{{ t('employees.title') }}</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ t('employees.subtitle') }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-700">
                            {{ t('employees.byTariff', { label: limitLabel }) }}
                        </p>
                        <p v-if="managerBotUsername" class="mt-1 text-sm text-slate-500">
                            {{ t('employees.telegramMiniAppHint', { bot: managerBotUsername }) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="!limits.can_add"
                    class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                >
                    {{ t('employees.limitExhausted') }}
                </div>

                <div
                    v-if="positions.length === 0"
                    class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    {{ t('employees.needPositions') }}
                    <a :href="route('positions.index')" class="font-medium underline">{{ t('employees.needPositionsLink') }}</a>.
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                        <div class="mb-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                :class="filter === 'active'
                                    ? 'bg-slate-800 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                @click="setFilter('active')"
                            >
                                {{ t('employees.active') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                :class="filter === 'dismissed'
                                    ? 'bg-slate-800 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                @click="setFilter('dismissed')"
                            >
                                {{ t('employees.fired') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                :class="filter === 'all'
                                    ? 'bg-slate-800 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                @click="setFilter('all')"
                            >
                                {{ t('common.all') }}
                            </button>
                        </div>

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
                                    :placeholder="t('employees.searchPh')"
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
                                    {{ t('employees.excelSample') }}
                                </a>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                                    :disabled="importForm.processing || positions.length === 0 || !limits.can_add"
                                    @click="triggerImport"
                                >
                                    <svg class="h-4 w-4 text-slate-500" :class="{ 'animate-spin': importForm.processing }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                    {{ importForm.processing ? t('employees.importing') : t('employees.importExcel') }}
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
                                    :disabled="positions.length === 0 || !limits.can_add"
                                    @click="openCreateModal"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ t('employees.add') }}
                                </button>
                            </div>
                        </div>

                        <InputError class="mt-2" :message="importForm.errors.file" />
                        <p class="mt-2 text-xs text-slate-400">
                            {{ t('employees.excelHint') }}
                        </p>
                    </div>

                    <div v-if="filteredEmployees.length === 0" class="px-6 py-16 text-center text-sm text-slate-400">
                        {{ employees.length === 0 ? t('employees.notAdded') : t('common.noResults') }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">{{ t('employees.fullName') }}</th>
                                    <th class="px-5 py-3">{{ t('employees.mail') }}</th>
                                    <th class="px-5 py-3">{{ t('employees.telegram') }}</th>
                                    <th class="px-5 py-3">{{ t('employees.position') }}</th>
                                    <th class="px-5 py-3">{{ t('common.status') }}</th>
                                    <th class="px-5 py-3 text-right">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="employee in filteredEmployees"
                                    :key="employee.id"
                                    class="bg-white"
                                    :class="{ 'opacity-70': employee.dismissed }"
                                >
                                    <td class="px-5 py-3 font-medium text-slate-900">
                                        {{ employee.name }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">
                                        {{ employee.email }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">
                                        <span v-if="employee.telegram_username">
                                            @{{ employee.telegram_username }}
                                            <span
                                                class="ml-1 text-xs"
                                                :class="employee.telegram_linked ? 'text-emerald-600' : 'text-amber-600'"
                                            >
                                                {{ employee.telegram_linked ? '✓' : t('employees.waitingStart') }}
                                            </span>
                                        </span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">
                                        {{ employee.position_name || '—' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span
                                            v-if="employee.dismissed"
                                            class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700"
                                        >
                                            {{ t('employees.dismissedLabel') }}{{ employee.dismissed_at ? ` ${employee.dismissed_at}` : '' }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
                                        >
                                            {{ t('common.active') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                v-if="!employee.dismissed"
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                                                :title="t('employees.editTitleAttr')"
                                                @click="openEditModal(employee)"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="!employee.dismissed"
                                                type="button"
                                                class="rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                                :title="t('employees.fire')"
                                                @click="dismissEmployee(employee)"
                                            >
                                                {{ t('employees.fire') }}
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                class="rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50"
                                                :title="t('employees.restore')"
                                                @click="restoreEmployee(employee)"
                                            >
                                                {{ t('employees.restore') }}
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
                <h3 class="text-lg font-semibold text-slate-900">{{ t('employees.newEmployee') }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ t('employees.newHint') }}
                </p>
                <InputError class="mt-3" :message="createForm.errors.employee" />

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="employee-name" :value="t('employees.fullName')" />
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
                        <InputLabel for="employee-email" :value="t('employees.mail')" />
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
                        <InputLabel for="employee-password" :value="t('common.password')" />
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
                        <InputLabel for="employee-password-confirmation" :value="t('employees.passwordConfirm')" />
                        <TextInput
                            id="employee-password-confirmation"
                            v-model="createForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                    </div>

                    <div>
                        <InputLabel for="employee-position" :value="t('employees.position')" />
                        <select
                            id="employee-position"
                            v-model="createForm.position_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>{{ t('employees.selectPosition') }}</option>
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

                    <div>
                        <InputLabel for="employee-telegram" :value="t('employees.telegramUsername')" />
                        <TextInput
                            id="employee-telegram"
                            v-model="createForm.telegram_username"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ivan_manager"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            {{ t('employees.telegramFieldHint') }}
                        </p>
                        <InputError class="mt-2" :message="createForm.errors.telegram_username" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = false">
                        {{ t('common.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">
                        {{ t('common.create') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showEditModal" max-width="lg" @close="showEditModal = false">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-semibold text-slate-900">{{ t('employees.editEmployee') }}</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="edit-employee-name" :value="t('employees.fullName')" />
                        <TextInput
                            id="edit-employee-name"
                            v-model="editForm.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="editForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-email" :value="t('employees.mail')" />
                        <TextInput
                            id="edit-employee-email"
                            v-model="editForm.email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="editForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-password" :value="t('employees.newPasswordOptional')" />
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
                        <InputLabel for="edit-employee-password-confirmation" :value="t('employees.passwordConfirm')" />
                        <TextInput
                            id="edit-employee-password-confirmation"
                            v-model="editForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                    </div>

                    <div>
                        <InputLabel for="edit-employee-position" :value="t('employees.position')" />
                        <select
                            id="edit-employee-position"
                            v-model="editForm.position_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>{{ t('employees.selectPosition') }}</option>
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

                    <div>
                        <InputLabel for="edit-employee-telegram" :value="t('employees.telegramUsername')" />
                        <TextInput
                            id="edit-employee-telegram"
                            v-model="editForm.telegram_username"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ivan_manager"
                        />
                        <InputError class="mt-2" :message="editForm.errors.telegram_username" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showEditModal = false">
                        {{ t('common.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">
                        {{ t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
