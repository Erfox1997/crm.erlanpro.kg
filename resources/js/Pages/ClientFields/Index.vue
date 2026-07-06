<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

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
});

const typeLabel = computed(() => Object.fromEntries(fieldTypes.map((item) => [item.value, item.label])));

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
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-800">
                    {{ pageTitle }}
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    Добавить поля
                </PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="mb-6 rounded-xl border border-sky-100 bg-sky-50/70 px-4 py-3 text-sm text-sky-900">
                    Настройте поля, которые менеджер заполняет при сохранении контакта из мессенджера:
                    имя, телефон, адрес, область, возраст, пол и любые другие параметры.
                </div>

                <div
                    v-if="fields.length === 0"
                    class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"
                >
                    <p class="text-slate-600">
                        Поля ещё не добавлены.
                    </p>
                    <PrimaryButton
                        type="button"
                        class="mt-4"
                        @click="openCreateModal"
                    >
                        Добавить первые поля
                    </PrimaryButton>
                </div>

                <div
                    v-else
                    class="space-y-3"
                >
                    <article
                        v-for="field in fields"
                        :key="field.id"
                        class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-900">
                                    {{ field.label }}
                                </h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                    {{ typeLabel[field.type] || field.type }}
                                </span>
                                <span
                                    v-if="field.is_required"
                                    class="rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700"
                                >
                                    Обязательное
                                </span>
                            </div>
                            <p class="mt-1 font-mono text-xs text-slate-500">
                                {{ field.key }}
                            </p>
                            <p
                                v-if="field.type === 'select' && field.options?.length"
                                class="mt-2 text-xs text-slate-500"
                            >
                                {{ field.options.join(' · ') }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <SecondaryButton
                                type="button"
                                @click="openEditModal(field)"
                            >
                                Изменить
                            </SecondaryButton>
                            <DangerButton
                                type="button"
                                @click="destroyField(field)"
                            >
                                Удалить
                            </DangerButton>
                        </div>
                    </article>
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
                                    Обязательное поле
                                </label>
                            </div>

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
