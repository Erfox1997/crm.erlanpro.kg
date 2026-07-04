<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    instagramConnected: {
        type: Boolean,
        default: false,
    },
    facebookConnected: {
        type: Boolean,
        default: false,
    },
    instagramAccount: {
        type: Object,
        default: null,
    },
    facebookAccount: {
        type: Object,
        default: null,
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    selectedConversation: {
        type: Object,
        default: null,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    webhookUrl: {
        type: String,
        default: '',
    },
});

const messagesEnd = ref(null);
const syncing = ref(false);

const sendForm = useForm({
    body: '',
});

const messengerConnected = computed(() => props.instagramConnected || props.facebookConnected);

const accountSummary = computed(() => {
    const parts = [];

    if (props.instagramConnected && props.instagramAccount) {
        parts.push(`Instagram: @${props.instagramAccount.username || props.instagramAccount.name || '—'}`);
    }

    if (props.facebookConnected && props.facebookAccount) {
        parts.push(`Facebook: ${props.facebookAccount.page_name || props.facebookAccount.page_id || '—'}`);
    }

    return parts.join(' · ');
});

function channelBadgeClass(channel) {
    return channel === 'facebook'
        ? 'bg-blue-100 text-blue-800'
        : 'bg-pink-100 text-pink-800';
}

function openConversation(id) {
    router.get(
        route('messenger.index'),
        { conversation: id },
        { preserveState: true, preserveScroll: true },
    );
}

function syncConversations() {
    syncing.value = true;
    router.post(route('messenger.sync'), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
    });
}

function sendMessage() {
    if (!props.selectedConversation) {
        return;
    }

    sendForm.post(
        route('messenger.send', props.selectedConversation.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                sendForm.reset('body');
                scrollToBottom();
            },
        },
    );
}

