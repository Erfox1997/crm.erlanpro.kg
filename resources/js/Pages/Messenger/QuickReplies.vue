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
const searchQuery = ref('');

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

const typeMeta = {
    text: {
        label: 'Текст',
        badge: 'bg-violet-100 text-violet-700 ring-violet-200',
        iconWrap: 'bg-violet-100 text-violet-600',
        icon: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z',
        card: 'from-violet-500/10 to-indigo-500/5 border-violet-100',
        dot: 'bg-violet-500',
    },
    audio: {
        label: 'Голос',
        badge: 'bg-sky-100 text-sky-700 ring-sky-200',
        iconWrap: 'bg-sky-100 text-sky-600',
        icon: 'M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z',
        card: 'from-sky-500/10 to-cyan-500/5 border-sky-100',
        dot: 'bg-sky-500',
    },
    image: {
        label: 'Картинка',
        badge: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        iconWrap: 'bg-emerald-100 text-emerald-600',
        icon: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z',
        card: 'from-emerald-500/10 to-teal-500/5 border-emerald-100',
        dot: 'bg-emerald-500',
    },
};

const stats = computed(() => ({
    total: props.quickReplies.length,
    text: props.quickReplies.filter((item) => item.type === 'text').length,
    audio: props.quickReplies.filter((item) => item.type === 'audio').length,
    image: props.quickReplies.filter((item) => item.type === 'image').length,
}));

