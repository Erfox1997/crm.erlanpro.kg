<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    quickReplies: {
        type: Array,
        default: () => [],
    },
});

const editingId = ref(null);

const createForm = useForm({
    title: '',
    body: '',
});

const editForm = useForm({
    title: '',
    body: '',
});

function startEdit(item) {
    editingId.value = item.id;
    editForm.title = item.title;
    editForm.body = item.body;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function submitCreate() {
    createForm.post(route('messenger.quick-replies.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function submitEdit(id) {
    editForm.put(route('messenger.quick-replies.update', id), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function remove(id) {
    if (! window.confirm('Удалить этот быстрый ответ?')) {
        return;
    }

    useForm({}).delete(route('messenger.quick-replies.destroy', id), {
        preserveScroll: true,
    });
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
                        Шаблоны для быстрого ответа в мессенджере — как в WhatsApp Business.
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

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800">
                        Новый шаблон
                    </h3>
                    <form
                        class="mt-4 space-y-4"
                        @submit.prevent="submitCreate"
                    >
                        <div>
                            <InputLabel
                                for="create-title"
                                value="Название (короткая метка)"
                            />
                            <TextInput
                                id="create-title"
                                v-model="createForm.title"
                                class="mt-1 block w-full"
                                placeholder="Например: Приветствие"
                            />
                            <InputError
                                class="mt-1"
                                :message="createForm.errors.title"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="create-body"
                                value="Текст сообщения"
                            />
                            <textarea
                                id="create-body"
                                v-model="createForm.body"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Здравствуйте! Чем могу помочь?"
                            />
                            <InputError
                                class="mt-1"
                                :message="createForm.errors.body"
                            />
                        </div>
                        <PrimaryButton :disabled="createForm.processing">
                            Добавить
                        </PrimaryButton>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-800">
                            Ваши шаблоны ({{ quickReplies.length }})
                        </h3>
                    </div>

                    <div
                        v-if="quickReplies.length === 0"
                        class="px-5 py-10 text-center text-sm text-gray-500"
                    >
                        Пока нет шаблонов. Добавьте первый выше.
                    </div>

                    <ul
                        v-else
                        class="divide-y divide-gray-100"
                    >
                        <li
                            v-for="item in quickReplies"
                            :key="item.id"
                            class="px-5 py-4"
                        >
                            <form
                                v-if="editingId === item.id"
                                class="space-y-3"
                                @submit.prevent="submitEdit(item.id)"
                            >
                                <TextInput
                                    v-model="editForm.title"
                                    class="block w-full"
                                />
                                <textarea
                                    v-model="editForm.body"
                                    rows="4"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <div class="flex gap-2">
                                    <PrimaryButton :disabled="editForm.processing">
                                        Сохранить
                                    </PrimaryButton>
                                    <button
                                        type="button"
                                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700"
                                        @click="cancelEdit"
                                    >
                                        Отмена
                                    </button>
                                </div>
                            </form>

                            <div v-else>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ item.title }}
                                        </p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-600">
                                            {{ item.body }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <button
                                            type="button"
                                            class="text-sm text-indigo-600 hover:underline"
                                            @click="startEdit(item)"
                                        >
                                            Изменить
                                        </button>
                                        <button
                                            type="button"
                                            class="text-sm text-red-600 hover:underline"
                                            @click="remove(item.id)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
