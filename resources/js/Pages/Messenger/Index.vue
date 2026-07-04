<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
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
const searchQuery = ref('');

const sendForm = useForm({
    body: '',
    audio: null,
});

const isRecording = ref(false);
const recordingSeconds = ref(0);
let mediaRecorder = null;
let recordingStream = null;
let recordingTimer = null;
let audioChunks = [];

const messengerConnected = computed(() => props.instagramConnected || props.facebookConnected);

const filteredConversations = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    if (!q) {
        return props.conversations;
    }

    return props.conversations.filter((conversation) => {
        const haystack = [
            conversation.participant_name,
            conversation.participant_username,
            conversation.participant_id,
            conversation.preview,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(q);
    });
});

function channelBadgeClass(channel) {
    return channel === 'facebook'
        ? 'bg-[#1877f2] text-white'
        : 'bg-gradient-to-br from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] text-white';
}

function avatarClass(channel) {
    return channel === 'facebook'
        ? 'bg-[#1877f2]'
        : 'bg-gradient-to-br from-pink-500 to-purple-600';
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
    if (!props.selectedConversation || !sendForm.body.trim()) {
        return;
    }

    sendForm.transform((data) => ({
        body: data.body,
        audio: null,
    })).post(
        route('messenger.send', props.selectedConversation.id),
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                sendForm.reset('body', 'audio');
                scrollToBottom();
            },
        },
    );
}

function pickRecorderMimeType() {
    const candidates = [
        'audio/mp4',
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
    ];

    return candidates.find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
}

async function startRecording() {
    if (!props.selectedConversation || sendForm.processing || isRecording.value) {
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        window.alert('Запись голоса не поддерживается в этом браузере.');
        return;
    }

    try {
        recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = pickRecorderMimeType();
        audioChunks = [];
        mediaRecorder = mimeType
            ? new MediaRecorder(recordingStream, { mimeType })
            : new MediaRecorder(recordingStream);

        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = () => {
            stopRecordingTracks();
            sendVoiceMessage();
        };

        mediaRecorder.start();
        isRecording.value = true;
        recordingSeconds.value = 0;
        recordingTimer = window.setInterval(() => {
            recordingSeconds.value += 1;
        }, 1000);
    } catch {
        stopRecordingTracks();
        window.alert('Не удалось получить доступ к микрофону.');
    }
}

function stopRecordingTracks() {
    recordingStream?.getTracks().forEach((track) => track.stop());
    recordingStream = null;
}

function stopRecording() {
    if (!isRecording.value || !mediaRecorder) {
        return;
    }

    window.clearInterval(recordingTimer);
    recordingTimer = null;
    isRecording.value = false;

    if (mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
}

function sendVoiceMessage() {
    if (!props.selectedConversation || audioChunks.length === 0) {
        return;
    }

    const mimeType = mediaRecorder?.mimeType || audioChunks[0]?.type || 'audio/webm';
    const extension = mimeType.includes('mp4') ? 'm4a' : 'webm';
    const blob = new Blob(audioChunks, { type: mimeType });
    const file = new File([blob], `voice.${extension}`, { type: mimeType });

    sendForm.transform(() => ({
        body: '',
        audio: file,
    })).post(
        route('messenger.send', props.selectedConversation.id),
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                sendForm.reset('body', 'audio');
                audioChunks = [];
                mediaRecorder = null;
                scrollToBottom();
            },
            onFinish: () => {
                audioChunks = [];
                mediaRecorder = null;
            },
        },
    );
}

function formatRecordingTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;

    return `${mins}:${String(secs).padStart(2, '0')}`;
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

function formatListTime(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const now = new Date();
    const isToday = date.toDateString() === now.toDateString();

    if (isToday) {
        return date.toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
    });
}

