<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChannelIcon from '@/Components/Messenger/ChannelIcon.vue';
import VoiceMessagePlayer from '@/Components/Messenger/VoiceMessagePlayer.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    instagramConnected: {
        type: Boolean,
        default: false,
    },
    facebookConnected: {
        type: Boolean,
        default: false,
    },
    wappiConnected: {
        type: Boolean,
        default: false,
    },
    telegramConnected: {
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
    quickReplies: {
        type: Array,
        default: () => [],
    },
    wappiAccount: {
        type: Object,
        default: null,
    },
    telegramAccount: {
        type: Object,
        default: null,
    },
    wappiWebhookUrl: {
        type: String,
        default: '',
    },
    webhookUrl: {
        type: String,
        default: '',
    },
    clientFieldDefinitions: {
        type: Array,
        default: () => [],
    },
    messengerFieldKey: {
        type: String,
        default: null,
    },
    linkedClient: {
        type: Object,
        default: null,
    },
    funnelDeal: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const messagesEnd = ref(null);
const syncing = ref(false);
const searchQuery = ref('');
const slashActiveIndex = ref(0);
const messageInput = ref(null);
const imageInput = ref(null);

const sendForm = useForm({
    body: '',
    audio: null,
    image: null,
});

const imagePreviewUrl = ref(null);
const lightboxImageUrl = ref(null);
const showClientModal = ref(false);

const clientForm = useForm({
    fields: {},
});

const dealStageForm = useForm({
    stage_id: '',
});

const isRecording = ref(false);
const recordingSeconds = ref(0);
let mediaRecorder = null;
let recordingStream = null;
let recordingTimer = null;
let audioChunks = [];

const messengerConnected = computed(() => (
    props.instagramConnected
    || props.facebookConnected
    || props.wappiConnected
    || props.telegramConnected
));

const filteredConversations = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    if (!q) {
        return props.conversations;
    }

    return props.conversations.filter((conversation) => {
        const haystack = [
            conversation.display_name,
            conversation.participant_name,
            conversation.participant_username,
            conversation.participant_id,
            conversation.pipeline_name,
            conversation.stage_name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(q);
    });
});

const slashQuickReplyQuery = computed(() => {
    const match = sendForm.body.match(/^\/(\S*)$/);

    return match ? match[1].toLowerCase() : null;
});

const slashQuickRepliesOpen = computed(() => (
    slashQuickReplyQuery.value !== null && props.quickReplies.length > 0
));

const filteredSlashQuickReplies = computed(() => {
    if (slashQuickReplyQuery.value === null) {
        return [];
    }

    const query = slashQuickReplyQuery.value;

    return props.quickReplies
        .filter((reply) => query === '' || reply.title.toLowerCase().includes(query))
        .slice(0, 8);
});

watch(filteredSlashQuickReplies, () => {
    slashActiveIndex.value = 0;
});

function channelBadgeClass(channel) {
    if (channel === 'facebook') {
        return 'bg-[#1877f2] text-white';
    }

    if (channel === 'wappi') {
        return 'bg-[#25D366] text-white';
    }

    if (channel === 'telegram') {
        return 'bg-[#229ED9] text-white';
    }

    return 'bg-gradient-to-br from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] text-white';
}

function avatarClass(channel) {
    if (channel === 'facebook') {
        return 'bg-[#1877f2]';
    }

    if (channel === 'wappi') {
        return 'bg-[#25D366]';
    }

    if (channel === 'telegram') {
        return 'bg-[#229ED9]';
    }

    return 'bg-gradient-to-br from-pink-500 to-purple-600';
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
        onSuccess: () => {
            router.reload();
        },
        onFinish: () => {
            syncing.value = false;
        },
    });
}

function applyQuickReply(reply) {
    if (!props.selectedConversation) {
        return;
    }

    if (reply.type === 'text') {
        sendForm.body = reply.body || '';
        nextTick(() => messageInput.value?.focus());

        return;
    }

    router.post(
        route('messenger.send-quick-reply', [props.selectedConversation.id, reply.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                sendForm.reset('body');
                scrollToBottom();
            },
        },
    );
}

