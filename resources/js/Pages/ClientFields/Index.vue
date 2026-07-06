<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({
    fields: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Данные клиента',
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedField = ref(null);
const createLabelInput = ref([]);

const fieldTypes = [
    { value: 'text', label: 'Текст' },
    { value: 'textarea', label: 'Длинный текст' },
    { value: 'number', label: 'Число' },
    { value: 'phone', label: 'Телефон' },
    { value: 'email', label: 'Email' },
    { value: 'date', label: 'Дата' },
    { value: 'select', label: 'Список' },
];

const cyrToLat = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z',
    и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
    с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch',
    ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
};

function emptyCreateRow() {
    return {
        label: '',
        key: '',
        type: 'text',
        options_text: '',
        is_required: false,
        show_in_messenger: false,
        keyTouched: false,
    };
}

const createRows = ref([emptyCreateRow()]);

const batchForm = useForm({
    fields: [],
});

const editForm = useForm({
    label: '',
    key: '',
    type: 'text',
    options: [],
    options_text: '',
    is_required: false,
    show_in_messenger: false,
});

function transliterate(value) {
    return value
        .toLowerCase()
        .split('')
        .map((char) => cyrToLat[char] ?? char)
        .join('');
}

function slugify(value) {
    return transliterate(value)
        .trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');
}

function onRowLabelInput(index) {
    const row = createRows.value[index];

    if (!row.keyTouched) {
        row.key = slugify(row.label);
    }
}

function onRowKeyInput(index) {
    createRows.value[index].keyTouched = true;
}

function existingMessengerFieldId() {
    return props.fields.find((field) => field.show_in_messenger)?.id ?? null;
}

function onRowMessengerToggle(index, checked) {
    if (!checked) {
        createRows.value[index].show_in_messenger = false;

        return;
    }

    createRows.value.forEach((row, rowIndex) => {
        row.show_in_messenger = rowIndex === index;
    });
}

function openCreateModal() {
    createRows.value = [emptyCreateRow()];
    batchForm.clearErrors();
    showCreateModal.value = true;

    nextTick(() => createLabelInput.value?.[0]?.focus());
}

function addCreateRow() {
    createRows.value.push(emptyCreateRow());

    nextTick(() => {
        const inputs = createLabelInput.value;

        if (inputs?.length) {
            inputs[inputs.length - 1]?.focus();
        }
    });
}

function removeCreateRow(index) {
    if (createRows.value.length === 1) {
        createRows.value[0] = emptyCreateRow();

        return;
    }

    createRows.value.splice(index, 1);
}

function openEditModal(field) {
    selectedField.value = field;
    editForm.label = field.label;
    editForm.key = field.key;
    editForm.type = field.type;
    editForm.options = field.options ?? [];
    editForm.options_text = (field.options ?? []).join('\n');
    editForm.is_required = field.is_required;
    editForm.show_in_messenger = field.show_in_messenger;
    editForm.clearErrors();
    showEditModal.value = true;
}

function parseOptions(text) {
    return text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

function submitBatch(closeAfter = true) {
    const prepared = createRows.value
        .map((row) => ({
            label: row.label.trim(),
            key: row.key.trim(),
            type: row.type,
            options: row.type === 'select' ? parseOptions(row.options_text) : [],
            is_required: row.is_required,
            show_in_messenger: row.show_in_messenger,
        }))
        .filter((row) => row.label !== '');

    if (prepared.length === 0) {
        batchForm.setError('fields', 'Добавьте хотя бы одно поле с названием.');

        return;
    }

    batchForm.fields = prepared;
    batchForm.post(route('client-fields.store-batch'), {
        preserveScroll: true,
        onSuccess: () => {
            if (closeAfter) {
                showCreateModal.value = false;
            }

            createRows.value = [emptyCreateRow()];
            batchForm.clearErrors();

            if (!closeAfter) {
                nextTick(() => createLabelInput.value?.[0]?.focus());
            }
        },
    });
}

function submitEdit() {
    editForm
        .transform((data) => ({
            label: data.label,
            key: data.key,
            type: data.type,
            options: data.type === 'select' ? parseOptions(data.options_text) : [],
            is_required: data.is_required,
            show_in_messenger: data.show_in_messenger,
        }))
        .put(route('client-fields.update', selectedField.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false;
                selectedField.value = null;
            },
        });
}

function destroyField(field) {
    if (!confirm(`Удалить поле «${field.label}»?`)) {
        return;
    }

    useForm({}).delete(route('client-fields.destroy', field.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="CRM" />

    <AuthenticatedLayout>
        <div class="bg-slate-100 py-8 sm:py-10">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="mb-6 flex justify-center">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-800 px-8 py-2.5 text-sm font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-slate-700"
                        @click="openCreateModal"
                    >
                        Добавить поля
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-lg">
                    <div class="flex flex-col sm:flex-row">
                        <div class="flex items-center justify-center border-b border-slate-100 bg-white px-8 py-10 sm:w-[34%] sm:border-b-0 sm:border-r">
                            <div class="flex h-28 w-28 items-center justify-center rounded-full bg-slate-800 shadow-md">
                                <svg class="h-14 w-14 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1 px-6 py-4 sm:px-8 sm:py-6">
                            <div
                                v-if="fields.length === 0"
                                class="flex h-full min-h-[12rem] items-center justify-center py-8 text-center text-sm text-slate-400"
                            >
                                Поля ещё не добавлены
                            </div>

                            <ul v-else class="divide-y divide-slate-100">
                                <li
                                    v-for="field in fields"
                                    :key="field.id"
                                    class="flex items-center justify-between gap-4 py-3.5"
                                >
                                    <span class="text-sm text-slate-700">
                                        {{ field.label }}:
                                        <span
                                            v-if="field.show_in_messenger"
                                            class="ml-1 text-xs font-normal text-emerald-600"
                                        >
                                            (имя в чате)
                                        </span>
                                    </span>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <button
                                            type="button"
                                            class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                                            title="Редактировать"
                                            @click="openEditModal(field)"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                            title="Удалить"
                                            @click="destroyField(field)"
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
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="3xl" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    Новые поля
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Добавьте сразу несколько полей — например: Имя, Телефон, Адрес, Область.
                </p>

                <form
                    class="mt-5 space-y-4"
                    @submit.prevent="submitBatch(true)"
                >
                    <div class="max-h-[55vh] space-y-4 overflow-y-auto pr-1">
                        <div
                            v-for="(row, index) in createRows"
                            :key="index"
                            class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-medium text-slate-700">
                                    Поле {{ index + 1 }}
                                </p>
                                <button
                                    v-if="createRows.length > 1"
                                    type="button"
                                    class="text-xs text-rose-600 hover:text-rose-700"
                                    @click="removeCreateRow(index)"
                                >
                                    Убрать
                                </button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel :for="`create_label_${index}`" value="Название поля" />
                                    <TextInput
                                        :id="`create_label_${index}`"
                                        :ref="(el) => { if (el) createLabelInput[index] = el }"
                                        v-model="row.label"
                                        class="mt-1 block w-full"
                                        placeholder="Например: Имя"
                                        @input="onRowLabelInput(index)"
                                    />
                                    <InputError class="mt-2" :message="batchForm.errors[`fields.${index}.label`]" />
                                </div>

                                <div>
                                    <InputLabel :for="`create_key_${index}`" value="Ключ (латиница)" />
                                    <TextInput
                                        :id="`create_key_${index}`"
                                        v-model="row.key"
                                        class="mt-1 block w-full font-mono text-sm"
                                        placeholder="name"
                                        @input="onRowKeyInput(index)"
                                    />
                                    <InputError class="mt-2" :message="batchForm.errors[`fields.${index}.key`]" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel :for="`create_type_${index}`" value="Тип" />
                                    <select
                                        :id="`create_type_${index}`"
                                        v-model="row.type"
                                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                    >
                                        <option
                                            v-for="type in fieldTypes"
                                            :key="type.value"
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="batchForm.errors[`fields.${index}.type`]" />
                                </div>

                                <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
                                    <input
                                        v-model="row.is_required"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-indigo-600"
                                    >
                                    Обязательное
                                </label>
                            </div>

                            <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                                <input
                                    :checked="row.show_in_messenger"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-emerald-600"
                                    :disabled="existingMessengerFieldId() !== null"
                                    @change="onRowMessengerToggle(index, $event.target.checked)"
                                >
                                <span>
                                    Имя в мессенджере
                                    <span class="block text-xs text-slate-500">
                                        Это значение показывается в шапке чата вместо имени из Telegram / Instagram.
                                    </span>
                                    <span
                                        v-if="existingMessengerFieldId() !== null"
                                        class="mt-1 block text-xs text-amber-600"
                                    >
                                        Уже назначено другому полю — снимите галочку там или отредактируйте его.
                                    </span>
                                </span>
                            </label>

                            <div
                                v-if="row.type === 'select'"
                                class="mt-4"
                            >
                                <InputLabel :for="`create_options_${index}`" value="Варианты (по одному в строке)" />
                                <textarea
                                    :id="`create_options_${index}`"
                                    v-model="row.options_text"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                    placeholder="Мужской&#10;Женский"
                                />
                                <InputError class="mt-2" :message="batchForm.errors[`fields.${index}.options`]" />
                            </div>
                        </div>
                    </div>

                    <InputError :message="batchForm.errors.fields" />

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <SecondaryButton
                            type="button"
                            @click="addCreateRow"
                        >
                            + Добавить ещё поле
                        </SecondaryButton>

                        <div class="flex flex-wrap gap-2">
                            <SecondaryButton
                                type="button"
                                @click="showCreateModal = false"
                            >
                                Отмена
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                :disabled="batchForm.processing"
                                @click="submitBatch(false)"
                            >
                                Сохранить и добавить ещё
                            </SecondaryButton>
                            <PrimaryButton
                                type="submit"
                                :disabled="batchForm.processing"
                            >
                                Сохранить все
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    Редактировать поле
                </h3>

                <form
                    class="mt-5 space-y-4"
                    @submit.prevent="submitEdit"
                >
                    <div>
                        <InputLabel for="edit_label" value="Название поля" />
                        <TextInput
                            id="edit_label"
                            v-model="editForm.label"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="editForm.errors.label" />
                    </div>

                    <div>
                        <InputLabel for="edit_key" value="Ключ (латиница)" />
                        <TextInput
                            id="edit_key"
                            v-model="editForm.key"
                            class="mt-1 block w-full font-mono text-sm"
                        />
                        <InputError class="mt-2" :message="editForm.errors.key" />
                    </div>

                    <div>
                        <InputLabel for="edit_type" value="Тип" />
                        <select
                            id="edit_type"
                            v-model="editForm.type"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                        >
                            <option
                                v-for="type in fieldTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.type" />
                    </div>

                    <div v-if="editForm.type === 'select'">
                        <InputLabel for="edit_options" value="Варианты (по одному в строке)" />
                        <textarea
                            id="edit_options"
                            v-model="editForm.options_text"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                        />
                        <InputError class="mt-2" :message="editForm.errors.options" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="editForm.is_required"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600"
                        >
                        Обязательное поле
                    </label>

                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input
                            v-model="editForm.show_in_messenger"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-emerald-600"
                            :disabled="existingMessengerFieldId() !== null && existingMessengerFieldId() !== selectedField?.id"
                        >
                        <span>
                            Имя в мессенджере
                            <span class="block text-xs text-slate-500">
                                Это значение показывается в шапке чата вместо имени из мессенджера.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <SecondaryButton
                            type="button"
                            @click="showEditModal = false"
                        >
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="editForm.processing"
                        >
                            Сохранить
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
