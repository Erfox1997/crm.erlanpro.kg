<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    instagramConnected: {
        type: Boolean,
        default: false,
    },
    instagramAccount: {
        type: Object,
        default: null,
    },
    mediaItems: {
        type: Array,
        default: () => [],
    },
    selectedMedia: {
        type: Object,
        default: null,
    },
    comments: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const syncing = ref(false);
const replyTargetId = ref(null);

const replyForm = useForm({
    body: '',
});

const selectedMediaId = computed(() => props.selectedMedia?.id ?? null);

function selectMedia(id) {
    router.get(route('comments.index'), { media: id }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function syncComments() {
    syncing.value = true;
    router.post(route('comments.sync'), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
    });
}

function openReply(commentId) {
    replyTargetId.value = commentId;
    replyForm.reset();
    replyForm.clearErrors();
}

function cancelReply() {
    replyTargetId.value = null;
    replyForm.reset();
    replyForm.clearErrors();
}

function submitReply(commentId) {
    replyForm.post(route('comments.reply', commentId), {
        preserveScroll: true,
        onSuccess: () => {
            replyTargetId.value = null;
            replyForm.reset();
        },
    });
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function displayName(comment) {
    if (comment.author_username) {
        return `@${comment.author_username}`;
    }

    return comment.author_name || 'Клиент';
}

function captionPreview(caption) {
    if (!caption) {
        return 'Публикация без подписи';
    }

    return caption.length > 80 ? `${caption.slice(0, 80)}…` : caption;
}
</script>

<template>
    <Head title="Комментарии Instagram" />

    <AuthenticatedLayout full-height>
        <div class="flex h-full min-h-0 flex-col">
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">
                        Комментарии Instagram
                    </h1>
                    <p
                        v-if="instagramAccount?.username"
                        class="text-sm text-slate-500"
                    >
                        @{{ instagramAccount.username }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <SecondaryButton
                        :disabled="syncing || !instagramConnected"
                        @click="syncComments"
                    >
                        {{ syncing ? 'Обновление…' : 'Обновить' }}
                    </SecondaryButton>
                    <Link
                        v-if="!instagramConnected"
                        :href="route('integrations.index')"
                        class="text-sm font-medium text-pink-600 hover:text-pink-700"
                    >
                        Подключить Instagram
                    </Link>
                </div>
            </div>

            <div
                v-if="page.props.flash?.success"
                class="shrink-0 border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
            >
                {{ page.props.flash.success }}
            </div>

            <div
                v-if="page.props.errors?.sync"
                class="shrink-0 border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800"
            >
                {{ page.props.errors.sync }}
            </div>

            <div class="flex min-h-0 flex-1 overflow-hidden">
                <aside class="flex w-full shrink-0 flex-col border-r border-slate-200 bg-white lg:w-[360px]">
                    <div class="border-b border-slate-100 px-4 py-3 text-sm font-medium text-slate-700">
                        Публикации
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <button
                            v-for="item in mediaItems"
                            :key="item.id"
                            type="button"
                            class="flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50"
                            :class="selectedMediaId === item.id ? 'bg-pink-50/70' : ''"
                            @click="selectMedia(item.id)"
                        >
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                <img
                                    v-if="item.thumbnail_url"
                                    :src="item.thumbnail_url"
                                    alt=""
                                    class="h-full w-full object-cover"
                                >
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-sm text-slate-800">
                                        {{ captionPreview(item.caption) }}
                                    </p>
                                    <span
                                        v-if="item.unread_count > 0"
                                        class="shrink-0 rounded-full bg-pink-600 px-2 py-0.5 text-xs font-medium text-white"
                                    >
                                        {{ item.unread_count }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ item.comment_count }} коммент.
                                    <span v-if="item.last_comment_at"> · {{ formatDate(item.last_comment_at) }}</span>
                                </p>
                            </div>
                        </button>

                        <div
                            v-if="mediaItems.length === 0"
                            class="px-4 py-8 text-center text-sm text-slate-500"
                        >
                            <template v-if="instagramConnected">
                                Нажмите «Обновить», чтобы загрузить публикации.
                            </template>
                            <template v-else>
                                Подключите Instagram в разделе «Интеграции».
                            </template>
                        </div>
                    </div>
                </aside>

                <section class="hidden min-h-0 min-w-0 flex-1 flex-col bg-slate-50 lg:flex">
                    <template v-if="selectedMedia">
                        <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-4">
                            <div class="flex items-start gap-4">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                    <img
                                        v-if="selectedMedia.thumbnail_url"
                                        :src="selectedMedia.thumbnail_url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    >
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-800">
                                        {{ selectedMedia.caption || 'Публикация без подписи' }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-3 text-xs text-slate-500">
                                        <span v-if="selectedMedia.published_at">
                                            {{ formatDate(selectedMedia.published_at) }}
                                        </span>
                                        <a
                                            v-if="selectedMedia.permalink"
                                            :href="selectedMedia.permalink"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-medium text-pink-600 hover:text-pink-700"
                                        >
                                            Открыть в Instagram
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                            <div
                                v-for="comment in comments"
                                :key="comment.id"
                                class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ displayName(comment) }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ formatDate(comment.sent_at) }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="comment.direction === 'inbound'"
                                        type="button"
                                        class="text-sm font-medium text-pink-600 hover:text-pink-700"
                                        @click="openReply(comment.id)"
                                    >
                                        Ответить
                                    </button>
                                </div>

                                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-800">
                                    {{ comment.body }}
                                </p>

                                <div
                                    v-if="comment.replies?.length"
                                    class="mt-4 space-y-3 border-l-2 border-slate-100 pl-4"
                                >
                                    <div
                                        v-for="reply in comment.replies"
                                        :key="reply.id"
                                        class="rounded-xl bg-slate-50 px-3 py-2"
                                    >
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ displayName(reply) }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ formatDate(reply.sent_at) }}
                                        </p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800">
                                            {{ reply.body }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="replyTargetId === comment.id"
                                    class="mt-4 border-t border-slate-100 pt-4"
                                >
                                    <TextInput
                                        v-model="replyForm.body"
                                        class="w-full"
                                        placeholder="Введите ответ клиенту…"
                                        @keyup.enter.exact="submitReply(comment.id)"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="replyForm.errors.body || page.props.errors?.reply"
                                    />
                                    <div class="mt-3 flex gap-2">
                                        <PrimaryButton
                                            :disabled="replyForm.processing"
                                            @click="submitReply(comment.id)"
                                        >
                                            Отправить
                                        </PrimaryButton>
                                        <SecondaryButton @click="cancelReply">
                                            Отмена
                                        </SecondaryButton>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="comments.length === 0"
                                class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500"
                            >
                                Комментариев пока нет. Нажмите «Обновить», чтобы синхронизировать.
                            </div>
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex flex-1 items-center justify-center px-6 text-sm text-slate-500"
                    >
                        Выберите публикацию слева, чтобы посмотреть комментарии.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