function quickReplyPreview(reply) {
    if (reply.type === 'text') {
        return reply.body;
    }

    if (reply.type === 'audio') {
        return reply.body || '🎤 Голосовое сообщение';
    }

    return reply.body || '🖼 Изображение';
}

function onMessageInputKeydown(event) {
    if (!slashQuickRepliesOpen.value || filteredSlashQuickReplies.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        slashActiveIndex.value = (slashActiveIndex.value + 1) % filteredSlashQuickReplies.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        slashActiveIndex.value = (slashActiveIndex.value - 1 + filteredSlashQuickReplies.value.length)
            % filteredSlashQuickReplies.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        applyQuickReply(filteredSlashQuickReplies.value[slashActiveIndex.value]);
    } else if (event.key === 'Escape') {
        sendForm.body = '';
    }
}

function sendMessage() {
    if (!props.selectedConversation || slashQuickRepliesOpen.value) {
        return;
    }

    if (!sendForm.body.trim() && !sendForm.image) {
        return;
    }

    sendForm.transform((data) => ({
        body: data.body,
        audio: null,
        image: data.image,
    })).post(
        route('messenger.send', props.selectedConversation.id),
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                sendForm.reset('body', 'audio', 'image');
                clearImagePreview();
                scrollToBottom();
            },
        },
    );
}

function onImageSelected(event) {
    const file = event.target.files?.[0] ?? null;

    if (!file) {
        return;
    }

    clearImagePreview();
    sendForm.image = file;
    imagePreviewUrl.value = URL.createObjectURL(file);
}

function clearImagePreview() {
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value);
    }

    imagePreviewUrl.value = null;
    sendForm.image = null;

    if (imageInput.value) {
        imageInput.value.value = '';
    }
}

function openImageLightbox(url) {
    if (!url) {
        return;
    }

    lightboxImageUrl.value = url;
}

function closeImageLightbox() {
    lightboxImageUrl.value = null;
}

onUnmounted(() => {
    clearImagePreview();
});

function prefillClientFieldValue(key) {
    const saved = props.linkedClient?.custom_fields?.[key];
    if (saved !== undefined && saved !== null && String(saved).trim() !== '') {
        return String(saved);
    }

    return apiValueForField(key);
}

const messengerApiName = computed(() => {
    return props.selectedConversation?.participant_name?.trim() || '';
});

const messengerApiContact = computed(() => {
    const conversation = props.selectedConversation;
    if (!conversation) {
        return '';
    }

    const username = (conversation.participant_username || '').trim();
    if (username !== '') {
        return username.startsWith('@') ? username : `@${username}`;
    }

    const participantId = (conversation.participant_id || '').trim();
    if (participantId !== '') {
        if (/^\+?\d/.test(participantId)) {
            return participantId.startsWith('+') ? participantId : `@${participantId}`;
        }

        return participantId;
    }

    return '';
});

function apiValueForField(key) {
    const conversation = props.selectedConversation;
    if (!conversation) {
        return '';
    }

    const normalizedKey = key.toLowerCase();

    if (['name', 'imya', 'fio', 'full_name', 'fullname'].includes(normalizedKey)) {
        return messengerApiName.value;
    }

    if (['phone', 'telefon', 'nomer', 'number', 'tel'].includes(normalizedKey)) {
        return messengerApiContact.value.replace(/^@/, '');
    }

    if (['username', 'login', 'nick'].includes(normalizedKey)) {
        return messengerApiContact.value;
    }

    return '';
}

function applyMessengerNameToField(key) {
    if (messengerApiName.value) {
        clientForm.fields[key] = messengerApiName.value;
    }
}

function applyMessengerContactToField(key) {
    if (messengerApiContact.value) {
        clientForm.fields[key] = messengerApiContact.value;
    }
}

function resetClientForm() {
    const fields = {};

    for (const definition of props.clientFieldDefinitions) {
        fields[definition.key] = prefillClientFieldValue(definition.key);
    }

    clientForm.fields = fields;
    clientForm.clearErrors();
}

function openClientModal() {
    resetClientForm();
    showClientModal.value = true;
}