const filteredQuickReplies = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.quickReplies;
    }

    return props.quickReplies.filter((item) => {
        const haystack = [
            item.title,
            item.body,
            typeMeta[item.type]?.label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

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

function metaFor(type) {
    return typeMeta[type] || typeMeta.text;
}

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

    return item.body || 'Изображение без подписи';
}

function deleteItem(item) {
    if (!window.confirm(`Удалить шаблон «/${item.title}»?`)) {
        return;
    }

    useForm({}).delete(route('messenger.quick-replies.destroy', item.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="CRM" />

    <AuthenticatedLayout>
        <div class="py-5 sm:py-6">
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex justify-end">
                    <div class="flex items-stretch overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                            <div>
                                <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Всего</p>
                                <p class="text-sm font-bold text-slate-900">{{ stats.total }}</p>
                            </div>
                        </div>
                        <div class="w-px bg-slate-200" />
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <svg class="h-4 w-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                            </svg>
                            <div>
                                <p class="text-[10px] font-medium uppercase tracking-wide text-violet-500">Текст</p>
                                <p class="text-sm font-bold text-slate-900">{{ stats.text }}</p>
                            </div>
                        </div>
                        <div class="w-px bg-slate-200" />
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <svg class="h-4 w-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                            <div>
                                <p class="text-[10px] font-medium uppercase tracking-wide text-sky-500">Голос</p>
                                <p class="text-sm font-bold text-slate-900">{{ stats.audio }}</p>
                            </div>
                        </div>
                        <div class="w-px bg-slate-200" />
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                            </svg>
                            <div>
                                <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-500">Картинка</p>
                                <p class="text-sm font-bold text-slate-900">{{ stats.image }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="$page.props.flash?.success"
                    class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        ✓
                    </span>
                    {{ $page.props.flash.success }}
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
                                    placeholder="Поиск по названию или тексту..."
                                    class="w-full rounded-xl border-slate-200 bg-white py-2 pl-9 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-400 focus:ring-violet-400"
                                >
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    :href="route('messenger.quick-replies.sample')"
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
                                    :disabled="importForm.processing"
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
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-500"
                                    @click="openCreateModal"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Создать шаблон
                                </button>
                            </div>
                        </div>

                        <InputError class="mt-2" :message="importForm.errors.file" />
                    </div>

                    <div
                        v-if="quickReplies.length === 0"
                        class="px-6 py-16 text-center"
                    >
                        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-violet-100 to-indigo-100 text-indigo-600">
                            <svg
                                class="h-10 w-10"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM8.625 12h7.5M8.625 15h4.125M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <h3 class="mt-6 text-lg font-semibold text-slate-900">
                            Пока нет шаблонов
                        </h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
                            Создайте первый шаблон или загрузите готовые ответы из Excel. В мессенджере они будут доступны через команду «/».
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/20"
                                @click="openCreateModal"
                            >
                                Создать шаблон
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm"
                                @click="triggerImport"
                            >
                                Импорт из Excel
                            </button>
                        </div>
                    </div>

                    <div
                        v-else-if="filteredQuickReplies.length === 0"
                        class="px-6 py-14 text-center"
                    >
                        <p class="text-sm text-slate-500">
                            По запросу «{{ searchQuery }}» ничего не найдено.
                        </p>
                    </div>

                    <div
                        v-else
                        class="divide-y divide-slate-100"
                    >
                        <div
                            v-for="item in filteredQuickReplies"
                            :key="item.id"
                            class="flex flex-col gap-3 px-4 py-3.5 transition hover:bg-slate-50/80 sm:flex-row sm:items-center sm:gap-4 sm:px-5"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                                <span
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                    :class="metaFor(item.type).badge"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="metaFor(item.type).icon" />
                                    </svg>
                                    {{ metaFor(item.type).label }}
                                </span>

                                <p class="shrink-0 font-mono text-sm font-bold text-slate-900">
                                    /{{ item.title }}
                                </p>

                                <div class="min-w-0 flex-1">
                                    <p
                                        v-if="item.type === 'text'"
                                        class="truncate text-sm text-slate-500"
                                    >
                                        {{ previewText(item) }}
                                    </p>

                                    <div
                                        v-else-if="item.type === 'audio'"
                                        class="flex items-center gap-2"
                                    >
                                        <svg class="h-8 w-28 shrink-0 text-sky-400" viewBox="0 0 112 24" fill="currentColor" aria-hidden="true">
                                            <rect x="0" y="10" width="3" height="4" rx="1.5" />
                                            <rect x="6" y="6" width="3" height="12" rx="1.5" />
                                            <rect x="12" y="2" width="3" height="20" rx="1.5" />
                                            <rect x="18" y="8" width="3" height="8" rx="1.5" />
                                            <rect x="24" y="4" width="3" height="16" rx="1.5" />
                                            <rect x="30" y="9" width="3" height="6" rx="1.5" />
                                            <rect x="36" y="1" width="3" height="22" rx="1.5" />
                                            <rect x="42" y="7" width="3" height="10" rx="1.5" />
                                            <rect x="48" y="3" width="3" height="18" rx="1.5" />
                                            <rect x="54" y="8" width="3" height="8" rx="1.5" />
                                            <rect x="60" y="5" width="3" height="14" rx="1.5" />
                                            <rect x="66" y="10" width="3" height="4" rx="1.5" />
                                            <rect x="72" y="2" width="3" height="20" rx="1.5" />
                                            <rect x="78" y="6" width="3" height="12" rx="1.5" />
                                            <rect x="84" y="9" width="3" height="6" rx="1.5" />
                                            <rect x="90" y="4" width="3" height="16" rx="1.5" />
                                            <rect x="96" y="7" width="3" height="10" rx="1.5" />
                                            <rect x="102" y="11" width="3" height="2" rx="1" />
                                            <rect x="108" y="8" width="3" height="8" rx="1.5" />
                                        </svg>
                                        <span class="truncate text-sm text-slate-400">{{ previewText(item) }}</span>
                                    </div>

                                    <div
                                        v-else
                                        class="flex items-center gap-2"
                                    >
                                        <img
                                            v-if="item.attachment_url"
                                            :src="item.attachment_url"
                                            alt=""
                                            class="h-8 w-8 shrink-0 rounded-lg border border-slate-200 object-cover"
                                        >
                                        <p class="truncate text-sm text-slate-500">
                                            {{ previewText(item) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2 sm:ml-auto">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                    @click="openEditModal(item)"
                                >
                                    Редактировать
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                    @click="deleteItem(item)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal
            :show="showCreateModal"
            max-width="lg"
            @close="closeCreateModal"
        >
            <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">
                    Новый шаблон
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Создайте быстрый ответ для мессенджера
                </p>
            </div>

            <form
                class="space-y-5 p-6"
                @submit.prevent="submitCreate"
            >
                <div>
                    <InputLabel value="Тип шаблона" />
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button
                            v-for="(meta, type) in typeMeta"
                            :key="type"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-center text-sm font-medium transition"
                            :class="createForm.type === type
                                ? 'border-indigo-300 bg-indigo-50 text-indigo-700 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                            @click="createForm.type = type"
                        >
                            {{ meta.label }}
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel
                        for="create-title"
                        value="Название команды"
                    />
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-slate-400">/</span>
                        <TextInput
                            id="create-title"
                            v-model="createForm.title"
                            class="block w-full pl-7"
                            placeholder="компофф"
                        />
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">
                        В чате будет доступно как
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700">/{{ createForm.title || 'название' }}</span>
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
                        class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-400 focus:ring-indigo-400"
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
                    <label
                        for="create-attachment"
                        class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center transition hover:border-indigo-300 hover:bg-indigo-50/40"
                    >
                        <svg
                            class="h-8 w-8 text-slate-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                            />
                        </svg>
                        <span class="mt-3 text-sm font-medium text-slate-700">
                            {{ createForm.attachment?.name || 'Нажмите, чтобы выбрать файл' }}
                        </span>
                        <span class="mt-1 text-xs text-slate-500">
                            До 16 МБ
                        </span>
                    </label>
                    <input
                        id="create-attachment"
                        type="file"
                        class="hidden"
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
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-400 focus:ring-indigo-400"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <SecondaryButton
                        type="button"
                        @click="closeCreateModal"
                    >
                        Отмена
                    </SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">
                        Сохранить шаблон
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal
            :show="showEditModal"
            max-width="lg"
            @close="closeEditModal"
        >
            <div
                v-if="selectedItem"
                class="border-b border-slate-100 bg-slate-50/80 px-6 py-5"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="metaFor(selectedItem.type).badge"
                    >
                        {{ metaFor(selectedItem.type).label }}
                    </span>
                    <h3 class="font-mono text-lg font-semibold text-slate-900">
                        /{{ selectedItem.title }}
                    </h3>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Редактирование шаблона
                </p>
            </div>

            <form
                v-if="selectedItem"
                class="space-y-5 p-6"
                @submit.prevent="submitEdit"
            >
                <div>
                    <InputLabel value="Тип шаблона" />
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button
                            v-for="(meta, type) in typeMeta"
                            :key="type"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-center text-sm font-medium transition"
                            :class="editForm.type === type
                                ? 'border-indigo-300 bg-indigo-50 text-indigo-700 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                            @click="editForm.type = type"
                        >
                            {{ meta.label }}
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Название команды" />
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-slate-400">/</span>
                        <TextInput
                            v-model="editForm.title"
                            class="block w-full pl-7"
                        />
                    </div>
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
                        class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-400 focus:ring-indigo-400"
                    />
                    <InputError
                        class="mt-1"
                        :message="editForm.errors.body"
                    />
                </div>

                <div v-else>
                    <div
                        v-if="selectedItem.attachment_url"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"
                    >
                        <img
                            v-if="selectedItem.type === 'image'"
                            :src="selectedItem.attachment_url"
                            alt=""
                            class="max-h-48 w-full object-cover"
                        >
                        <div
                            v-else-if="selectedItem.type === 'audio'"
                            class="p-4"
                        >
                            <audio
                                :src="selectedItem.attachment_url"
                                controls
                                class="w-full"
                            />
                        </div>
                    </div>

                    <div class="mt-4">
                        <InputLabel
                            for="edit-attachment"
                            :value="editAttachmentLabel"
                        />
                        <label
                            for="edit-attachment"
                            class="mt-2 flex cursor-pointer items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50/40"
                        >
                            {{ editForm.attachment?.name || 'Выбрать новый файл' }}
                        </label>
                        <input
                            id="edit-attachment"
                            type="file"
                            class="hidden"
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
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-400 focus:ring-indigo-400"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-5">
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
        </Modal>
    </AuthenticatedLayout>
</template>
