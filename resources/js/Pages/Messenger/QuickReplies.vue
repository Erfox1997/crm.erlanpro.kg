<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    quickReplies: {
        type: Array,
        default: () => [],
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedItem = ref(null);
const importInput = ref(null);

const createForm = useForm({
    type: 'text',
    title: '',
    body: '',
    attachment: null,
});

const editForm = useForm({
    type: 'text',
    title: '',
    body: '',
    attachment: null,
});

const importForm = useForm({
    file: null,
});

const typeLabels = {
    text: 'Текст',
    audio: 'Голос',
    image: 'Картинка',
};

const createAttachmentLabel = computed(() => {
    if (createForm.type === 'audio') {
        return 'Аудиофайл (M4A, MP3, WAV)';
    }

    if (createForm.type === 'image') {
        return 'Изображение (JPG, PNG, WEBP)';
    }

    return '';
});

const editAttachmentLabel = computed(() => {
    if (editForm.type === 'audio') {
        return 'Новый аудиофайл (необязательно)';
    }

    if (editForm.type === 'image') {
        return 'Новое изображение (необязательно)';
    }

    return '';
});

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    createForm.type = 'text';
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
    createForm.reset();
    createForm.clearErrors();
}

function openEditModal(item) {
    selectedItem.value = item;
    editForm.clearErrors();
    editForm.type = item.type || 'text';
    editForm.title = item.title;
    editForm.body = item.body || '';
    editForm.attachment = null;
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    selectedItem.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function onCreateAttachmentChange(event) {
    createForm.attachment = event.target.files?.[0] ?? null;
}

function onEditAttachmentChange(event) {
    editForm.attachment = event.target.files?.[0] ?? null;
}

function submitCreate() {
    createForm.post(route('messenger.quick-replies.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeCreateModal(),
    });
}

function submitEdit() {
    if (!selectedItem.value) {
        return;
    }

    editForm.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(route('messenger.quick-replies.update', selectedItem.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeEditModal(),
    });
}

function removeSelected() {
    if (!selectedItem.value || !window.confirm('Удалить этот быстрый ответ?')) {
        return;
    }

    useForm({}).delete(route('messenger.quick-replies.destroy', selectedItem.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
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
    importForm.post(route('messenger.quick-replies.import'), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            importForm.reset();
        },
    });
}

function previewText(item) {
    if (item.type === 'text') {
        return item.body;
    }

    if (item.type === 'audio') {
        return item.body || 'Голосовой шаблон';
    }

    return item.body || 'Изображение';
}
</script>

<template>
    <Head title="Быстрые ответы" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        Быстрые ответы
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Шаблоны для мессенджера. В чате введите «/» и начните писать название.
                    </p>
                </div>
                <Link
                    :href="route('messenger.index')"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← К чатам
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800">
                                Шаблоны ({{ quickReplies.length }})
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Excel: колонка A — название, B — текст (только текстовые шаблоны)
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a
                                :href="route('messenger.quick-replies.sample')"
                                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Скачать образец
                            </a>
                            <SecondaryButton @click="triggerImport">
                                Импорт Excel
                            </SecondaryButton>
                            <input
                                ref="importInput"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="hidden"
                                @change="onImportChange"
                            >
                            <PrimaryButton @click="openCreateModal">
                                Создать
                            </PrimaryButton>
                        </div>
                    </div>

                    <InputError
                        class="px-5 pt-3"
                        :message="importForm.errors.file"
                    />

                    <div
                        v-if="quickReplies.length === 0"
                        class="px-5 py-12 text-center"
                    >
                        <p class="text-sm text-gray-500">
                            Пока нет шаблонов.
                        </p>
                        <PrimaryButton
                            class="mt-4"
                            @click="openCreateModal"
                        >
                            Создать первый шаблон
                        </PrimaryButton>
                    </div>

                    <ul
                        v-else
                        class="divide-y divide-gray-100"
                    >
                        <li
                            v-for="item in quickReplies"
                            :key="item.id"
                        >
                            <button
                                type="button"
                                class="flex w-full items-start gap-4 px-5 py-4 text-left transition hover:bg-gray-50"
                                @click="openEditModal(item)"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-gray-900">
                                            /{{ item.title }}
                                        </p>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                            {{ typeLabels[item.type] || item.type }}
                                        </span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 whitespace-pre-wrap text-sm text-gray-600">
                                        {{ previewText(item) }}
                                    </p>
                                </div>
                                <div
                                    v-if="item.type === 'image' && item.attachment_url"
                                    class="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-gray-200"
                                >
                                    <img
                                        :src="item.attachment_url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    >
                                </div>
                                <div
                                    v-else-if="item.type === 'audio'"
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"
                                >
                                    <svg
                                        class="h-6 w-6"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                                        />
                                    </svg>
                                </div>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Modal
            :show="showCreateModal"
            max-width="lg"
            @close="closeCreateModal"
        >
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    Новый шаблон
                </h3>

                <form
                    class="mt-5 space-y-4"
                    @submit.prevent="submitCreate"
                >
                    <div>
                        <InputLabel value="Тип шаблона" />
                        <select
                            v-model="createForm.type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="text">
                                Текст
                            </option>
                            <option value="audio">
                                Голосовое
                            </option>
                            <option value="image">
                                Картинка
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel
                            for="create-title"
                            value="Название (для /команды)"
                        />
                        <TextInput
                            id="create-title"
                            v-model="createForm.title"
                            class="mt-1 block w-full"
                            placeholder="компофф"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            В чате: /{{ createForm.title || 'название' }}
                        </p>
                        <InputError
                            class="mt-1"
                            :message="createForm.errors.title"
                        />
                    </div>

                    <div v-if="createForm.type === 'text'">
                        <InputLabel
                            for="create-body"
                            value="Текст сообщения"
                        />
                        <textarea
                            id="create-body"
                            v-model="createForm.body"
                            rows="6"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Здравствуйте! Чем могу помочь?"
                        />
                        <InputError
                            class="mt-1"
                            :message="createForm.errors.body"
                        />
                    </div>

                    <div v-else>
                        <InputLabel
                            for="create-attachment"
                            :value="createAttachmentLabel"
                        />
                        <input
                            id="create-attachment"
                            type="file"
                            class="mt-1 block w-full text-sm text-gray-600"
                            :accept="createForm.type === 'audio' ? 'audio/*' : 'image/*'"
                            @change="onCreateAttachmentChange"
                        >
                        <InputError
                            class="mt-1"
                            :message="createForm.errors.attachment"
                        />

                        <div class="mt-4">
                            <InputLabel
                                for="create-caption"
                                value="Подпись (необязательно)"
                            />
                            <textarea
                                id="create-caption"
                                v-model="createForm.body"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton
                            type="button"
                            @click="closeCreateModal"
                        >
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton :disabled="createForm.processing">
                            Сохранить
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showEditModal"
            max-width="lg"
            @close="closeEditModal"
        >
            <div
                v-if="selectedItem"
                class="p-6"
            >
                <h3 class="text-lg font-semibold text-gray-900">
                    /{{ selectedItem.title }}
                </h3>

                <form
                    class="mt-5 space-y-4"
                    @submit.prevent="submitEdit"
                >
                    <div>
                        <InputLabel value="Тип шаблона" />
                        <select
                            v-model="editForm.type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="text">
                                Текст
                            </option>
                            <option value="audio">
                                Голосовое
                            </option>
                            <option value="image">
                                Картинка
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel value="Название (для /команды)" />
                        <TextInput
                            v-model="editForm.title"
                            class="mt-1 block w-full"
                        />
                        <InputError
                            class="mt-1"
                            :message="editForm.errors.title"
                        />
                    </div>

                    <div v-if="editForm.type === 'text'">
                        <InputLabel value="Текст сообщения" />
                        <textarea
                            v-model="editForm.body"
                            rows="6"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError
                            class="mt-1"
                            :message="editForm.errors.body"
                        />
                    </div>

                    <div v-else>
                        <div
                            v-if="selectedItem.attachment_url"
                            class="rounded-lg border border-gray-200 bg-gray-50 p-3"
                        >
                            <img
                                v-if="selectedItem.type === 'image'"
                                :src="selectedItem.attachment_url"
                                alt=""
                                class="max-h-40 rounded-md"
                            >
                            <audio
                                v-else-if="selectedItem.type === 'audio'"
                                :src="selectedItem.attachment_url"
                                controls
                                class="w-full"
                            />
                        </div>

                        <div class="mt-4">
                            <InputLabel
                                for="edit-attachment"
                                :value="editAttachmentLabel"
                            />
                            <input
                                id="edit-attachment"
                                type="file"
                                class="mt-1 block w-full text-sm text-gray-600"
                                :accept="editForm.type === 'audio' ? 'audio/*' : 'image/*'"
                                @change="onEditAttachmentChange"
                            >
                            <InputError
                                class="mt-1"
                                :message="editForm.errors.attachment"
                            />
                        </div>

                        <div class="mt-4">
                            <InputLabel value="Подпись (необязательно)" />
                            <textarea
                                v-model="editForm.body"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <DangerButton
                            type="button"
                            @click="removeSelected"
                        >
                            Удалить
                        </DangerButton>
                        <div class="flex gap-3">
                            <SecondaryButton
                                type="button"
                                @click="closeEditModal"
                            >
                                Отмена
                            </SecondaryButton>
                            <PrimaryButton :disabled="editForm.processing">
                                Сохранить
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