function saveClientData() {
    if (!props.selectedConversation) {
        return;
    }

    clientForm.post(route('messenger.save-client', props.selectedConversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            showClientModal.value = false;
        },
    });
}

function updateDealStage() {
    if (!props.selectedConversation || !props.funnelDeal || !dealStageForm.stage_id) {
        return;
    }

    if (Number(dealStageForm.stage_id) === props.funnelDeal.stage_id) {
        return;
    }

    dealStageForm.patch(
        route('messenger.update-deal-stage', props.selectedConversation.id),
        {
            preserveScroll: true,
        },
    );
}

watch(
    () => props.funnelDeal?.stage_id,
    (stageId) => {
        dealStageForm.stage_id = stageId ? String(stageId) : '';
        dealStageForm.clearErrors();
    },
    { immediate: true },
);

watch(
    () => [props.selectedConversation?.id, props.linkedClient?.id, props.clientFieldDefinitions],
    () => {
        if (showClientModal.value) {
            resetClientForm();
        }
    },
    { deep: true },
);

function pickRecorderMimeType(channel) {
    const mobileFriendlyTypes = ['audio/mp4', 'audio/aac'];

    if (channel === 'instagram' || channel === 'facebook') {
        const supported = mobileFriendlyTypes.find((type) => MediaRecorder.isTypeSupported(type));

        if (supported) {
            return supported;
        }

        if (channel === 'instagram') {
            return null;
        }
    }

    const candidates = channel === 'wappi' || channel === 'telegram'
        ? [
            'audio/ogg;codecs=opus',
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
        ]
        : [
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
        const mimeType = pickRecorderMimeType(props.selectedConversation.channel);

        if (props.selectedConversation.channel === 'instagram' && ! mimeType) {
            stopRecordingTracks();
            window.alert(
                'Для Instagram нужен формат M4A/MP4. Откройте CRM в Safari или Edge и попробуйте снова.',
            );

            return;
        }

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
    const channel = props.selectedConversation.channel;
    const extension = channel === 'instagram'
        ? (mimeType.includes('wav') ? 'wav' : 'm4a')
        : channel === 'wappi'
            ? (mimeType.includes('ogg') ? 'ogg' : mimeType.includes('mp4') ? 'm4a' : 'webm')
            : (mimeType.includes('mp4') ? 'm4a' : 'webm');
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

function messageDateKey(iso) {
    if (!iso) {
        return 'unknown';
    }

    const date = new Date(iso);

    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function formatMessageDateLabel(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const now = new Date();
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === now.toDateString()) {
        return 'Сегодня';
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return 'Вчера';
    }

    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const messagesWithDateDividers = computed(() => {
    const items = [];
    let lastDateKey = null;

    for (const message of props.messages) {
        const dateKey = messageDateKey(message.sent_at);

        if (dateKey !== lastDateKey) {
            items.push({
                type: 'date',
                key: `date-${dateKey}`,
                label: formatMessageDateLabel(message.sent_at),
            });
            lastDateKey = dateKey;
        }

        items.push({
            type: 'message',
            key: `message-${message.id}`,
            message,
        });
    }

    return items;
});

function participantLabel(conversation) {
    return (
        conversation.display_name
        || conversation.participant_name
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

    <AuthenticatedLayout full-height>
        <div
            v-if="$page.props.errors?.sync"
            class="shrink-0 border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
        >
            {{ $page.props.errors.sync }}
        </div>

        <div
            class="flex min-h-0 flex-1 flex-col overflow-hidden p-0 sm:p-3"
        >
        <div
            class="flex min-h-0 flex-1 overflow-hidden rounded-xl border border-[#d1d7db] bg-white shadow-sm"
        >
            <!-- Список чатов -->
            <aside
                class="flex h-full min-h-0 w-full flex-col border-[#d1d7db] bg-white lg:w-[360px] lg:shrink-0 lg:border-r"
                :class="selectedConversation ? 'hidden lg:flex' : 'flex'"
            >
                <div
                    class="flex items-center justify-between gap-3 bg-[#f0f2f5] px-4 py-3"
                >
                    <h2 class="text-lg font-semibold text-[#111b21]">
                        Чаты
                    </h2>
                    <div class="flex items-center gap-1">
                        <Link
                            v-if="messengerConnected"
                            :href="route('messenger.quick-replies.index')"
                            class="rounded-full p-2 text-[#54656f] transition hover:bg-[#e9edef]"
                            title="Быстрые ответы"
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
                                    d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM8.625 12h7.5M8.625 15h4.125M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </Link>
                        <button
                            v-if="messengerConnected"
                            type="button"
                            class="rounded-full p-2 text-[#54656f] transition hover:bg-[#e9edef]"
                            :disabled="syncing"
                            title="Обновить новые сообщения"
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
                    Подключите Instagram, Facebook, WhatsApp или Telegram в
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
                    class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain"
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
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-semibold text-white"
                                    :class="avatarClass(conversation.channel)"
                                >
                                    {{ avatarInitials(conversation) }}
                                </div>
                                <span
                                    v-if="conversation.unread_count > 0"
                                    class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#25d366] px-1 text-[10px] font-bold text-white"
                                >
                                    {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                                </span>
                            </div>

                            <div class="min-w-0 flex-1 border-b border-[#f0f2f5] pb-3">
                                <div class="flex items-start justify-between gap-2">
                                    <p
                                        class="truncate text-[16px]"
                                        :class="conversation.unread_count > 0 ? 'font-semibold text-[#111b21]' : 'text-[#111b21]'"
                                    >
                                        {{ participantLabel(conversation) }}
                                    </p>
                                    <span
                                        class="shrink-0 text-xs"
                                        :class="conversation.unread_count > 0 ? 'font-semibold text-[#00a884]' : 'text-[#667781]'"
                                    >
                                        {{ formatListTime(conversation.last_message_at) }}
                                    </span>
                                </div>
                                <div class="mt-0.5 flex min-w-0 items-center gap-1.5">
                                    <ChannelIcon
                                        :channel="conversation.channel"
                                        :title="conversation.channel_label"
                                    />
                                    <p
                                        v-if="conversation.pipeline_name"
                                        class="flex min-w-0 items-center gap-1 truncate text-sm leading-tight"
                                        :class="conversation.unread_count > 0 ? 'font-medium text-[#111b21]' : 'text-[#667781]'"
                                    >
                                        <span class="truncate">
                                            {{ conversation.pipeline_name }}
                                        </span>
                                        <span
                                            v-if="conversation.stage_name"
                                            class="shrink-0 text-[11px] font-normal text-[#8696a0]"
                                        >
                                            · {{ conversation.stage_name }}
                                        </span>
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
                class="flex h-full min-h-0 min-w-0 flex-1 flex-col bg-[#efeae2]"
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
                    <div class="flex min-h-0 flex-1 flex-col">
                    <div
                        class="flex shrink-0 items-center gap-2 border-b border-[#d1d7db] bg-[#f0f2f5] px-2.5 py-2 sm:gap-3 sm:px-4 sm:py-2.5"
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
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white sm:h-10 sm:w-10 sm:text-sm"
                            :class="avatarClass(selectedConversation.channel)"
                        >
                            {{ avatarInitials(selectedConversation) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-[#111b21] sm:text-base">
                                {{ participantLabel(selectedConversation) }}
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

                        <button
                            type="button"
                            class="shrink-0 rounded-full bg-white px-2 py-1 text-[11px] font-medium text-[#008069] shadow-sm transition hover:bg-[#f0f2f5] sm:px-3 sm:py-1.5 sm:text-xs"
                            @click="openClientModal"
                        >
                            {{ linkedClient ? 'Данные клиента' : 'Сохранить контакт' }}
                        </button>
                    </div>

                    <div
                        v-if="funnelDeal"
                        class="flex shrink-0 flex-wrap items-center gap-2 border-b border-[#d1d7db] bg-[#f7f8fa] px-2.5 py-2 sm:px-4"
                    >
                        <span class="text-xs font-medium text-[#111b21]">
                            {{ funnelDeal.pipeline_name }}
                        </span>
                        <span class="text-xs text-[#667781]">·</span>
                        <select
                            v-model="dealStageForm.stage_id"
                            class="min-w-0 max-w-[12rem] rounded-md border-[#d1d7db] bg-white py-1 pl-2 pr-7 text-xs text-[#111b21] shadow-sm focus:border-[#00a884] focus:ring-[#00a884]"
                            :disabled="dealStageForm.processing"
                            @change="updateDealStage"
                        >
                            <option
                                v-for="stage in funnelDeal.stages"
                                :key="stage.id"
                                :value="String(stage.id)"
                            >
                                {{ stage.name }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-y-contain bg-[#efeae2] px-2 py-2 sm:space-y-1 sm:px-4 sm:py-3"
                        style="background-image: url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23d9d0c3%22 fill-opacity=%220.35%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z/%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"
                    >
                        <div
                            v-if="messages.length === 0"
                            class="py-12 text-center text-sm text-[#667781]"
                        >
                            Сообщений пока нет.
                        </div>

                        <template
                            v-for="item in messagesWithDateDividers"
                            :key="item.key"
                        >
                            <div
                                v-if="item.type === 'date'"
                                class="flex justify-center py-2"
                            >
                                <span class="rounded-lg bg-[#ffffffd9] px-2.5 py-0.5 text-[11px] font-medium text-[#54656f] shadow-sm sm:px-3 sm:text-xs">
                                    {{ item.label }}
                                </span>
                            </div>

                            <div
                                v-else
                                class="flex px-0.5"
                                :class="item.message.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                            >
                            <div
                                class="relative max-w-[82%] rounded-lg px-2 py-1.5 text-[13px] leading-snug shadow-sm sm:max-w-[min(100%,28rem)] sm:px-3 sm:py-2 sm:text-sm"
                                :class="item.message.direction === 'outbound'
                                    ? 'rounded-tr-none bg-[#d9fdd3]'
                                    : 'rounded-tl-none bg-white'"
                            >
                                <p
                                    v-if="item.message.body?.trim()"
                                    class="whitespace-pre-wrap break-words text-[#111b21]"
                                >
                                    {{ item.message.body }}
                                </p>

                                <div
                                    v-if="item.message.attachments?.length"
                                    class="space-y-2"
                                    :class="{ 'mt-1': item.message.body?.trim() }"
                                >
                                    <div
                                        v-for="(attachment, index) in item.message.attachments"
                                        :key="`${item.message.id}-${index}`"
                                    >
                                        <VoiceMessagePlayer
                                            v-if="attachment.type === 'audio' && attachment.url"
                                            :src="attachment.url"
                                            :outbound="item.message.direction === 'outbound'"
                                        />

                                        <p
                                            v-else-if="attachment.type === 'audio'"
                                            class="flex items-center gap-2 text-xs text-[#667781]"
                                        >
                                            <span>🎤</span>
                                            <span>{{ attachmentLabel(attachment.type) }}</span>
                                            <span
                                                v-if="!attachment.url"
                                                class="text-[10px] opacity-70"
                                            >
                                                (нажмите «Обновить» в списке чатов)
                                            </span>
                                        </p>

                                        <img
                                            v-else-if="attachment.type === 'image' && attachment.url"
                                            :src="attachment.url"
                                            :alt="attachment.name || attachmentLabel(attachment.type)"
                                            class="max-h-48 max-w-full cursor-zoom-in rounded-md object-contain transition hover:opacity-90 sm:max-h-72"
                                            @click="openImageLightbox(attachment.url)"
                                        >

                                        <p
                                            v-else-if="attachment.type === 'image'"
                                            class="text-xs text-[#667781]"
                                        >
                                            🖼 {{ attachmentLabel(attachment.type) }}
                                        </p>

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
                                    v-if="!messageHasContent(item.message)"
                                    class="text-[#667781]"
                                >
                                    —
                                </p>

                                <div
                                    class="mt-0.5 flex items-end justify-end gap-1 text-[10px] text-[#667781] sm:mt-1 sm:text-[11px]"
                                >
                                    <span>{{ formatMessageTime(item.message.sent_at) }}</span>
                                    <span
                                        v-if="item.message.direction === 'outbound'"
                                        class="text-[#53bdeb]"
                                    >
                                        ✓✓
                                    </span>
                                </div>
                            </div>
                            </div>
                        </template>
                        <div ref="messagesEnd" />
                    </div>

                    <form
                        class="shrink-0 bg-[#f0f2f5] px-2 py-2 sm:px-4 sm:py-3"
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

                        <div
                            v-if="imagePreviewUrl"
                            class="mb-2 flex items-center gap-3 rounded-lg bg-white px-3 py-2 shadow-sm"
                        >
                            <img
                                :src="imagePreviewUrl"
                                alt="Предпросмотр"
                                class="h-16 w-16 rounded-md object-cover"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-[#111b21]">
                                    {{ sendForm.image?.name || 'Изображение' }}
                                </p>
                                <p class="text-xs text-[#667781]">
                                    Будет отправлено как фото
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-[#667781] hover:bg-[#f0f2f5]"
                                title="Убрать"
                                @click="clearImagePreview"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="relative flex items-end gap-1.5 sm:gap-2">
                            <input
                                ref="imageInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                class="hidden"
                                @change="onImageSelected"
                            >

                            <button
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f0f2f5] text-[#54656f] transition hover:bg-[#e9edef] disabled:opacity-40 sm:h-10 sm:w-10"
                                :disabled="sendForm.processing || isRecording"
                                title="Прикрепить изображение"
                                @click="imageInput?.click()"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21.44 11.05l-8.49 8.49a5 5 0 01-7.07-7.07l9.19-9.19a3 3 0 014.24 4.24l-9.19 9.19a1.5 1.5 0 01-2.12-2.12l8.49-8.49"
                                    />
                                </svg>
                            </button>

                            <div class="relative min-w-0 flex-1">
                                <div
                                    v-if="slashQuickRepliesOpen && filteredSlashQuickReplies.length > 0"
                                    class="absolute bottom-full left-0 right-0 z-30 mb-2 max-h-64 overflow-y-auto rounded-lg border border-[#d1d7db] bg-white py-1 shadow-lg"
                                >
                                    <button
                                        v-for="(reply, index) in filteredSlashQuickReplies"
                                        :key="reply.id"
                                        type="button"
                                        class="block w-full px-3 py-2.5 text-left transition"
                                        :class="index === slashActiveIndex
                                            ? 'bg-[#f0f2f5]'
                                            : 'hover:bg-[#f0f2f5]'"
                                        @click="applyQuickReply(reply)"
                                    >
                                        <span class="block text-sm font-semibold text-[#111b21]">
                                            /{{ reply.title }}
                                        </span>
                                        <span class="mt-1 block max-h-24 overflow-hidden whitespace-pre-wrap text-xs leading-relaxed text-[#667781]">
                                            {{ quickReplyPreview(reply) }}
                                        </span>
                                    </button>
                                </div>

                                <input
                                    ref="messageInput"
                                    v-model="sendForm.body"
                                    type="text"
                                    placeholder="Сообщение"
                                    class="w-full rounded-lg border-0 bg-white px-3 py-2 text-[13px] text-[#111b21] shadow-sm placeholder:text-[#8696a0] focus:ring-2 focus:ring-[#00a884]/30 sm:px-4 sm:py-2.5 sm:text-sm"
                                    :disabled="sendForm.processing || isRecording"
                                    @keydown="onMessageInputKeydown"
                                >
                            </div>

                            <button
                                v-if="(sendForm.body.trim() || sendForm.image) && !slashQuickRepliesOpen"
                                type="submit"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00a884] text-white transition hover:bg-[#008f6f] disabled:opacity-40 sm:h-10 sm:w-10"
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
                                v-else-if="!sendForm.image"
                                type="button"
                                class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition disabled:opacity-40 sm:h-10 sm:w-10"
                                :class="isRecording
                                    ? 'bg-[#00a884] text-white shadow-md'
                                    : 'bg-[#f0f2f5] text-[#54656f] hover:bg-[#e9edef]'"
                                :disabled="sendForm.processing"
                                title="Голосовое сообщение"
                                @click="isRecording ? stopRecording() : startRecording()"
                            >
                                <span
                                    v-if="isRecording"
                                    class="absolute inset-0 animate-ping rounded-full bg-[#00a884]/30"
                                />
                                <svg
                                    class="relative h-[22px] w-[22px]"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.49 6-3.3 6-6.72h-1.7z"
                                    />
                                </svg>
                            </button>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="sendForm.errors.body"
                        />
                    </form>
                    </div>
                </template>
            </section>
        </div>
        </div>

        <div
            v-if="lightboxImageUrl"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
            @click="closeImageLightbox"
        >
            <button
                type="button"
                class="absolute right-4 top-4 rounded-full bg-black/50 px-3 py-1 text-2xl text-white"
                @click.stop="closeImageLightbox"
            >
                ✕
            </button>
            <img
                :src="lightboxImageUrl"
                alt="Изображение"
                class="max-h-[92vh] max-w-[92vw] object-contain"
                @click.stop
            >
        </div>

        <Modal :show="showClientModal" @close="showClientModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ linkedClient ? 'Данные клиента' : 'Сохранить контакт' }}
                </h3>

                <div
                    v-if="clientFieldDefinitions.length === 0"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    Сначала добавьте поля в разделе
                    <Link
                        :href="route('client-fields.index')"
                        class="font-medium underline"
                    >
                        «Данные клиента»
                    </Link>.
                </div>

                <form
                    v-else
                    class="mt-5 space-y-4"
                    @submit.prevent="saveClientData"
                >
                    <div
                        v-for="definition in clientFieldDefinitions"
                        :key="definition.id"
                    >
                        <InputLabel
                            :for="`client_field_${definition.key}`"
                            :value="definition.label + (definition.is_required ? ' *' : '')"
                        />

                        <div class="mt-1 flex items-start gap-1.5">
                            <textarea
                                v-if="definition.type === 'textarea'"
                                :id="`client_field_${definition.key}`"
                                v-model="clientForm.fields[definition.key]"
                                rows="3"
                                class="block min-w-0 flex-1 rounded-md border-slate-300 shadow-sm"
                            />

                            <select
                                v-else-if="definition.type === 'select'"
                                :id="`client_field_${definition.key}`"
                                v-model="clientForm.fields[definition.key]"
                                class="block min-w-0 flex-1 rounded-md border-slate-300 shadow-sm"
                            >
                                <option value="">
                                    Выберите...
                                </option>
                                <option
                                    v-for="option in definition.options"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </option>
                            </select>

                            <TextInput
                                v-else
                                :id="`client_field_${definition.key}`"
                                v-model="clientForm.fields[definition.key]"
                                class="block min-w-0 flex-1"
                                :type="definition.type === 'number' ? 'number' : definition.type === 'email' ? 'email' : definition.type === 'date' ? 'date' : definition.type === 'phone' ? 'tel' : 'text'"
                            />

                            <div class="flex shrink-0 flex-col gap-1.5">
                                <button
                                    v-if="messengerApiName"
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                    :title="`Имя: ${messengerApiName}`"
                                    @click="applyMessengerNameToField(definition.key)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="messengerApiContact"
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700"
                                    :title="`Контакт: ${messengerApiContact}`"
                                    @click="applyMessengerContactToField(definition.key)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <InputError
                            class="mt-2"
                            :message="clientForm.errors[`fields.${definition.key}`]"
                        />
                    </div>

                    <InputError
                        class="mt-2"
                        :message="clientForm.errors.client || page.props.errors?.client"
                    />

                    <div class="flex justify-end gap-2">
                        <SecondaryButton
                            type="button"
                            @click="showClientModal = false"
                        >
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="clientForm.processing"
                        >
                            Сохранить
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <p
            v-if="messengerConnected && webhookUrl"
            class="mt-2 hidden text-xs text-slate-400 sm:block"
        >
            Webhook для Meta: {{ webhookUrl }}
        </p>
    </AuthenticatedLayout>
</template>