function formatTime(iso) {
    if (!iso) {
        return '';
    }
    return new Date(iso).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function participantLabel(conversation) {
    return (
        conversation.participant_name
        || conversation.participant_username
        || conversation.participant_id
    );
}

function attachmentLabel(type) {
    switch (type) {
    case 'audio':
        return 'Голосовое сообщение';
    case 'image':
        return 'Фото';
    case 'video':
        return 'Видео';
    case 'file':
        return 'Файл';
    default:
        return 'Вложение';
    }
}

function messageHasContent(message) {
    return Boolean(message.body?.trim())
        || (Array.isArray(message.attachments) && message.attachments.length > 0);
}

function scrollToBottom() {
    nextTick(() => {
        messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
    });
}

watch(
    () => props.messages,
    () => scrollToBottom(),
    { immediate: true },
);
</script>

<template>
    <Head title="Мессенджер" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        Мессенджер
                    </h2>
                    <p
                        v-if="messengerConnected && accountSummary"
                        class="mt-1 text-sm text-gray-500"
                    >
                        {{ accountSummary }}
                    </p>
                    <p
                        v-else
                        class="mt-1 text-sm text-gray-500"
                    >
                        Подключите Instagram или Facebook в интеграциях.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton
                        v-if="messengerConnected"
                        type="button"
                        :disabled="syncing"
                        @click="syncConversations"
                    >
                        {{ syncing ? 'Обновление…' : 'Обновить' }}
                    </SecondaryButton>
                    <Link
                        v-if="!instagramConnected"
                        :href="route('integrations.index')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        Подключить Instagram
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div
                v-if="$page.props.errors?.sync"
                class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ $page.props.errors.sync }}
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="grid min-h-[32rem] grid-cols-1 lg:grid-cols-[18rem_1fr]">
                    <aside class="border-b border-gray-200 lg:border-b-0 lg:border-r">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Диалоги
                            </p>
                        </div>

                        <div
                            v-if="!messengerConnected"
                            class="px-4 py-8 text-center text-sm text-gray-500"
                        >
                            Сначала подключите Instagram или Facebook в
                            <Link
                                :href="route('integrations.index')"
                                class="text-pink-600 hover:underline"
                            >
                                интеграциях
                            </Link>.
                        </div>

                        <div
                            v-else-if="conversations.length === 0"
                            class="px-4 py-8 text-center text-sm text-gray-500"
                        >
                            Диалогов пока нет. Нажмите «Обновить» или дождитесь
                            входящего сообщения.
                        </div>

                        <ul
                            v-else
                            class="divide-y divide-gray-100"
                        >
                            <li
                                v-for="conversation in conversations"
                                :key="conversation.id"
                            >
                                <button
                                    type="button"
                                    class="flex w-full flex-col gap-1 px-4 py-3 text-left transition hover:bg-gray-50"
                                    :class="{
                                        'bg-pink-50': selectedConversation?.id === conversation.id,
                                    }"
                                    @click="openConversation(conversation.id)"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                            :class="channelBadgeClass(conversation.channel)"
                                        >
                                            {{ conversation.channel_label }}
                                        </span>
                                        <span class="truncate text-sm font-medium text-gray-900">
                                            {{ participantLabel(conversation) }}
                                        </span>
                                    </div>
                                    <span
                                        v-if="conversation.preview"
                                        class="truncate text-xs text-gray-500"
                                    >
                                        {{ conversation.preview }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">
                                        {{ formatTime(conversation.last_message_at) }}
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </aside>

                    <section class="flex min-h-[32rem] flex-col">
                        <div
                            v-if="!selectedConversation"
                            class="flex flex-1 items-center justify-center px-6 text-center text-sm text-gray-500"
                        >
                            Выберите диалог слева или обновите список.
                        </div>

                        <template v-else>
                            <div class="border-b border-gray-100 px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="selectedConversation.channel"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                        :class="channelBadgeClass(selectedConversation.channel)"
                                    >
                                        {{ selectedConversation.channel_label }}
                                    </span>
                                    <p class="font-medium text-gray-900">
                                        {{ selectedConversation.participant_name
                                            || selectedConversation.participant_username
                                            || selectedConversation.participant_id }}
                                    </p>
                                </div>
                                <p
                                    v-if="selectedConversation.participant_username"
                                    class="text-xs text-gray-500"
                                >
                                    @{{ selectedConversation.participant_username }}
                                </p>
                            </div>

                            <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                                <div
                                    v-if="messages.length === 0"
                                    class="py-8 text-center text-sm text-gray-500"
                                >
                                    Сообщений пока нет.
                                </div>

                                <div
                                    v-for="message in messages"
                                    :key="message.id"
                                    class="flex"
                                    :class="message.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                                >
                                    <div
                                        class="max-w-[80%] rounded-2xl px-4 py-2 text-sm"
                                        :class="message.direction === 'outbound'
                                            ? 'bg-pink-600 text-white'
                                            : 'bg-gray-100 text-gray-900'"
                                    >
                                        <p
                                            v-if="message.body?.trim()"
                                            class="whitespace-pre-wrap break-words"
                                        >
                                            {{ message.body }}
                                        </p>

                                        <div
                                            v-if="message.attachments?.length"
                                            class="space-y-2"
                                            :class="{ 'mt-2': message.body?.trim() }"
                                        >
                                            <div
                                                v-for="(attachment, index) in message.attachments"
                                                :key="`${message.id}-${index}`"
                                            >
                                                <audio
                                                    v-if="attachment.type === 'audio' && attachment.url"
                                                    controls
                                                    preload="metadata"
                                                    class="max-w-full min-w-[14rem]"
                                                    :src="attachment.url"
                                                >
                                                    {{ attachmentLabel(attachment.type) }}
                                                </audio>

                                                <p
                                                    v-else-if="attachment.type === 'audio'"
                                                    class="text-xs opacity-80"
                                                >
                                                    🎤 {{ attachmentLabel(attachment.type) }}
                                                </p>

                                                <img
                                                    v-else-if="attachment.type === 'image'"
                                                    :src="attachment.url"
                                                    :alt="attachment.name || attachmentLabel(attachment.type)"
                                                    class="max-h-64 max-w-full rounded-lg object-contain"
                                                >

                                                <video
                                                    v-else-if="attachment.type === 'video'"
                                                    controls
                                                    preload="metadata"
                                                    class="max-h-64 max-w-full rounded-lg"
                                                    :src="attachment.url"
                                                />

                                                <a
                                                    v-else
                                                    :href="attachment.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-1 underline"
                                                    :class="message.direction === 'outbound'
                                                        ? 'text-white'
                                                        : 'text-pink-700'"
                                                >
                                                    {{ attachment.name || attachmentLabel(attachment.type) }}
                                                </a>
                                            </div>
                                        </div>

                                        <p
                                            v-if="!messageHasContent(message)"
                                            class="opacity-70"
                                        >
                                            —
                                        </p>

                                        <p
                                            class="mt-1 text-[11px] opacity-70"
                                        >
                                            {{ formatTime(message.sent_at) }}
                                        </p>
                                    </div>
                                </div>
                                <div ref="messagesEnd" />
                            </div>

                            <form
                                class="border-t border-gray-100 px-5 py-4"
                                @submit.prevent="sendMessage"
                            >
                                <div class="flex gap-2">
                                    <TextInput
                                        v-model="sendForm.body"
                                        class="block w-full"
                                        placeholder="Напишите сообщение…"
                                        :disabled="sendForm.processing"
                                    />
                                    <PrimaryButton
                                        type="submit"
                                        :disabled="sendForm.processing || !sendForm.body.trim()"
                                    >
                                        Отправить
                                    </PrimaryButton>
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="sendForm.errors.body"
                                />
                            </form>
                        </template>
                    </section>
                </div>
            </div>

            <p
                v-if="messengerConnected && webhookUrl"
                class="mt-4 text-xs text-gray-400"
            >
                Webhook для Meta (продакшен): {{ webhookUrl }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
