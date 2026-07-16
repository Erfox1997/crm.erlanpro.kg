<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChannelIcon from '@/Components/Messenger/ChannelIcon.vue';
import SellFromChatModal from '@/Components/Messenger/SellFromChatModal.vue';
import VoiceMessagePlayer from '@/Components/Messenger/VoiceMessagePlayer.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

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
    chatGptConnected: {
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
    filterPipelines: {
        type: Array,
        default: () => [],
    },
    shopConnected: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const messagesEnd = ref(null);
const messagesContainer = ref(null);
const syncing = ref(false);
const sellModalOpen = ref(false);
const searchQuery = ref('');
const slashActiveIndex = ref(0);
const messageInput = ref(null);
const imageInput = ref(null);
const sendError = ref('');
const quickReplyTargetId = ref(null);

const localConversations = ref(
    (props.conversations || []).map((conversation) => ({ ...conversation })),
);
const localMessages = ref((props.messages || []).map((message) => ({ ...message })));

let pollSince = new Date(Date.now() - 15_000).toISOString();
let pollTimer = null;
let pollInFlight = false;
let shouldStickToBottom = true;

const jsonRequestHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const sendForm = useForm({
    body: '',
    audio: null,
    image: null,
});

const imagePreviewUrl = ref(null);
const lightboxImageUrl = ref(null);
const showClientModal = ref(false);
const showFilterModal = ref(false);
const showSaveQuickReplyModal = ref(false);
const saveQuickReplyPreview = ref('');
const aiImproving = ref(false);
const aiError = ref('');

const saveQuickReplyForm = useForm({
    title: '',
});
let saveQuickReplyMessageId = null;

const funnelFilter = ref({
    pipeline_id: '',
    stage_id: '',
});

const draftFilter = ref({
    pipeline_id: '',
    stage_id: '',
});

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

const funnelFilterActive = computed(() => Boolean(funnelFilter.value.pipeline_id));

const draftFilterStages = computed(() => {
    if (!draftFilter.value.pipeline_id) {
        return [];
    }

    const pipeline = props.filterPipelines.find(
        (p) => p.id === Number(draftFilter.value.pipeline_id),
    );

    return pipeline?.stages ?? [];
});

const filteredConversations = computed(() => {
    let list = [...localConversations.value].sort((a, b) => {
        const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
        const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

        if (bTime !== aTime) {
            return bTime - aTime;
        }

        return Number(b.id) - Number(a.id);
    });

    if (funnelFilter.value.pipeline_id) {
        const pipelineId = Number(funnelFilter.value.pipeline_id);
        list = list.filter((conversation) => conversation.pipeline_id === pipelineId);

        if (funnelFilter.value.stage_id) {
            const stageId = Number(funnelFilter.value.stage_id);
            list = list.filter((conversation) => conversation.stage_id === stageId);
        }
    }

    const q = searchQuery.value.trim().toLowerCase();

    if (!q) {
        return list;
    }

    return list.filter((conversation) => {
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

function cloneList(items) {
    return (items || []).map((item) => ({ ...item }));
}

function numericMessageId(id) {
    const value = Number(id);

    return Number.isFinite(value) && value > 0 ? value : 0;
}

function lastKnownMessageId() {
    let maxId = 0;

    for (const message of localMessages.value) {
        const id = numericMessageId(message.id);
        if (id > maxId) {
            maxId = id;
        }
    }

    return maxId;
}

function isNearBottom() {
    const el = messagesContainer.value;
    if (!el) {
        return true;
    }

    return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}

function onMessagesScroll() {
    shouldStickToBottom = isNearBottom();
}

function mergeConversations(updates) {
    if (!Array.isArray(updates) || updates.length === 0) {
        return;
    }

    const byId = new Map(localConversations.value.map((item) => [item.id, item]));

    for (const update of updates) {
        const current = byId.get(update.id);
        const selectedId = props.selectedConversation?.id;
        const merged = {
            ...(current || {}),
            ...update,
            unread_count: selectedId === update.id
                ? 0
                : (update.unread_count ?? current?.unread_count ?? 0),
        };
        byId.set(update.id, merged);
    }

    localConversations.value = Array.from(byId.values());
}

function mergeMessages(updates, { scroll = true } = {}) {
    if (!Array.isArray(updates) || updates.length === 0) {
        return;
    }

    const byKey = new Map(
        localMessages.value.map((message) => [String(message.id), message]),
    );
    let added = 0;

    for (const update of updates) {
        const key = String(update.id);
        if (!byKey.has(key)) {
            added += 1;
        }
        byKey.set(key, { ...byKey.get(key), ...update });
    }

    localMessages.value = Array.from(byKey.values()).sort((a, b) => {
        const aTime = a.sent_at ? new Date(a.sent_at).getTime() : 0;
        const bTime = b.sent_at ? new Date(b.sent_at).getTime() : 0;
        if (aTime !== bTime) {
            return aTime - bTime;
        }

        return numericMessageId(a.id) - numericMessageId(b.id);
    });

    if (scroll && added > 0 && shouldStickToBottom) {
        scrollToBottom();
    }
}

function touchConversationAfterSend(conversationUpdate, previewBody = '') {
    if (!conversationUpdate?.id) {
        return;
    }

    const existing = localConversations.value.find((item) => item.id === conversationUpdate.id);
    mergeConversations([{
        ...(existing || { id: conversationUpdate.id }),
        ...conversationUpdate,
        unread_count: 0,
        last_message_preview: previewBody || existing?.last_message_preview,
    }]);
}

async function pollUpdates() {
    if (pollInFlight || document.hidden || !messengerConnected.value) {
        return;
    }

    pollInFlight = true;

    try {
        const params = { since: pollSince };
        if (props.selectedConversation?.id) {
            params.conversation_id = props.selectedConversation.id;
            params.after_message_id = lastKnownMessageId();
        }

        const { data } = await window.axios.get(route('messenger.updates'), {
            params,
            headers: jsonRequestHeaders,
        });

        if (data?.server_time) {
            pollSince = data.server_time;
        }

        mergeConversations(data?.conversations || []);

        if (props.selectedConversation?.id) {
            mergeMessages(data?.messages || []);
            mergeConversations([{
                id: props.selectedConversation.id,
                unread_count: 0,
            }]);
        }
    } catch {
        // Keep polling; transient network errors are fine.
    } finally {
        pollInFlight = false;
    }
}

function startPolling() {
    stopPolling();
    pollTimer = window.setInterval(pollUpdates, 2500);
}

function stopPolling() {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

function onVisibilityChange() {
    if (document.hidden) {
        return;
    }

    pollUpdates();
}

watch(
    () => props.conversations,
    (conversations) => {
        const pendingIds = new Set(
            localMessages.value
                .filter((message) => message.status === 'pending' || String(message.id).startsWith('tmp-'))
                .map((message) => message.id),
        );

        if (pendingIds.size === 0) {
            localConversations.value = cloneList(conversations);
            return;
        }

        mergeConversations(conversations || []);
    },
    { deep: true },
);

watch(
    () => [props.selectedConversation?.id, props.messages],
    () => {
        const pending = localMessages.value.filter(
            (message) => message.status === 'pending' || String(message.id).startsWith('tmp-'),
        );
        const serverMessages = cloneList(props.messages);

        if (pending.length === 0) {
            localMessages.value = serverMessages;
        } else {
            const byId = new Map(serverMessages.map((message) => [String(message.id), message]));
            for (const message of pending) {
                if (!byId.has(String(message.id))) {
                    byId.set(String(message.id), message);
                }
            }
            localMessages.value = Array.from(byId.values());
        }

        shouldStickToBottom = true;
        scrollToBottom();
    },
    { deep: true },
);

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
    startPolling();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    stopPolling();
    clearImagePreview();
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

function stageBadgeStyle(color) {
    const hex = (color || '#94a3b8').replace('#', '');

    if (hex.length !== 6) {
        return {
            backgroundColor: 'rgba(148, 163, 184, 0.16)',
            color: '#64748b',
        };
    }

    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);

    return {
        backgroundColor: `rgba(${r}, ${g}, ${b}, 0.16)`,
        color: `rgb(${Math.max(0, r - 24)}, ${Math.max(0, g - 24)}, ${Math.max(0, b - 24)})`,
    };
}

function openConversation(id) {
    quickReplyTargetId.value = null;
    router.get(
        route('messenger.index'),
        { conversation: id },
        { preserveState: true, preserveScroll: true },
    );
}

function toggleQuickReplyTarget(messageId) {
    const message = localMessages.value.find((item) => item.id === messageId);
    if (!message || !canSaveAsQuickReply(message)) {
        quickReplyTargetId.value = null;
        return;
    }

    quickReplyTargetId.value = quickReplyTargetId.value === messageId ? null : messageId;
}

function onSalePending({ clientId, total, currency: saleCurrency }) {
    const totalLabel = Number(total || 0).toLocaleString('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    localMessages.value = [
        ...localMessages.value,
        {
            id: clientId,
            client_id: clientId,
            direction: 'outbound',
            body: '',
            attachments: [{
                type: 'image',
                url: '',
                name: 'Чек',
                mime_type: 'image/png',
                loading: true,
            }],
            status: 'pending',
            pending_kind: 'receipt',
            sent_at: new Date().toISOString(),
            loading_label: `Чек · ${totalLabel} ${saleCurrency || ''}`.trim(),
        },
    ];
    shouldStickToBottom = true;
    scrollToBottom();
    touchConversationAfterSend(
        { id: props.selectedConversation?.id, last_message_at: new Date().toISOString() },
        'Чек',
    );
}

async function onSaleFinished({ clientId, ok, message, warning }) {
    if (ok) {
        localMessages.value = localMessages.value.filter((item) => item.id !== clientId);
        sendError.value = warning || '';
        await pollUpdates();
        shouldStickToBottom = true;
        scrollToBottom();
        return;
    }

    localMessages.value = localMessages.value.map((item) => (
        item.id === clientId
            ? {
                ...item,
                status: 'failed',
                body: message || 'Не удалось создать продажу',
                attachments: [{
                    type: 'image',
                    url: '',
                    name: 'Чек',
                    loading: false,
                    failed: true,
                }],
            }
            : item
    ));
    sendError.value = message || 'Не удалось создать продажу.';
}

async function onQuoteFinished({ ok, message }) {
    if (ok) {
        await pollUpdates();
        shouldStickToBottom = true;
        scrollToBottom();
        return;
    }

    sendError.value = message || 'Не удалось отправить расчёт.';
}

function openFilterModal() {
    draftFilter.value = {
        pipeline_id: funnelFilter.value.pipeline_id,
        stage_id: funnelFilter.value.stage_id,
    };
    showFilterModal.value = true;
}

function onDraftPipelineChange() {
    draftFilter.value.stage_id = '';
}

function applyFunnelFilter() {
    if (!draftFilter.value.pipeline_id) {
        return;
    }

    funnelFilter.value = {
        pipeline_id: draftFilter.value.pipeline_id,
        stage_id: draftFilter.value.stage_id,
    };
    showFilterModal.value = false;
}

function clearFunnelFilter() {
    funnelFilter.value = { pipeline_id: '', stage_id: '' };
    draftFilter.value = { pipeline_id: '', stage_id: '' };
    showFilterModal.value = false;
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

async function applyQuickReply(reply) {
    if (!props.selectedConversation) {
        return;
    }

    if (reply.type === 'text') {
        sendForm.body = reply.body || '';
        nextTick(() => messageInput.value?.focus());

        return;
    }

    const clientId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const previewBody = reply.type === 'audio'
        ? '🎤 Голосовое сообщение'
        : (reply.body || '🖼 Изображение');

    localMessages.value = [
        ...localMessages.value,
        {
            id: clientId,
            client_id: clientId,
            direction: 'outbound',
            body: previewBody,
            attachments: [],
            status: 'pending',
            sent_at: new Date().toISOString(),
        },
    ];
    sendForm.reset('body');
    sendError.value = '';
    shouldStickToBottom = true;
    scrollToBottom();
    touchConversationAfterSend(
        { id: props.selectedConversation.id, last_message_at: new Date().toISOString() },
        previewBody,
    );

    try {
        const { data } = await window.axios.post(
            route('messenger.send-quick-reply', [props.selectedConversation.id, reply.id]),
            {},
            { headers: jsonRequestHeaders },
        );

        localMessages.value = localMessages.value.filter((message) => message.id !== clientId);
        if (data?.message) {
            mergeMessages([data.message]);
        }
        if (data?.conversation) {
            touchConversationAfterSend(data.conversation, previewBody);
        }
    } catch (error) {
        localMessages.value = localMessages.value.map((message) => (
            message.id === clientId
                ? { ...message, status: 'failed' }
                : message
        ));
        sendError.value = error?.response?.data?.message
            || error?.response?.data?.errors?.body?.[0]
            || 'Не удалось отправить шаблон.';
    }
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

async function sendMessage() {
    if (!props.selectedConversation || slashQuickRepliesOpen.value) {
        return;
    }

    const body = sendForm.body.trim();
    const imageFile = sendForm.image;

    if (!body && !imageFile) {
        return;
    }

    const clientId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const previewUrl = imagePreviewUrl.value;
    const optimisticAttachments = imageFile
        ? [{
            type: 'image',
            url: previewUrl || '',
            name: imageFile.name || 'image.jpg',
            mime_type: imageFile.type || 'image/jpeg',
        }]
        : [];

    localMessages.value = [
        ...localMessages.value,
        {
            id: clientId,
            client_id: clientId,
            direction: 'outbound',
            body,
            attachments: optimisticAttachments,
            status: 'pending',
            sent_at: new Date().toISOString(),
        },
    ];

    const formData = new FormData();
    formData.append('body', body);
    if (imageFile) {
        formData.append('image', imageFile);
    }

    sendForm.reset('body', 'audio', 'image');
    imagePreviewUrl.value = null;
    if (imageInput.value) {
        imageInput.value.value = '';
    }
    sendError.value = '';
    shouldStickToBottom = true;
    scrollToBottom();
    touchConversationAfterSend(
        { id: props.selectedConversation.id, last_message_at: new Date().toISOString() },
        body || '🖼 Изображение',
    );

    try {
        const { data } = await window.axios.post(
            route('messenger.send', props.selectedConversation.id),
            formData,
            { headers: jsonRequestHeaders },
        );

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        localMessages.value = localMessages.value.filter((message) => message.id !== clientId);
        if (data?.message) {
            mergeMessages([data.message]);
        }
        if (data?.conversation) {
            touchConversationAfterSend(data.conversation, body);
        }
    } catch (error) {
        localMessages.value = localMessages.value.map((message) => (
            message.id === clientId
                ? { ...message, status: 'failed' }
                : message
        ));
        sendError.value = error?.response?.data?.message
            || error?.response?.data?.errors?.body?.[0]
            || 'Не удалось отправить сообщение.';
        if (!sendForm.body && body) {
            sendForm.body = body;
        }
    }
}

async function improveWithAi() {
    if (!props.chatGptConnected || aiImproving.value || isRecording.value) {
        return;
    }

    const body = sendForm.body.trim();
    if (!body) {
        aiError.value = 'Сначала введите текст сообщения';
        return;
    }

    aiImproving.value = true;
    aiError.value = '';

    try {
        const { data } = await window.axios.post(route('messenger.ai-improve'), { body });
        if (data?.body) {
            sendForm.body = data.body;
            nextTick(() => messageInput.value?.focus());
        }
    } catch (error) {
        aiError.value = error?.response?.data?.message
            || 'Не удалось улучшить текст через ИИ';
    } finally {
        aiImproving.value = false;
    }
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
    if (!props.selectedConversation || isRecording.value) {
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

async function sendVoiceMessage() {
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
    const clientId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const localUrl = URL.createObjectURL(blob);

    audioChunks = [];
    mediaRecorder = null;

    localMessages.value = [
        ...localMessages.value,
        {
            id: clientId,
            client_id: clientId,
            direction: 'outbound',
            body: '',
            attachments: [{
                type: 'audio',
                url: localUrl,
                name: file.name,
                mime_type: mimeType,
            }],
            status: 'pending',
            sent_at: new Date().toISOString(),
        },
    ];
    sendError.value = '';
    shouldStickToBottom = true;
    scrollToBottom();
    touchConversationAfterSend(
        { id: props.selectedConversation.id, last_message_at: new Date().toISOString() },
        '🎤 Голосовое сообщение',
    );

    const formData = new FormData();
    formData.append('body', '');
    formData.append('audio', file);

    try {
        const { data } = await window.axios.post(
            route('messenger.send', props.selectedConversation.id),
            formData,
            { headers: jsonRequestHeaders },
        );

        URL.revokeObjectURL(localUrl);
        localMessages.value = localMessages.value.filter((message) => message.id !== clientId);
        if (data?.message) {
            mergeMessages([data.message]);
        }
        if (data?.conversation) {
            touchConversationAfterSend(data.conversation, '🎤 Голосовое сообщение');
        }
    } catch (error) {
        localMessages.value = localMessages.value.map((message) => (
            message.id === clientId
                ? { ...message, status: 'failed' }
                : message
        ));
        sendError.value = error?.response?.data?.message
            || error?.response?.data?.errors?.body?.[0]
            || 'Не удалось отправить голосовое сообщение.';
    }
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

    for (const message of localMessages.value) {
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

function outboundTicks(message) {
    if (message.status === 'failed') {
        return '!';
    }

    if (message.status === 'pending') {
        return '✓';
    }

    return '✓✓';
}

function outboundTicksClass(message) {
    if (message.status === 'failed') {
        return 'text-red-500';
    }

    if (message.status === 'pending') {
        return 'text-[#667781]';
    }

    return 'text-[#53bdeb]';
}

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

function canSaveAsQuickReply(message) {
    if (
        String(message.id).startsWith('tmp-')
        || message.status === 'pending'
        || message.status === 'failed'
        || message.attachments?.[0]?.loading
    ) {
        return false;
    }

    if (message.body?.trim()) {
        return true;
    }

    const first = Array.isArray(message.attachments) ? message.attachments[0] : null;
    return first?.type === 'image' || first?.type === 'audio';
}

function suggestQuickReplyTitle(message) {
    const body = (message.body || '').trim().replace(/\s+/g, ' ');
    if (body) {
        return body.slice(0, 40).replace(/[^\p{L}\p{N}\s_-]+/gu, '').trim() || 'ответ';
    }

    const first = Array.isArray(message.attachments) ? message.attachments[0] : null;
    if (first?.type === 'image') {
        return 'фото';
    }
    if (first?.type === 'audio') {
        return 'голос';
    }

    return 'ответ';
}

function openSaveQuickReplyModal(message) {
    if (!canSaveAsQuickReply(message)) {
        return;
    }

    saveQuickReplyMessageId = message.id;
    saveQuickReplyPreview.value = message.body?.trim()
        || (message.attachments?.[0]?.type === 'image' ? 'Фото' : 'Голосовое сообщение');
    saveQuickReplyForm.clearErrors();
    saveQuickReplyForm.title = suggestQuickReplyTitle(message);
    showSaveQuickReplyModal.value = true;
}

function closeSaveQuickReplyModal() {
    showSaveQuickReplyModal.value = false;
    saveQuickReplyMessageId = null;
    saveQuickReplyPreview.value = '';
    saveQuickReplyForm.reset();
    saveQuickReplyForm.clearErrors();
}

function submitSaveQuickReply() {
    if (!saveQuickReplyMessageId) {
        return;
    }

    saveQuickReplyForm.post(
        route('messenger.messages.quick-reply', saveQuickReplyMessageId),
        {
            preserveScroll: true,
            onSuccess: () => closeSaveQuickReplyModal(),
        },
    );
}

function scrollToBottom() {
    nextTick(() => {
        messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
    });
}

</script>

<template>
    <Head title="Мессенджер" />

    <AuthenticatedLayout full-height>
        <div
            v-if="$page.props.flash?.success"
            class="shrink-0 border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
        >
            {{ $page.props.flash.success }}
        </div>

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
                        <button
                            v-if="filterPipelines.length"
                            type="button"
                            class="rounded-full p-2 transition hover:bg-[#e9edef]"
                            :class="funnelFilterActive
                                ? 'bg-[#d9fdd3] text-[#008069]'
                                : 'text-[#54656f]'"
                            title="Фильтр по воронке"
                            @click="openFilterModal"
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
                                    d="M3 4.5h18M6 9.75h12M10.5 15h3"
                                />
                            </svg>
                        </button>
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
                                            class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium leading-none"
                                            :style="stageBadgeStyle(conversation.stage_color)"
                                        >
                                            {{ conversation.stage_name }}
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
                            v-if="shopConnected"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-500 px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-amber-600 sm:px-3 sm:text-xs"
                            title="Продать"
                            @click="sellModalOpen = true"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                            </svg>
                            <span class="hidden sm:inline">Продать</span>
                        </button>

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
                        ref="messagesContainer"
                        class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-y-contain bg-[#efeae2] px-2 py-2 sm:space-y-1 sm:px-4 sm:py-3"
                        style="background-image: url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23d9d0c3%22 fill-opacity=%220.35%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z/%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"
                        @scroll="onMessagesScroll"
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
                                class="group relative max-w-[82%] rounded-lg px-2 py-1.5 text-[13px] leading-snug shadow-sm sm:max-w-[min(100%,28rem)] sm:px-3 sm:py-2 sm:text-sm"
                                :class="[
                                    item.message.direction === 'outbound'
                                        ? 'rounded-tr-none bg-[#d9fdd3]'
                                        : 'rounded-tl-none bg-white',
                                    item.message.status === 'failed' ? 'ring-1 ring-red-300 opacity-80' : '',
                                ]"
                                @click="toggleQuickReplyTarget(item.message.id)"
                            >
                                <button
                                    v-if="canSaveAsQuickReply(item.message)"
                                    type="button"
                                    class="absolute -top-2 right-1 z-10 rounded-full bg-white p-1 text-[#54656f] opacity-0 shadow-sm ring-1 ring-[#d1d7db] transition hover:bg-[#f0f2f5] hover:text-[#008069] sm:group-hover:opacity-100"
                                    :class="{ 'opacity-100': quickReplyTargetId === item.message.id }"
                                    title="В быстрые ответы"
                                    @click.stop="openSaveQuickReplyModal(item.message)"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"
                                        />
                                    </svg>
                                </button>

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

                                        <div
                                            v-else-if="attachment.type === 'image' && attachment.loading"
                                            class="flex h-40 w-44 flex-col items-center justify-center gap-3 rounded-md bg-white/70 sm:h-48 sm:w-52"
                                        >
                                            <span
                                                class="inline-block h-9 w-9 animate-spin rounded-full border-[3px] border-[#00a884] border-t-transparent"
                                                aria-hidden="true"
                                            />
                                            <span class="px-2 text-center text-xs text-[#54656f]">
                                                {{ item.message.loading_label || 'Чек загружается…' }}
                                            </span>
                                        </div>

                                        <img
                                            v-else-if="attachment.type === 'image' && attachment.url"
                                            :src="attachment.url"
                                            :alt="attachment.name || attachmentLabel(attachment.type)"
                                            class="max-h-48 max-w-full cursor-zoom-in rounded-md object-contain transition hover:opacity-90 sm:max-h-72"
                                            @click.stop="openImageLightbox(attachment.url)"
                                        >

                                        <p
                                            v-else-if="attachment.type === 'image' && attachment.failed"
                                            class="text-xs text-red-600"
                                        >
                                            Не удалось загрузить чек
                                        </p>

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
                                        :class="outboundTicksClass(item.message)"
                                        :title="item.message.status === 'failed' ? 'Не отправлено' : ''"
                                    >
                                        {{ outboundTicks(item.message) }}
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
                            v-if="sendError"
                            class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            {{ sendError }}
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
                                :disabled="isRecording"
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

                            <button
                                v-if="chatGptConnected"
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition disabled:opacity-40 sm:h-10 sm:w-10"
                                :class="aiImproving
                                    ? 'bg-[#10a37f] text-white'
                                    : 'bg-[#f0f2f5] text-[#10a37f] hover:bg-[#e6f6f1]'"
                                :disabled="isRecording || aiImproving || !sendForm.body.trim()"
                                title="Улучшить текст с ИИ"
                                @click="improveWithAi"
                            >
                                <svg
                                    v-if="!aiImproving"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M22.282 9.821a5.985 5.985 0 00-.516-4.91 6.046 6.046 0 00-6.51-2.9A6.065 6.065 0 004.981 4.18a5.985 5.985 0 00-3.998 2.9 6.046 6.046 0 00.743 7.097 5.98 5.98 0 00.51 4.911 6.051 6.051 0 006.515 2.9A5.985 5.985 0 0013.26 24a6.056 6.056 0 005.772-4.206 5.99 5.99 0 003.997-2.9 6.056 6.056 0 00-.747-7.073zM13.26 22.43a4.476 4.476 0 01-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 00.392-.681v-6.737l2.02 1.168a.071.071 0 01.038.052v5.583a4.504 4.504 0 01-4.494 4.494zM3.6 18.304a4.47 4.47 0 01-.535-3.014l.142.085 4.783 2.759a.771.771 0 00.78 0l5.843-3.369v2.332a.08.08 0 01-.033.062L9.74 19.95a4.5 4.5 0 01-6.14-1.646zM2.34 7.896a4.485 4.485 0 012.366-1.973V11.6a.766.766 0 00.388.676l5.815 3.355-2.02 1.168a.076.076 0 01-.071 0l-4.83-2.786A4.504 4.504 0 012.34 7.872zm16.597 3.855l-5.833-3.387L15.119 7.2a.076.076 0 01.071 0l4.83 2.791a4.494 4.494 0 01-.676 8.105v-5.678a.79.79 0 00-.407-.667zm2.01-3.023l-.141-.085-4.774-2.782a.776.776 0 00-.785 0L9.409 9.23V6.897a.066.066 0 01.028-.061l4.83-2.787a4.5 4.5 0 016.68 4.66zm-12.64 4.135l-2.02-1.164a.08.08 0 01-.038-.057V6.075a4.5 4.5 0 017.375-3.453l-.141.08L8.704 5.46a.795.795 0 00-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
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
                                    :disabled="isRecording"
                                    @keydown="onMessageInputKeydown"
                                >
                            </div>

                            <button
                                v-if="(sendForm.body.trim() || sendForm.image) && !slashQuickRepliesOpen"
                                type="submit"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00a884] text-white transition hover:bg-[#008f6f] disabled:opacity-40 sm:h-10 sm:w-10"
                                :disabled="isRecording"
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
                            :message="sendError || sendForm.errors.body || aiError"
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

        <Modal :show="showSaveQuickReplyModal" max-width="md" @close="closeSaveQuickReplyModal">
            <form class="p-6" @submit.prevent="submitSaveQuickReply">
                <h3 class="text-lg font-semibold text-slate-900">
                    В быстрые ответы
                </h3>
                <p class="mt-1 text-sm text-slate-600">
                    Сообщение сохранится как шаблон. В чате его можно вызвать через
                    <span class="font-mono">/</span> и название.
                </p>

                <div
                    v-if="saveQuickReplyPreview"
                    class="mt-4 max-h-28 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm whitespace-pre-wrap text-slate-700"
                >
                    {{ saveQuickReplyPreview }}
                </div>

                <div class="mt-4">
                    <InputLabel for="save_quick_reply_title" value="Название команды" />
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-500">/</span>
                        <TextInput
                            id="save_quick_reply_title"
                            v-model="saveQuickReplyForm.title"
                            type="text"
                            class="block w-full"
                            placeholder="например: мбанк"
                            maxlength="120"
                            autocomplete="off"
                        />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Будет доступно как
                        <span class="font-mono">/{{ saveQuickReplyForm.title || 'название' }}</span>
                    </p>
                    <InputError
                        class="mt-2"
                        :message="saveQuickReplyForm.errors.title"
                    />
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton
                        type="button"
                        @click="closeSaveQuickReplyModal"
                    >
                        Отмена
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        :disabled="saveQuickReplyForm.processing || !saveQuickReplyForm.title.trim()"
                    >
                        Сохранить
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showFilterModal" max-width="md" @close="showFilterModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    Фильтр чатов
                </h3>
                <p class="mt-1 text-sm text-slate-600">
                    Покажем только клиентов из выбранной воронки. Этап можно не указывать.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="filter_pipeline" value="Воронка" />
                        <select
                            id="filter_pipeline"
                            v-model="draftFilter.pipeline_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @change="onDraftPipelineChange"
                        >
                            <option value="">— выберите воронку —</option>
                            <option
                                v-for="pipeline in filterPipelines"
                                :key="pipeline.id"
                                :value="String(pipeline.id)"
                            >
                                {{ pipeline.is_default ? '★ ' : '' }}{{ pipeline.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="draftFilter.pipeline_id">
                        <InputLabel for="filter_stage" value="Этап (необязательно)" />
                        <select
                            id="filter_stage"
                            v-model="draftFilter.stage_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Все этапы</option>
                            <option
                                v-for="stage in draftFilterStages"
                                :key="stage.id"
                                :value="String(stage.id)"
                            >
                                {{ stage.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                    <SecondaryButton
                        v-if="funnelFilterActive"
                        type="button"
                        @click="clearFunnelFilter"
                    >
                        Сбросить
                    </SecondaryButton>
                    <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                        <SecondaryButton
                            type="button"
                            @click="showFilterModal = false"
                        >
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton
                            type="button"
                            :disabled="!draftFilter.pipeline_id"
                            @click="applyFunnelFilter"
                        >
                            Применить
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

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

        <SellFromChatModal
            v-if="selectedConversation && shopConnected"
            :show="sellModalOpen"
            :conversation-id="selectedConversation.id"
            :client-name="linkedClient?.name || selectedConversation.participant_name || ''"
            :client-phone="linkedClient?.phone || selectedConversation.participant_id || ''"
            :catalog-url="route('shop-sales.catalog')"
            :submit-url="route('shop-sales.store', selectedConversation.id)"
            :quote-url="route('shop-sales.quote', selectedConversation.id)"
            :draft-url="route('shop-sales.draft.show', selectedConversation.id)"
            @close="sellModalOpen = false"
            @sale-pending="onSalePending"
            @sale-finished="onSaleFinished"
            @quote-finished="onQuoteFinished"
        />

        <p
            v-if="messengerConnected && webhookUrl"
            class="mt-2 hidden text-xs text-slate-400 sm:block"
        >
            Webhook для Meta: {{ webhookUrl }}
        </p>
    </AuthenticatedLayout>
</template>