function formatMessageTime(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleTimeString('ru-RU', {
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

function avatarInitials(conversation) {
    const label = participantLabel(conversation) ?? '?';

    if (label.startsWith('+') || /^\d/.test(label)) {
        return label.slice(-2);
    }

    return label
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || '?';
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
        <div
            v-if="$page.props.errors?.sync"
            class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
        >
            {{ $page.props.errors.sync }}
        </div>

        <div
            class="flex h-[calc(100dvh-4.25rem)] min-h-[32rem] overflow-hidden rounded-xl border border-[#d1d7db] bg-white shadow-sm"
        >
            <!-- Список чатов -->
            <aside
                class="flex w-full flex-col border-[#d1d7db] bg-white lg:w-[360px] lg:shrink-0 lg:border-r"
                :class="selectedConversation ? 'hidden lg:flex' : 'flex'"
            >
                <div
                    class="flex items-center justify-between gap-3 bg-[#f0f2f5] px-4 py-3"
                >
                    <h2 class="text-lg font-semibold text-[#111b21]">
                        Чаты
                    </h2>
                    <button
                        v-if="messengerConnected"
                        type="button"
                        class="rounded-full p-2 text-[#54656f] transition hover:bg-[#e9edef]"
                        :disabled="syncing"
                        title="Обновить"
                        @click="syncConversations"
                    >
                        <svg
                            class="h-5 w-5"
                            :class="{ 'animate-spin': syncing }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                            />
                        </svg>
                    </button>
                </div>

                <div class="border-b border-[#e9edef] px-3 py-2">
                    <div class="relative">
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8696a0]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"
                            />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Поиск или новый чат"
                            class="w-full rounded-lg border-0 bg-[#f0f2f5] py-2 pl-10 pr-3 text-sm text-[#111b21] placeholder:text-[#8696a0] focus:ring-2 focus:ring-[#00a884]/30"
                        />
                    </div>
                </div>

                <div
                    v-if="!messengerConnected"
                    class="flex flex-1 items-center justify-center px-6 text-center text-sm text-[#667781]"
                >
                    Подключите Instagram или Facebook в
                    <Link
                        :href="route('integrations.index')"
                        class="ml-1 text-[#00a884] hover:underline"
                    >
                        интеграциях
                    </Link>.
                </div>

                <div
                    v-else-if="conversations.length === 0"
                    class="flex flex-1 items-center justify-center px-6 text-center text-sm text-[#667781]"
                >
                    Диалогов пока нет. Нажмите обновить или дождитесь
                    входящего сообщения.
                </div>

                <ul
                    v-else
                    class="flex-1 overflow-y-auto"
                >
                    <li
                        v-for="conversation in filteredConversations"
                        :key="conversation.id"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-[#f5f6f6]"
                            :class="{
                                'bg-[#f0f2f5]': selectedConversation?.id === conversation.id,
                            }"
                            @click="openConversation(conversation.id)"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                                :class="avatarClass(conversation.channel)"
                            >
                                {{ avatarInitials(conversation) }}
                            </div>

                            <div class="min-w-0 flex-1 border-b border-[#f0f2f5] pb-3">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-[16px] text-[#111b21]">
                                        {{ participantLabel(conversation) }}
                                    </p>
                                    <span
                                        class="shrink-0 text-xs text-[#667781]"
                                    >
                                        {{ formatListTime(conversation.last_message_at) }}
                                    </span>
                                </div>
                                <div
                                    class="mt-0.5 flex items-center gap-2"
                                >
                                    <span
                                        class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                        :class="channelBadgeClass(conversation.channel)"
                                    >
                                        {{ conversation.channel_label }}
                                    </span>
                                    <p
                                        v-if="conversation.preview"
                                        class="truncate text-sm text-[#667781]"
                                    >
                                        {{ conversation.preview }}
                                    </p>
                                </div>
                            </div>
                        </button>
                    </li>

                    <li
                        v-if="filteredConversations.length === 0"
                        class="px-4 py-8 text-center text-sm text-[#667781]"
                    >
                        Ничего не найдено
                    </li>
                </ul>
            </aside>

            <!-- Окно чата -->
            <section
                class="flex min-w-0 flex-1 flex-col bg-[#efeae2]"
                :class="!selectedConversation ? 'hidden lg:flex' : 'flex'"
            >
                <div
                    v-if="!selectedConversation"
                    class="hidden flex-1 flex-col items-center justify-center border-b border-[#d1d7db] bg-[#f0f2f5] text-center lg:flex"
                >
                    <div
                        class="mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-[#e9edef] text-4xl"
                    >
                        💬
                    </div>
                    <h3 class="text-2xl font-light text-[#41525d]">
                        CRM ErlanPro Messenger
                    </h3>
                    <p class="mt-2 max-w-sm text-sm text-[#667781]">
                        Выберите чат слева, чтобы читать и отвечать клиентам.
                    </p>
                </div>

                <template v-else>
                    <div
                        class="flex items-center gap-3 border-b border-[#d1d7db] bg-[#f0f2f5] px-4 py-2.5"
                    >
                        <button
                            type="button"
                            class="rounded-full p-1 text-[#54656f] hover:bg-[#e9edef] lg:hidden"
                            @click="router.get(route('messenger.index'))"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </button>

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                            :class="avatarClass(selectedConversation.channel)"
                        >
                            {{ avatarInitials(selectedConversation) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-[#111b21]">
                                {{
                                    selectedConversation.participant_name
                                        || selectedConversation.participant_username
                                        || selectedConversation.participant_id
                                }}
                            </p>
                            <p
                                v-if="selectedConversation.participant_username"
                                class="truncate text-xs text-[#667781]"
                            >
                                @{{ selectedConversation.participant_username }}
                            </p>
                        </div>

                        <span
                            class="hidden rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase sm:inline"
                            :class="channelBadgeClass(selectedConversation.channel)"
                        >
                            {{ selectedConversation.channel_label }}
                        </span>
                    </div>

                    <div
                        class="flex-1 space-y-1 overflow-y-auto bg-[#efeae2] px-4 py-3"
                        style="background-image: url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23d9d0c3%22 fill-opacity=%220.35%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z/%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"
                    >
                        <div
                            v-if="messages.length === 0"
                            class="py-12 text-center text-sm text-[#667781]"
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
                                class="relative max-w-[min(100%,28rem)] rounded-lg px-3 py-2 text-sm shadow-sm"
                                :class="message.direction === 'outbound'
                                    ? 'rounded-tr-none bg-[#d9fdd3]'
                                    : 'rounded-tl-none bg-white'"
                            >
                                <p
                                    v-if="message.body?.trim()"
                                    class="whitespace-pre-wrap break-words text-[#111b21]"
                                >
                                    {{ message.body }}
                                </p>

                                <div
                                    v-if="message.attachments?.length"
                                    class="space-y-2"
                                    :class="{ 'mt-1': message.body?.trim() }"
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
                                            class="text-xs text-[#667781]"
                                        >
                                            🎤 {{ attachmentLabel(attachment.type) }}
                                        </p>

                                        <img
                                            v-else-if="attachment.type === 'image'"
                                            :src="attachment.url"
                                            :alt="attachment.name || attachmentLabel(attachment.type)"
                                            class="max-h-72 max-w-full rounded-md object-contain"
                                        >

                                        <video
                                            v-else-if="attachment.type === 'video'"
                                            controls
                                            preload="metadata"
                                            class="max-h-72 max-w-full rounded-md"
                                            :src="attachment.url"
                                        />

                                        <a
                                            v-else
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-[#027eb5] underline"
                                        >
                                            {{ attachment.name || attachmentLabel(attachment.type) }}
                                        </a>
                                    </div>
                                </div>

                                <p
                                    v-if="!messageHasContent(message)"
                                    class="text-[#667781]"
                                >
                                    —
                                </p>

                                <div
                                    class="mt-1 flex items-end justify-end gap-1 text-[11px] text-[#667781]"
                                >
                                    <span>{{ formatMessageTime(message.sent_at) }}</span>
                                    <span
                                        v-if="message.direction === 'outbound'"
                                        class="text-[#53bdeb]"
                                    >
                                        ✓✓
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div ref="messagesEnd" />
                    </div>

                    <form
                        class="bg-[#f0f2f5] px-4 py-3"
                        @submit.prevent="sendMessage"
                    >
                        <div
                            v-if="isRecording"
                            class="mb-2 flex items-center justify-between rounded-lg bg-white px-4 py-2 text-sm text-[#111b21] shadow-sm"
                        >
                            <span class="flex items-center gap-2">
                                <span class="inline-flex h-2.5 w-2.5 animate-pulse rounded-full bg-red-500" />
                                Запись {{ formatRecordingTime(recordingSeconds) }}
                            </span>
                            <button
                                type="button"
                                class="rounded-full bg-[#00a884] px-3 py-1 text-xs font-medium text-white"
                                @click="stopRecording"
                            >
                                Отправить
                            </button>
                        </div>

                        <div class="flex items-end gap-2">
                            <input
                                v-model="sendForm.body"
                                type="text"
                                placeholder="Введите сообщение"
                                class="flex-1 rounded-lg border-0 bg-white px-4 py-2.5 text-sm text-[#111b21] shadow-sm placeholder:text-[#8696a0] focus:ring-2 focus:ring-[#00a884]/30"
                                :disabled="sendForm.processing || isRecording"
                            />

                            <button
                                v-if="sendForm.body.trim()"
                                type="submit"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#00a884] text-white transition hover:bg-[#008f6f] disabled:opacity-40"
                                :disabled="sendForm.processing || isRecording"
                                title="Отправить"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.682 13.623 1.816-13.623 1.817-.011 7.682z"
                                    />
                                </svg>
                            </button>

                            <button
                                v-else
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-[#54656f] transition hover:bg-[#e9edef] disabled:opacity-40"
                                :class="isRecording ? 'bg-red-50 text-red-600' : ''"
                                :disabled="sendForm.processing"
                                title="Голосовое сообщение"
                                @click="isRecording ? stopRecording() : startRecording()"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 18.75a4.5 4.5 0 004.5-4.5V9a4.5 4.5 00-9 0v5.25a4.5 4.5 004.5 4.5z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8.25 18.75v1.125c0 .621.504 1.125 1.125 1.125h5.25c.621 0 1.125-.504 1.125-1.125V18.75"
                                    />
                                </svg>
                            </button>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="sendForm.errors.body"
                        />
                    </form>
                </template>
            </section>
        </div>

        <p
            v-if="messengerConnected && webhookUrl"
            class="mt-2 text-xs text-slate-400"
        >
            Webhook для Meta: {{ webhookUrl }}
        </p>
    </AuthenticatedLayout>
</template>
