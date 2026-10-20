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
import { localeTag } from '@/i18n';
import {
    loadCachedMessages,
    saveCachedMessages,
    warmMessageCache,
} from '@/utils/messengerMessageStore';
import { syncAppBadge } from '@/utils/syncAppBadge';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

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
const isMiniApp = computed(() => Boolean(page.props.telegramMiniApp));
const companyId = computed(() => Number(page.props.company?.id) || 0);

const messagesEnd = ref(null);
const messagesContainer = ref(null);
const syncing = ref(false);
const sellModalOpen = ref(false);
const searchQuery = ref('');
const slashActiveIndex = ref(0);
const messageInput = ref(null);
const imageInput = ref(null);
const sendError = ref('');
const messageMenuId = ref(null);
const messageMenuPlacement = ref('below');
const conversationMenuId = ref(null);
const headerMenuOpen = ref(false);
const markingAllRead = ref(false);
const replyToMessage = ref(null);
const emojiPickerOpen = ref(false);
const composerAttachOpen = ref(false);
const chatToast = ref(null);

const composerEmojis = [
    '😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😎',
    '🤔', '😅', '😢', '😭', '😡', '👍', '👎', '👏',
    '🙏', '🔥', '❤️', '💔', '✨', '🎉', '✅', '❌',
    '💯', '🤝', '💪', '🙌', '👌', '✌️', '🤗', '😴',
    '🤩', '😇', '🤷', '🙈', '☀️', '🌙', '⭐', '🌹',
    '☕', '🍕', '🎂', '📱', '💰', '📍', '⏰', '👋',
];

let pollSince = new Date(Date.now() - 15_000).toISOString();
let pollTimer = null;
let pollInFlight = false;
let shouldStickToBottom = true;
let chatToastTimer = null;
let removeInertiaSuccessListener = null;
let removeInertiaErrorListener = null;
/** @type {Set<string>} */
const inFlightClientIds = new Set();
let lastOutboundConfirmAt = 0;

const localConversations = ref(
    (props.conversations || []).map((conversation) => ({ ...conversation })),
);
const selectedConversation = ref(
    props.selectedConversation ? { ...props.selectedConversation } : null,
);
const linkedClient = ref(props.linkedClient ? { ...props.linkedClient } : null);
const funnelDeal = ref(props.funnelDeal ? { ...props.funnelDeal } : null);
const localMessages = ref((props.messages || []).map((message) => ({
    ...message,
    stable_key: message.stable_key || message.client_id || message.id,
})));
const messagesLoading = ref(false);
/** @type {Map<number, any[]>} */
const messageCache = new Map();
if (selectedConversation.value?.id && localMessages.value.length > 0) {
    messageCache.set(
        selectedConversation.value.id,
        localMessages.value.map((message) => ({ ...message })),
    );
}
let conversationLoadToken = 0;
/** @type {AbortController|null} */
let conversationAbort = null;

const jsonRequestHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const localUnreadTotal = computed(() => (
    localConversations.value.reduce((sum, item) => sum + (Number(item.unread_count) || 0), 0)
));

watch(localUnreadTotal, (count) => {
    syncAppBadge(count);
});

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
const showTaskModal = ref(false);
const saveQuickReplyPreview = ref('');
const aiImproving = ref(false);
const aiError = ref('');

const saveQuickReplyForm = useForm({
    title: '',
});

const taskForm = useForm({
    note: '',
    due_on: '',
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

const dealPipelineForm = useForm({
    pipeline_id: '',
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

function showChatToast(message, type = 'success', durationMs = 2000) {
    const text = typeof message === 'string' ? message.trim() : '';
    if (!text) {
        return;
    }

    if (chatToastTimer) {
        window.clearTimeout(chatToastTimer);
        chatToastTimer = null;
    }

    chatToast.value = {
        id: Date.now(),
        message: text,
        type,
    };

    chatToastTimer = window.setTimeout(() => {
        chatToast.value = null;
        chatToastTimer = null;
        if (type === 'error') {
            sendError.value = '';
            aiError.value = '';
        }
    }, durationMs);
}

function setSendError(message) {
    const text = message || '';
    sendError.value = text;
    if (text) {
        showChatToast(text, 'error', 2200);
    }
}

function setAiError(message) {
    const text = message || '';
    aiError.value = text;
    if (text) {
        showChatToast(text, 'error', 2200);
    }
}

function firstErrorMessage(errors, keys = []) {
    if (!errors || typeof errors !== 'object') {
        return '';
    }

    for (const key of keys) {
        const value = errors[key];
        if (Array.isArray(value) && value[0]) {
            return String(value[0]);
        }
        if (typeof value === 'string' && value.trim()) {
            return value.trim();
        }
    }

    return '';
}

function showNoticesFromPageProps(pageProps) {
    if (!pageProps) {
        return;
    }

    if (pageProps.flash?.success) {
        showChatToast(pageProps.flash.success, 'success');
    }

    if (pageProps.flash?.warning) {
        showChatToast(pageProps.flash.warning, 'error', 4200);
    }

    if (pageProps.flash?.error) {
        showChatToast(pageProps.flash.error, 'error', 4200);
    }

    const errorMessage = firstErrorMessage(pageProps.errors, ['sync', 'client', 'stage', 'body']);
    if (errorMessage) {
        showChatToast(errorMessage, 'error', 2200);
    }
}

function numericMessageId(id) {
    const value = Number(id);

    return Number.isFinite(value) && value > 0 ? value : 0;
}

function messageStableKey(message) {
    return String(message?.stable_key || message?.client_id || message?.id || '');
}

function withStableIdentity(message, fallbackKey = null) {
    const stableKey = String(
        message?.stable_key
        || message?.client_id
        || fallbackKey
        || message?.id
        || '',
    );

    return {
        ...message,
        stable_key: stableKey,
        client_id: message?.client_id || (String(message?.id || '').startsWith('tmp-') ? message.id : message?.client_id),
    };
}

function isOptimisticMessage(message) {
    return Boolean(
        message
        && (message.status === 'pending' || String(message.id).startsWith('tmp-')),
    );
}

function isTmpMessage(message) {
    return String(message?.id || '').startsWith('tmp-');
}

function sortMessages(messages) {
    return [...messages].sort((a, b) => {
        const aTime = a.sent_at ? new Date(a.sent_at).getTime() : 0;
        const bTime = b.sent_at ? new Date(b.sent_at).getTime() : 0;
        if (aTime !== bTime) {
            return aTime - bTime;
        }

        return numericMessageId(a.id) - numericMessageId(b.id);
    });
}

function patchOptimisticWithServer(local, server, { finalize = false } = {}) {
    return withStableIdentity({
        ...local,
        id: server.id,
        status: finalize
            ? (server.status || 'sent')
            : (local.status === 'pending' ? 'pending' : (server.status || local.status)),
        body: local.body || server.body,
        // Keep local clock + previews so the bubble does not jump or remount
        sent_at: local.sent_at || server.sent_at,
        attachments: mergeConfirmedAttachments(local.attachments, server.attachments),
        client_id: local.client_id || local.stable_key || local.id,
        stable_key: local.stable_key || local.client_id || local.id,
    }, local.stable_key || local.client_id || local.id);
}

function optimisticContentMatches(local, server) {
    if (local?.direction !== 'outbound' || server?.direction !== 'outbound') {
        return false;
    }

    const localBody = (local.body || '').trim();
    const serverBody = (server.body || '').trim();
    const localAttachments = Array.isArray(local.attachments) ? local.attachments : [];
    const serverAttachments = Array.isArray(server.attachments) ? server.attachments : [];
    const localHasAudio = localAttachments.some((item) => item.type === 'audio');
    const serverHasAudio = serverAttachments.some((item) => item.type === 'audio');
    const localHasImage = localAttachments.some((item) => item.type === 'image');
    const serverHasImage = serverAttachments.some((item) => item.type === 'image');

    if (localHasAudio && serverHasAudio) {
        return true;
    }

    if (localHasImage && serverHasImage && localBody === serverBody) {
        return true;
    }

    if (localBody && serverBody && localBody === serverBody) {
        return true;
    }

    return !localBody && !serverBody && localAttachments.length > 0 && serverAttachments.length > 0;
}

function optimisticMatchesServer(local, server) {
    // Only unbound tmp bubbles may absorb a server echo — never rematch soft-patched ids
    if (!isTmpMessage(local)) {
        return false;
    }

    return optimisticContentMatches(local, server);
}

/**
 * Collapse poll/confirm races: tmp bubble + server echo must become one row.
 */
function dedupeLocalMessages(messages) {
    const result = [];

    for (const msg of messages) {
        const nid = numericMessageId(msg.id);

        if (nid && msg.direction === 'outbound') {
            const tmpIdx = result.findIndex((kept) => isTmpMessage(kept) && optimisticContentMatches(kept, msg));
            if (tmpIdx >= 0) {
                result[tmpIdx] = patchOptimisticWithServer(result[tmpIdx], msg, { finalize: true });
                continue;
            }

            const sameIdIdx = result.findIndex((kept) => numericMessageId(kept.id) === nid);
            if (sameIdIdx >= 0) {
                const kept = result[sameIdIdx];
                result[sameIdIdx] = withStableIdentity({
                    ...kept,
                    ...msg,
                    client_id: kept.client_id || msg.client_id,
                    stable_key: kept.stable_key || kept.client_id || msg.id,
                    sent_at: kept.sent_at || msg.sent_at,
                    attachments: mergeConfirmedAttachments(kept.attachments, msg.attachments),
                }, kept.stable_key || kept.client_id || msg.id);
                continue;
            }
        }

        if (isTmpMessage(msg) && msg.direction === 'outbound') {
            const serverIdx = result.findIndex((kept) => (
                numericMessageId(kept.id)
                && optimisticContentMatches(msg, kept)
            ));
            if (serverIdx >= 0) {
                result[serverIdx] = patchOptimisticWithServer(msg, result[serverIdx], { finalize: true });
                continue;
            }
        }

        result.push(msg);
    }

    return result;
}

function confirmOptimisticMessage(clientId, serverMessage) {
    if (!serverMessage) {
        return;
    }

    const serverId = String(serverMessage.id);
    const next = [];
    let replaced = false;

    for (const message of localMessages.value) {
        const isTarget = message.id === clientId
            || message.client_id === clientId
            || message.stable_key === clientId;
        const isDuplicateServer = String(message.id) === serverId && !isTarget;

        if (isDuplicateServer) {
            continue;
        }

        if (isTarget) {
            next.push(patchOptimisticWithServer(message, serverMessage, { finalize: true }));
            replaced = true;
            continue;
        }

        next.push(message);
    }

    if (!replaced) {
        next.push(withStableIdentity({
            ...serverMessage,
            client_id: clientId,
            stable_key: clientId,
        }, clientId));
    }

    localMessages.value = dedupeLocalMessages(next);
    inFlightClientIds.delete(clientId);
    lastOutboundConfirmAt = Date.now();
}

function mergeConfirmedAttachments(localAttachments, serverAttachments) {
    const local = Array.isArray(localAttachments) ? localAttachments : [];
    const server = Array.isArray(serverAttachments) ? serverAttachments : [];

    if (server.length === 0) {
        return local;
    }

    if (local.length === 0) {
        return server;
    }

    return server.map((item, index) => {
        const prev = local[index];
        if (!prev) {
            return item;
        }

        if (prev.url && String(prev.url).startsWith('blob:') && item.url) {
            return { ...prev, ...item };
        }

        if (prev.url && !item.url) {
            return { ...item, url: prev.url };
        }

        return { ...prev, ...item };
    });
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
        const selectedId = selectedConversation.value?.id;
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

    const list = [...localMessages.value];
    let added = 0;
    const claimedPending = new Set();
    const hasPendingOutbound = list.some((message) => (
        message.direction === 'outbound'
        && (isTmpMessage(message) || (message.status === 'pending' && String(message.stable_key || '').startsWith('tmp-')))
    )) || inFlightClientIds.size > 0;

    for (const update of updates) {
        const idKey = String(update.id);
        const existingIndex = list.findIndex((message) => String(message.id) === idKey);

        if (existingIndex >= 0) {
            const prev = list[existingIndex];
            list[existingIndex] = withStableIdentity({
                ...prev,
                ...update,
                client_id: prev.client_id || update.client_id,
                stable_key: prev.stable_key || prev.client_id || update.id,
                sent_at: prev.sent_at || update.sent_at,
                attachments: mergeConfirmedAttachments(prev.attachments, update.attachments),
            }, prev.stable_key || prev.client_id || update.id);
            continue;
        }

        const pendingIndex = list.findIndex((message, index) => (
            !claimedPending.has(index)
            && (
                optimisticMatchesServer(message, update)
                || (
                    message.direction === 'outbound'
                    && update.direction === 'outbound'
                    && String(message.stable_key || '').startsWith('tmp-')
                    && optimisticContentMatches(message, update)
                )
            )
        ));

        if (pendingIndex >= 0) {
            claimedPending.add(pendingIndex);
            list[pendingIndex] = patchOptimisticWithServer(list[pendingIndex], update);
            continue;
        }

        // Provider webhook echo: same outbound text within a few seconds — do not add a second bubble
        if (update.direction === 'outbound') {
            const echoIndex = list.findIndex((message) => (
                message.direction === 'outbound'
                && optimisticContentMatches(message, update)
                && Math.abs(new Date(message.sent_at || 0).getTime() - new Date(update.sent_at || 0).getTime()) < 60_000
            ));
            if (echoIndex >= 0) {
                list[echoIndex] = withStableIdentity({
                    ...list[echoIndex],
                    id: list[echoIndex].id || update.id,
                    status: list[echoIndex].status === 'pending' ? list[echoIndex].status : (update.status || list[echoIndex].status),
                }, list[echoIndex].stable_key || list[echoIndex].client_id || update.id);
                continue;
            }

            if (hasPendingOutbound || (Date.now() - lastOutboundConfirmAt) < 8_000) {
                // Wait for confirmOptimisticMessage — avoid race duplicates
                continue;
            }
        }

        list.push(withStableIdentity({ ...update }, update.id));
        added += 1;
    }

    localMessages.value = dedupeLocalMessages(added > 0 ? sortMessages(list) : list);

    if (selectedConversation.value?.id) {
        rememberMessages(selectedConversation.value.id, localMessages.value);
    }

    if (scroll && added > 0 && shouldStickToBottom) {
        scrollToBottom(false);
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
    if (pollInFlight || (!isMiniApp.value && document.hidden) || !messengerConnected.value) {
        return;
    }

    pollInFlight = true;

    try {
        const params = { since: pollSince };
        if (selectedConversation.value?.id) {
            params.conversation_id = selectedConversation.value.id;
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

        if (selectedConversation.value?.id) {
            mergeMessages(data?.messages || []);
            mergeConversations([{
                id: selectedConversation.value.id,
                unread_count: 0,
            }]);
        }

        if (data?.unread_count !== undefined) {
            syncAppBadge(data.unread_count);
        } else {
            syncAppBadge(localUnreadTotal.value);
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
        const hasPending = localMessages.value.some((message) => isOptimisticMessage(message));

        if (!hasPending) {
            localConversations.value = cloneList(conversations);
            return;
        }

        mergeConversations(conversations || []);
    },
    { deep: true },
);

let watchedConversationId = selectedConversation.value?.id ?? null;

watch(
    () => props.messages,
    (messages) => {
        const propId = props.selectedConversation?.id ?? null;
        const activeId = selectedConversation.value?.id ?? null;

        // Ignore stale Inertia props while SPA-navigating between chats.
        if (!propId || propId !== activeId) {
            return;
        }

        const serverMessages = cloneList(messages || []);
        const conversationChanged = propId !== watchedConversationId;
        watchedConversationId = propId;

        if (conversationChanged || localMessages.value.length === 0) {
            localMessages.value = serverMessages.map((message) => withStableIdentity(message, message.id));
            messageCache.set(propId, localMessages.value.map((message) => ({ ...message })));
            if (conversationChanged) {
                messageMenuId.value = null;
                replyToMessage.value = null;
            }
            shouldStickToBottom = true;
            scrollToBottom(false);
            return;
        }

        const serverById = new Map(serverMessages.map((message) => [String(message.id), message]));
        const usedServerIds = new Set();
        const next = [];

        for (const local of localMessages.value) {
            const localNumericId = numericMessageId(local.id);

            if (localNumericId) {
                usedServerIds.add(String(localNumericId));
                const server = serverById.get(String(localNumericId));
                if (server) {
                    next.push(withStableIdentity({
                        ...local,
                        ...server,
                        client_id: local.client_id,
                        stable_key: local.stable_key || local.client_id || local.id,
                        sent_at: local.sent_at || server.sent_at,
                        attachments: mergeConfirmedAttachments(local.attachments, server.attachments),
                        status: local.status === 'pending' ? local.status : (server.status || local.status),
                    }, local.stable_key || local.client_id || local.id));
                } else {
                    next.push(local);
                }
                continue;
            }

            if (isTmpMessage(local) || isOptimisticMessage(local)) {
                const matchedServer = [...serverById.values()].find((server) => (
                    !usedServerIds.has(String(server.id))
                    && optimisticContentMatches(local, server)
                ));

                if (matchedServer) {
                    usedServerIds.add(String(matchedServer.id));
                    next.push(patchOptimisticWithServer(local, matchedServer));
                } else {
                    next.push(local);
                }
                continue;
            }

            next.push(local);
        }

        for (const server of serverMessages) {
            if (usedServerIds.has(String(server.id))) {
                continue;
            }

            if (
                server.direction === 'outbound'
                && (
                    inFlightClientIds.size > 0
                    || next.some((message) => (
                        message.direction === 'outbound'
                        && optimisticContentMatches(message, server)
                        && Math.abs(new Date(message.sent_at || 0).getTime() - new Date(server.sent_at || 0).getTime()) < 60_000
                    ))
                )
            ) {
                continue;
            }

            next.push(withStableIdentity(server, server.id));
        }

        localMessages.value = dedupeLocalMessages(next);
        messageCache.set(propId, localMessages.value.map((message) => ({ ...message })));
    },
    { deep: true },
);

watch(
    () => props.linkedClient,
    (client) => {
        const propId = props.selectedConversation?.id ?? null;
        if (!propId || propId !== selectedConversation.value?.id) {
            return;
        }
        linkedClient.value = client ? { ...client } : null;
    },
    { deep: true },
);

watch(
    () => props.funnelDeal,
    (deal) => {
        const propId = props.selectedConversation?.id ?? null;
        if (!propId || propId !== selectedConversation.value?.id) {
            return;
        }
        funnelDeal.value = deal ? { ...deal } : null;
    },
    { deep: true },
);

watch(
    () => props.selectedConversation,
    (conversation) => {
        const propId = conversation?.id ?? null;
        if (!propId || propId !== selectedConversation.value?.id) {
            return;
        }
        selectedConversation.value = { ...conversation };
    },
    { deep: true },
);

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('click', closeMessageMenu);
    document.addEventListener('click', closeEmojiPicker);
    document.addEventListener('click', closeComposerAttachMenu);
    document.addEventListener('click', closeConversationMenu);
    document.addEventListener('click', closeHeaderMenu);
    startPolling();

    if (selectedConversation.value?.id && localMessages.value.length > 0 && companyId.value) {
        void saveCachedMessages(
            companyId.value,
            selectedConversation.value.id,
            localMessages.value,
        );
    }

    if (companyId.value) {
        void warmMessageCache(companyId.value, localConversations.value, messageCache);
    }

    if (isMiniApp.value) {
        const tg = window.Telegram?.WebApp;
        if (tg) {
            tg.ready();
            tg.expand();
            try {
                tg.setHeaderColor?.('#f0f2f5');
                tg.setBackgroundColor?.('#efeae2');
                tg.disableVerticalSwipes?.();
            } catch {
                // older clients
            }
        }

        registerNativePushToken();
    }

    showNoticesFromPageProps(page.props);

    removeInertiaSuccessListener = router.on('success', (event) => {
        showNoticesFromPageProps(event.detail?.page?.props);
    });

    removeInertiaErrorListener = router.on('error', (errors) => {
        const errorMessage = firstErrorMessage(errors, ['sync', 'client', 'stage', 'body']);
        if (errorMessage) {
            showChatToast(errorMessage, 'error', 2200);
        }
    });
});

async function registerNativePushToken() {
    const capacitor = window.Capacitor;
    if (!capacitor?.isNativePlatform?.()) {
        return;
    }

    try {
        const StatusBar = capacitor.Plugins?.StatusBar;
        if (StatusBar?.setOverlaysWebView) {
            await StatusBar.setOverlaysWebView({ overlay: false });
        }
        if (StatusBar?.setBackgroundColor) {
            await StatusBar.setBackgroundColor({ color: '#f0f2f5' });
        }
        if (StatusBar?.setStyle) {
            await StatusBar.setStyle({ style: 'DARK' });
        }
    } catch {
        // StatusBar plugin optional
    }

    const PushNotifications = capacitor.Plugins?.PushNotifications;
    if (!PushNotifications) {
        return;
    }

    try {
        let perm = await PushNotifications.checkPermissions();
        if (perm.receive !== 'granted') {
            perm = await PushNotifications.requestPermissions();
        }
        if (perm.receive !== 'granted') {
            return;
        }

        await PushNotifications.register();

        PushNotifications.addListener('registration', async (event) => {
            const token = event?.value;
            if (!token) {
                return;
            }

            try {
                await window.axios.post(route('device-tokens.store'), {
                    token,
                    platform: capacitor.getPlatform?.() || 'android',
                    app: 'messenger',
                }, { headers: jsonRequestHeaders });
            } catch {
                // ignore push registration errors
            }
        });

        PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
            const conversationId = Number(
                action?.notification?.data?.conversation_id
                || action?.notification?.data?.conversationId
                || 0,
            );
            if (conversationId > 0) {
                openConversation(conversationId);
            }
        });
    } catch {
        // Capacitor bridge missing plugins — ignore
    }
}

onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    document.removeEventListener('click', closeMessageMenu);
    document.removeEventListener('click', closeEmojiPicker);
    document.removeEventListener('click', closeComposerAttachMenu);
    document.removeEventListener('click', closeConversationMenu);
    document.removeEventListener('click', closeHeaderMenu);
    stopPolling();
    conversationLoadToken += 1;
    conversationAbort?.abort();
    conversationAbort = null;
    clearImagePreview();
    if (chatToastTimer) {
        window.clearTimeout(chatToastTimer);
        chatToastTimer = null;
    }
    removeInertiaSuccessListener?.();
    removeInertiaErrorListener?.();
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

function pipelineNameStyle(color) {
    if (!color) {
        return undefined;
    }

    return { color };
}

function messengerVisitParams(extra = {}) {
    return {
        ...(isMiniApp.value ? { mini: 1 } : {}),
        ...extra,
    };
}

function syncConversationUrl(conversationId = null) {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    if (conversationId) {
        url.searchParams.set('conversation', String(conversationId));
    } else {
        url.searchParams.delete('conversation');
    }

    if (isMiniApp.value) {
        url.searchParams.set('mini', '1');
    }

    const next = `${url.pathname}${url.search}${url.hash}`;
    window.history.replaceState(window.history.state, '', next);
}

function conversationFromList(id) {
    const row = localConversations.value.find((item) => item.id === id);
    if (!row) {
        return { id };
    }

    return {
        id: row.id,
        channel: row.channel,
        channel_label: row.channel_label,
        participant_name: row.participant_name,
        participant_username: row.participant_username,
        participant_id: row.participant_id,
        client_id: row.client_id ?? null,
        display_name: row.display_name,
        assigned_user_id: row.assigned_user_id,
        assigned_user_name: row.assigned_user_name,
    };
}

function rememberMessages(conversationId, messages) {
    if (!conversationId) {
        return;
    }

    const snapshot = (messages || []).map((message) => ({ ...message }));
    messageCache.set(conversationId, snapshot);

    if (companyId.value) {
        void saveCachedMessages(companyId.value, conversationId, snapshot);
    }
}

/**
 * Digits-only phone for WhatsApp deep links (wa.me / whatsapp://).
 * @param {unknown} value
 * @returns {string|null}
 */
function normalizeWhatsAppPhoneDigits(value) {
    if (value === null || value === undefined) {
        return null;
    }

    let digits = String(value).replace(/\D+/g, '');
    if (digits.length < 8 || digits.length > 15) {
        return null;
    }

    // Local KG mobile: 0XXXXXXXXX → 996XXXXXXXXX
    if (digits.startsWith('0') && digits.length === 10) {
        digits = `996${digits.slice(1)}`;
    }

    return digits;
}

const whatsAppCallPhone = computed(() => {
    const conversation = selectedConversation.value;
    if (!conversation) {
        return null;
    }

    const candidates = [
        linkedClient.value?.phone,
        linkedClient.value?.custom_fields?.phone,
        linkedClient.value?.custom_fields?.telefon,
        conversation.participant_username,
        conversation.channel === 'wappi' ? conversation.participant_id : null,
        conversation.participant_id,
    ];

    for (const candidate of candidates) {
        const digits = normalizeWhatsAppPhoneDigits(candidate);
        if (digits) {
            return digits;
        }
    }

    return null;
});

function openWhatsAppCall() {
    const digits = whatsAppCallPhone.value;
    if (!digits) {
        showChatToast(t('messenger.noPhoneForCall'), 'error', 2200);
        return;
    }

    const httpsUrl = `https://wa.me/${digits}`;
    const appUrl = `whatsapp://send?phone=${digits}`;
    const capacitor = typeof window !== 'undefined' ? window.Capacitor : null;

    if (capacitor?.isNativePlatform?.()) {
        try {
            window.location.href = appUrl;
            return;
        } catch {
            // fall through
        }

        const Browser = capacitor.Plugins?.Browser;
        if (Browser?.open) {
            void Browser.open({ url: httpsUrl });
            return;
        }
    }

    window.open(httpsUrl, '_blank', 'noopener,noreferrer');
}

async function loadConversationDetail(id) {
    const token = ++conversationLoadToken;

    if (conversationAbort) {
        conversationAbort.abort();
    }
    conversationAbort = new AbortController();

    const hasCache = messageCache.has(id);
    messagesLoading.value = !hasCache;

    try {
        const { data } = await window.axios.get(route('messenger.conversations.show', id), {
            headers: jsonRequestHeaders,
            signal: conversationAbort.signal,
        });

        if (token !== conversationLoadToken || selectedConversation.value?.id !== id) {
            return;
        }

        if (data?.conversation) {
            selectedConversation.value = { ...data.conversation };
        }
        linkedClient.value = data?.linkedClient ? { ...data.linkedClient } : null;
        funnelDeal.value = data?.funnelDeal ? { ...data.funnelDeal } : null;

        const incoming = (data?.messages || []).map((message) => withStableIdentity(message, message.id));
        const hasPending = localMessages.value.some((message) => isOptimisticMessage(message));

        if (hasPending) {
            mergeMessages(incoming, { scroll: false });
        } else {
            localMessages.value = incoming;
        }

        rememberMessages(id, localMessages.value);
        watchedConversationId = id;
        mergeConversations([{ id, unread_count: 0 }]);

        if (data?.unread_count !== undefined) {
            syncAppBadge(data.unread_count);
        } else {
            syncAppBadge(localUnreadTotal.value);
        }

        shouldStickToBottom = true;
        scrollToBottom(false);
    } catch (error) {
        if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
            return;
        }
        if (token === conversationLoadToken) {
            showChatToast(
                error?.response?.data?.message || t('messenger.err.message'),
                'error',
                2200,
            );
        }
    } finally {
        if (token === conversationLoadToken) {
            messagesLoading.value = false;
        }
    }
}

function openConversation(id) {
    const conversationId = Number(id);
    if (!conversationId) {
        return;
    }

    messageMenuId.value = null;
    conversationMenuId.value = null;
    replyToMessage.value = null;
    emojiPickerOpen.value = false;
    composerAttachOpen.value = false;
    sendError.value = '';

    if (selectedConversation.value?.id && selectedConversation.value.id !== conversationId) {
        rememberMessages(selectedConversation.value.id, localMessages.value);
    }

    selectedConversation.value = conversationFromList(conversationId);
    linkedClient.value = null;
    funnelDeal.value = null;
    mergeConversations([{ id: conversationId, unread_count: 0 }]);
    syncConversationUrl(conversationId);

    const cached = messageCache.get(conversationId);
    if (cached?.length) {
        localMessages.value = cached.map((message) => ({ ...message }));
        watchedConversationId = conversationId;
        shouldStickToBottom = true;
        scrollToBottom(false);
    } else {
        localMessages.value = [];
        watchedConversationId = conversationId;

        // Instant open from disk while network refreshes in background.
        if (companyId.value) {
            void loadCachedMessages(companyId.value, conversationId).then((disk) => {
                if (
                    !disk?.length
                    || selectedConversation.value?.id !== conversationId
                    || localMessages.value.length > 0
                ) {
                    return;
                }

                messageCache.set(conversationId, disk.map((message) => ({ ...message })));
                localMessages.value = disk.map((message) => ({ ...message }));
                shouldStickToBottom = true;
                scrollToBottom(false);
            });
        }
    }

    loadConversationDetail(conversationId);
}

function closeConversationMenu() {
    conversationMenuId.value = null;
}

function closeHeaderMenu() {
    headerMenuOpen.value = false;
}

function toggleHeaderMenu(event) {
    event?.stopPropagation?.();
    headerMenuOpen.value = !headerMenuOpen.value;
}

function openConversationMenu(conversationId, event) {
    event?.preventDefault?.();
    event?.stopPropagation?.();
    conversationMenuId.value = conversationMenuId.value === conversationId ? null : conversationId;
}

let conversationLongPressTimer = null;
let conversationLongPressFired = false;

function onConversationPointerDown(conversationId, event) {
    if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
    }

    conversationLongPressFired = false;
    clearTimeout(conversationLongPressTimer);
    conversationLongPressTimer = window.setTimeout(() => {
        conversationLongPressFired = true;
        conversationMenuId.value = conversationId;
    }, 480);
}

function onConversationPointerUp() {
    clearTimeout(conversationLongPressTimer);
    conversationLongPressTimer = null;
}

function onConversationClick(conversationId) {
    if (conversationLongPressFired) {
        conversationLongPressFired = false;
        return;
    }

    openConversation(conversationId);
}

async function markConversationAsUnread(conversation) {
    if (!conversation?.id) {
        return;
    }

    closeConversationMenu();

    try {
        const { data } = await window.axios.post(
            route('messenger.mark-unread', conversation.id),
            {},
            { headers: jsonRequestHeaders },
        );

        const unreadCount = Math.max(1, Number(data?.unread_count ?? 1));

        localConversations.value = localConversations.value.map((item) => (
            item.id === conversation.id
                ? { ...item, unread_count: unreadCount }
                : item
        ));

        if (selectedConversation.value?.id === conversation.id) {
            backToConversationList();
        }

        showChatToast(t('messenger.markedUnreadToast'), 'success', 1600);
        syncAppBadge(localUnreadTotal.value);
    } catch (error) {
        showChatToast(
            error?.response?.data?.message || t('messenger.err.message'),
            'error',
            2200,
        );
    }
}

async function markAllConversationsAsRead() {
    if (markingAllRead.value) {
        return;
    }

    closeHeaderMenu();
    markingAllRead.value = true;

    try {
        await window.axios.post(
            route('messenger.mark-all-read'),
            {},
            { headers: jsonRequestHeaders },
        );

        localConversations.value = localConversations.value.map((item) => (
            item.unread_count
                ? { ...item, unread_count: 0 }
                : item
        ));

        showChatToast(t('messenger.markedAllReadToast'), 'success', 1600);
        syncAppBadge(0);
    } catch (error) {
        showChatToast(
            error?.response?.data?.message || t('messenger.err.message'),
            'error',
            2200,
        );
    } finally {
        markingAllRead.value = false;
    }
}

function backToConversationList() {
    if (selectedConversation.value?.id) {
        rememberMessages(selectedConversation.value.id, localMessages.value);
    }

    conversationLoadToken += 1;
    if (conversationAbort) {
        conversationAbort.abort();
        conversationAbort = null;
    }

    messageMenuId.value = null;
    conversationMenuId.value = null;
    replyToMessage.value = null;
    emojiPickerOpen.value = false;
    composerAttachOpen.value = false;
    messagesLoading.value = false;
    selectedConversation.value = null;
    linkedClient.value = null;
    funnelDeal.value = null;
    localMessages.value = [];
    watchedConversationId = null;
    syncConversationUrl(null);
}

function messagePlainText(message) {
    const body = (message?.body || '').trim();
    if (body) {
        return body;
    }

    const first = Array.isArray(message?.attachments) ? message.attachments[0] : null;
    if (!first) {
        return '';
    }

    return attachmentLabel(first.type);
}

function messagePreviewShort(message, max = 80) {
    const text = messagePlainText(message);
    if (text.length <= max) {
        return text;
    }

    return `${text.slice(0, max - 1)}…`;
}

function openMessageMenu(message, event) {
    if (!message || String(message.id).startsWith('tmp-') || message.status === 'pending') {
        messageMenuId.value = null;
        return;
    }

    if (messageMenuId.value === message.id) {
        messageMenuId.value = null;
        return;
    }

    const bubble = event?.currentTarget;
    const rect = bubble?.getBoundingClientRect?.();
    const menuHeight = 220;
    const spaceBelow = rect ? window.innerHeight - rect.bottom : 0;
    const spaceAbove = rect ? rect.top : 0;

    if (spaceBelow < menuHeight && spaceAbove > spaceBelow) {
        messageMenuPlacement.value = 'above';
    } else {
        messageMenuPlacement.value = 'below';
    }

    messageMenuId.value = message.id;
}

function closeMessageMenu() {
    messageMenuId.value = null;
}

async function copyMessage(message) {
    const text = messagePlainText(message);
    if (!text) {
        closeMessageMenu();
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
        showChatToast(t('messenger.copiedToast'), 'success', 1400);
    } catch {
        // Fallback for older WebViews
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
        showChatToast(t('messenger.copiedToast'), 'success', 1400);
    }

    closeMessageMenu();
}

function editMessage(message) {
    const text = (message?.body || '').trim();
    if (!text) {
        closeMessageMenu();
        return;
    }

    sendForm.body = text;
    closeMessageMenu();
    nextTick(() => messageInput.value?.focus());
}

function replyToChatMessage(message) {
    if (!message) {
        return;
    }

    replyToMessage.value = {
        id: message.id,
        body: messagePlainText(message),
        direction: message.direction,
    };
    closeMessageMenu();
    nextTick(() => messageInput.value?.focus());
}

function clearReplyToMessage() {
    replyToMessage.value = null;
}

function canEditMessage(message) {
    return Boolean((message?.body || '').trim());
}

function canCopyMessage(message) {
    return Boolean(messagePlainText(message));
}

function saveMessageAsQuickReply(message) {
    closeMessageMenu();
    openSaveQuickReplyModal(message);
}

function buildSendBodyWithReply(body) {
    if (!replyToMessage.value) {
        return body;
    }

    const quote = messagePreviewShort(replyToMessage.value, 120);
    if (!quote) {
        return body;
    }

    return body ? `«${quote}»\n\n${body}` : `«${quote}»`;
}

function onSalePending({ clientId, total, currency: saleCurrency }) {
    const totalLabel = Number(total || 0).toLocaleString(localeTag(locale.value), {
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
                name: t('messenger.receipt'),
                mime_type: 'image/png',
                loading: true,
            }],
            status: 'pending',
            pending_kind: 'receipt',
            sent_at: new Date().toISOString(),
            loading_label: t('messenger.saleCurrencyLabel', {
                total: totalLabel,
                currency: saleCurrency || '',
            }).trim(),
        },
    ];
    shouldStickToBottom = true;
    scrollToBottom();
    touchConversationAfterSend(
        { id: selectedConversation.value?.id, last_message_at: new Date().toISOString() },
        t('messenger.receipt'),
    );
}

async function onSaleFinished({ clientId, ok, message, warning }) {
    if (ok) {
        localMessages.value = localMessages.value.filter((item) => item.id !== clientId);
        sendError.value = '';
        if (warning) {
            showChatToast(warning, 'success');
        }
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
                body: message || t('messenger.err.saleCreate'),
                attachments: [{
                    type: 'image',
                    url: '',
                    name: t('messenger.receipt'),
                    loading: false,
                    failed: true,
                }],
            }
            : item
    ));
    setSendError(message || t('messenger.err.saleCreate'));
}

async function onQuoteFinished({ ok, message }) {
    if (ok) {
        await pollUpdates();
        shouldStickToBottom = true;
        scrollToBottom();
        return;
    }

    setSendError(message || t('messenger.err.calcSend'));
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
    closeHeaderMenu();
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
    if (!selectedConversation.value) {
        return;
    }

    if (reply.type === 'text') {
        sendForm.body = reply.body || '';
        nextTick(() => messageInput.value?.focus());

        return;
    }

    const clientId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const previewBody = reply.type === 'audio'
        ? t('messenger.voiceLabel')
        : (reply.body || t('messenger.imageLabel'));

    localMessages.value = [
        ...localMessages.value,
        {
            id: clientId,
            client_id: clientId,
            stable_key: clientId,
            direction: 'outbound',
            body: previewBody,
            attachments: [],
            status: 'pending',
            sent_at: new Date().toISOString(),
        },
    ];
    inFlightClientIds.add(clientId);
    sendForm.reset('body');
    sendError.value = '';
    shouldStickToBottom = true;
    scrollToBottom(true);
    touchConversationAfterSend(
        { id: selectedConversation.value.id, last_message_at: new Date().toISOString() },
        previewBody,
    );

    try {
        const { data } = await window.axios.post(
            route('messenger.send-quick-reply', [selectedConversation.value.id, reply.id]),
            {},
            { headers: jsonRequestHeaders },
        );

        confirmOptimisticMessage(clientId, data?.message);
        if (data?.conversation) {
            touchConversationAfterSend(data.conversation, previewBody);
        }
    } catch (error) {
        inFlightClientIds.delete(clientId);
        localMessages.value = localMessages.value.map((message) => (
            message.id === clientId
                ? { ...message, status: 'failed' }
                : message
        ));
        setSendError(
            error?.response?.data?.message
            || error?.response?.data?.errors?.body?.[0]
            || t('messenger.err.template'),
        );
    }
}

function quickReplyPreview(reply) {
    if (reply.type === 'text') {
        return reply.body;
    }

    if (reply.type === 'audio') {
        return reply.body || t('messenger.voiceLabel');
    }

    return reply.body || t('messenger.imageLabel');
}

function onMessageInputKeydown(event) {
    if (slashQuickRepliesOpen.value && filteredSlashQuickReplies.value.length > 0) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            slashActiveIndex.value = (slashActiveIndex.value + 1) % filteredSlashQuickReplies.value.length;
            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            slashActiveIndex.value = (slashActiveIndex.value - 1 + filteredSlashQuickReplies.value.length)
                % filteredSlashQuickReplies.value.length;
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            applyQuickReply(filteredSlashQuickReplies.value[slashActiveIndex.value]);
            return;
        }
        if (event.key === 'Escape') {
            sendForm.body = '';
            return;
        }
    }

    // Enter = new line (especially on mobile). Ctrl/Cmd+Enter = send.
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        sendMessage();
    }
}

function resizeMessageInput() {
    const el = messageInput.value;
    if (!el) {
        return;
    }

    el.style.height = 'auto';
    const maxHeight = 120;
    el.style.height = `${Math.min(el.scrollHeight, maxHeight)}px`;
}

function toggleEmojiPicker() {
    composerAttachOpen.value = false;
    emojiPickerOpen.value = !emojiPickerOpen.value;
}

function closeEmojiPicker() {
    emojiPickerOpen.value = false;
}

function toggleComposerAttachMenu(event) {
    event?.stopPropagation?.();
    emojiPickerOpen.value = false;
    composerAttachOpen.value = !composerAttachOpen.value;
}

function closeComposerAttachMenu() {
    composerAttachOpen.value = false;
}

function insertEmoji(emoji) {
    const el = messageInput.value;
    const value = sendForm.body || '';
    const start = el?.selectionStart ?? value.length;
    const end = el?.selectionEnd ?? start;

    sendForm.body = `${value.slice(0, start)}${emoji}${value.slice(end)}`;
    emojiPickerOpen.value = false;

    nextTick(() => {
        el?.focus();
        const pos = start + emoji.length;
        try {
            el?.setSelectionRange(pos, pos);
        } catch {
            // some WebViews ignore selection APIs
        }
        resizeMessageInput();
    });
}

async function sendMessage() {
    emojiPickerOpen.value = false;
    composerAttachOpen.value = false;
    if (!selectedConversation.value || slashQuickRepliesOpen.value) {
        return;
    }

    const typedBody = sendForm.body.trim();
    const body = buildSendBodyWithReply(typedBody);
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
            stable_key: clientId,
            direction: 'outbound',
            body,
            attachments: optimisticAttachments,
            status: 'pending',
            sent_at: new Date().toISOString(),
        },
    ];

    inFlightClientIds.add(clientId);

    const formData = new FormData();
    formData.append('body', body);
    if (imageFile) {
        formData.append('image', imageFile);
    }

    sendForm.reset('body', 'audio', 'image');
    replyToMessage.value = null;
    messageMenuId.value = null;
    imagePreviewUrl.value = null;
    if (imageInput.value) {
        imageInput.value.value = '';
    }
    nextTick(() => resizeMessageInput());
    sendError.value = '';
    shouldStickToBottom = true;
    scrollToBottom(true);
    touchConversationAfterSend(
        { id: selectedConversation.value.id, last_message_at: new Date().toISOString() },
        body || t('messenger.imageLabel'),
    );

    try {
        const { data } = await window.axios.post(
            route('messenger.send', selectedConversation.value.id),
            formData,
            { headers: jsonRequestHeaders },
        );

        confirmOptimisticMessage(clientId, data?.message);
        if (previewUrl && data?.message) {
            URL.revokeObjectURL(previewUrl);
        }
        if (data?.conversation) {
            touchConversationAfterSend(data.conversation, body);
        }
    } catch (error) {
        inFlightClientIds.delete(clientId);
        localMessages.value = localMessages.value.map((message) => (
            message.id === clientId
                ? { ...message, status: 'failed' }
                : message
        ));
        setSendError(
            error?.response?.data?.message
            || error?.response?.data?.errors?.body?.[0]
            || t('messenger.err.message'),
        );
        if (!sendForm.body && typedBody) {
            sendForm.body = typedBody;
        }
    }
}

async function improveWithAi() {
    if (!props.chatGptConnected || aiImproving.value || isRecording.value) {
        return;
    }

    const body = sendForm.body.trim();
    if (!body) {
        setAiError(t('messenger.err.aiEmpty'));
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
        setAiError(
            error?.response?.data?.message
            || t('messenger.err.ai'),
        );
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
    const saved = linkedClient.value?.custom_fields?.[key];
    if (saved !== undefined && saved !== null && String(saved).trim() !== '') {
        return String(saved);
    }

    return apiValueForField(key);
}

const messengerApiName = computed(() => {
    return selectedConversation.value?.participant_name?.trim() || '';
});

const messengerApiContact = computed(() => {
    const conversation = selectedConversation.value;
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
    const conversation = selectedConversation.value;
    if (!conversation) {
        return '';
    }

    const normalizedKey = key.toLowerCase();
    const rawContact = messengerApiContact.value.replace(/^@/, '').trim();
    const looksLikePhone = /^\+?\d[\d\s()-]{6,}$/.test(rawContact);
    const isWhatsApp = conversation.channel === 'wappi';

    if (['name', 'imya', 'fio', 'full_name', 'fullname'].includes(normalizedKey)) {
        return messengerApiName.value || (!isWhatsApp && !looksLikePhone ? rawContact : '');
    }

    if (['phone', 'telefon', 'nomer', 'number', 'tel'].includes(normalizedKey)) {
        if (isWhatsApp || looksLikePhone) {
            return rawContact;
        }

        return linkedClient.value?.phone ? String(linkedClient.value.phone) : '';
    }

    if (['username', 'login', 'nick'].includes(normalizedKey)) {
        if (!isWhatsApp && !looksLikePhone && rawContact) {
            return messengerApiContact.value;
        }

        return messengerApiName.value;
    }

    return '';
}

function isNameLikeFieldKey(key) {
    return ['name', 'imya', 'fio', 'full_name', 'fullname', 'username', 'login', 'nick'].includes(
        String(key || '').toLowerCase(),
    );
}

function isPhoneLikeFieldKey(key) {
    return ['phone', 'telefon', 'nomer', 'number', 'tel'].includes(
        String(key || '').toLowerCase(),
    );
}

function applyMessengerNameToField(key) {
    if (messengerApiName.value) {
        clientForm.fields[key] = messengerApiName.value;
    }
}

function applyMessengerContactToField(key) {
    if (!messengerApiContact.value) {
        return;
    }

    const value = isPhoneLikeFieldKey(key)
        ? messengerApiContact.value.replace(/^@/, '')
        : messengerApiContact.value;

    clientForm.fields[key] = value;
}

function applyMessengerNameToForm() {
    if (!messengerApiName.value) {
        return;
    }

    for (const definition of props.clientFieldDefinitions) {
        if (isNameLikeFieldKey(definition.key)) {
            clientForm.fields[definition.key] = messengerApiName.value;
        }
    }
}

function applyMessengerContactToForm() {
    if (!messengerApiContact.value) {
        return;
    }

    const phone = messengerApiContact.value.replace(/^@/, '');

    for (const definition of props.clientFieldDefinitions) {
        if (isPhoneLikeFieldKey(definition.key)) {
            clientForm.fields[definition.key] = phone;
        } else if (['username', 'login', 'nick'].includes(String(definition.key || '').toLowerCase())) {
            clientForm.fields[definition.key] = messengerApiContact.value;
        }
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
    if (!selectedConversation.value) {
        return;
    }

    clientForm.post(route('messenger.save-client', selectedConversation.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showClientModal.value = false;
        },
    });
}

const dealUpdating = ref(false);

function applyFunnelDealUpdate(payload) {
    if (!payload?.funnelDeal && !payload?.conversation) {
        return;
    }

    if (payload.funnelDeal) {
        funnelDeal.value = { ...payload.funnelDeal };
    }

    if (payload.conversation?.id) {
        mergeConversations([payload.conversation]);
    }
}

async function updateDealStage() {
    if (!selectedConversation.value || !funnelDeal.value || !dealStageForm.stage_id || dealUpdating.value) {
        return;
    }

    if (Number(dealStageForm.stage_id) === funnelDeal.value.stage_id) {
        return;
    }

    const conversationId = selectedConversation.value.id;
    const stageId = Number(dealStageForm.stage_id);
    const stage = (funnelDeal.value.stages || []).find((item) => Number(item.id) === stageId);
    const previous = { ...funnelDeal.value };

    dealUpdating.value = true;
    funnelDeal.value = {
        ...funnelDeal.value,
        stage_id: stageId,
        stage_name: stage?.name || funnelDeal.value.stage_name,
        stage_color: stage?.color || funnelDeal.value.stage_color,
    };
    mergeConversations([{
        id: conversationId,
        stage_id: stageId,
        stage_name: stage?.name,
        stage_color: stage?.color,
    }]);

    try {
        const { data } = await window.axios.patch(
            route('messenger.update-deal-stage', conversationId),
            { stage_id: stageId },
            { headers: jsonRequestHeaders },
        );
        applyFunnelDealUpdate(data);
    } catch (error) {
        funnelDeal.value = previous;
        dealStageForm.stage_id = previous.stage_id ? String(previous.stage_id) : '';
        showChatToast(
            error?.response?.data?.message
            || error?.response?.data?.errors?.stage?.[0]
            || t('messenger.err.message'),
            'error',
            2800,
        );
    } finally {
        dealUpdating.value = false;
    }
}

async function updateDealPipeline() {
    if (!selectedConversation.value || !dealPipelineForm.pipeline_id || dealUpdating.value) {
        return;
    }

    if (Number(dealPipelineForm.pipeline_id) === funnelDeal.value?.pipeline_id) {
        return;
    }

    const pipeline = (props.filterPipelines || []).find(
        (item) => Number(item.id) === Number(dealPipelineForm.pipeline_id),
    );
    const firstStage = pipeline?.stages?.[0];
    if (!firstStage || !pipeline) {
        return;
    }

    const conversationId = selectedConversation.value.id;
    const previous = funnelDeal.value ? { ...funnelDeal.value } : null;

    dealPipelineForm.stage_id = String(firstStage.id);
    dealUpdating.value = true;

    funnelDeal.value = {
        ...(funnelDeal.value || {}),
        pipeline_id: Number(pipeline.id),
        pipeline_name: pipeline.name,
        pipeline_color: pipeline.color,
        pipeline_icon: pipeline.icon,
        stage_id: Number(firstStage.id),
        stage_name: firstStage.name,
        stage_color: firstStage.color,
        stages: (pipeline.stages || []).map((item) => ({ ...item })),
    };
    mergeConversations([{
        id: conversationId,
        pipeline_id: Number(pipeline.id),
        pipeline_name: pipeline.name,
        pipeline_color: pipeline.color,
        pipeline_icon: pipeline.icon,
        stage_id: Number(firstStage.id),
        stage_name: firstStage.name,
        stage_color: firstStage.color,
    }]);

    try {
        const { data } = await window.axios.patch(
            route('messenger.update-deal-pipeline', conversationId),
            {
                pipeline_id: Number(pipeline.id),
                stage_id: Number(firstStage.id),
            },
            { headers: jsonRequestHeaders },
        );
        applyFunnelDealUpdate(data);
    } catch (error) {
        if (previous) {
            funnelDeal.value = previous;
            dealPipelineForm.pipeline_id = previous.pipeline_id ? String(previous.pipeline_id) : '';
        }
        showChatToast(
            error?.response?.data?.message
            || error?.response?.data?.errors?.pipeline?.[0]
            || t('messenger.err.message'),
            'error',
            2800,
        );
    } finally {
        dealUpdating.value = false;
    }
}

watch(
    () => funnelDeal.value?.stage_id,
    (stageId) => {
        dealStageForm.stage_id = stageId ? String(stageId) : '';
        dealStageForm.clearErrors();
    },
    { immediate: true },
);

watch(
    () => funnelDeal.value?.pipeline_id,
    (pipelineId) => {
        dealPipelineForm.pipeline_id = pipelineId ? String(pipelineId) : '';
        dealPipelineForm.clearErrors();
    },
    { immediate: true },
);

watch(
    () => [selectedConversation.value?.id, linkedClient.value?.id, props.clientFieldDefinitions],
    () => {
        if (showClientModal.value) {
            resetClientForm();
        }
    },
    { deep: true },
);

function isRecorderMimeSupported(type) {
    try {
        return typeof MediaRecorder !== 'undefined'
            && typeof MediaRecorder.isTypeSupported === 'function'
            && MediaRecorder.isTypeSupported(type);
    } catch {
        return false;
    }
}

function pickRecorderMimeType(channel) {
    if (typeof MediaRecorder === 'undefined') {
        return null;
    }

    const mobileFriendlyTypes = ['audio/mp4', 'audio/aac', 'audio/webm'];

    if (channel === 'instagram' || channel === 'facebook') {
        const supported = mobileFriendlyTypes.find((type) => isRecorderMimeSupported(type));

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
            'audio/aac',
        ]
        : [
            'audio/mp4',
            'audio/aac',
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
        ];

    return candidates.find((type) => isRecorderMimeSupported(type)) ?? '';
}

async function startRecording() {
    if (!selectedConversation.value || isRecording.value) {
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
        showChatToast(t('messenger.err.voiceUnsupported'), 'error', 2800);
        return;
    }

    try {
        // Capacitor Android needs RECORD_AUDIO in the manifest + a real getUserMedia prompt
        recordingStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
            },
        });
        const mimeType = pickRecorderMimeType(selectedConversation.value.channel);

        if (selectedConversation.value.channel === 'instagram' && ! mimeType) {
            stopRecordingTracks();
            showChatToast(t('messenger.igFormatLong'), 'error', 3200);

            return;
        }

        audioChunks = [];
        try {
            mediaRecorder = mimeType
                ? new MediaRecorder(recordingStream, { mimeType })
                : new MediaRecorder(recordingStream);
        } catch {
            mediaRecorder = new MediaRecorder(recordingStream);
        }

        mediaRecorder.ondataavailable = (event) => {
            if (event.data?.size > 0) {
                audioChunks.push(event.data);
            }
        };

        mediaRecorder.onerror = () => {
            stopRecordingTracks();
            isRecording.value = false;
            window.clearInterval(recordingTimer);
            recordingTimer = null;
            mediaRecorder = null;
            showChatToast(t('messenger.err.voiceSend'), 'error', 2800);
        };

        mediaRecorder.onstop = () => {
            stopRecordingTracks();
            void sendVoiceMessage();
        };

        // timeslice helps some Android WebViews flush chunks reliably
        try {
            mediaRecorder.start(250);
        } catch {
            mediaRecorder.start();
        }
        isRecording.value = true;
        recordingSeconds.value = 0;
        recordingTimer = window.setInterval(() => {
            recordingSeconds.value += 1;
        }, 1000);
    } catch (error) {
        stopRecordingTracks();
        mediaRecorder = null;
        isRecording.value = false;
        const name = String(error?.name || '');
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            showChatToast(t('messenger.err.mic'), 'error', 2800);
            return;
        }
        showChatToast(
            error?.message ? `${t('messenger.err.mic')} (${error.message})` : t('messenger.err.mic'),
            'error',
            3200,
        );
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
    const conversationId = selectedConversation.value?.id;
    if (!conversationId) {
        return;
    }

    const chunks = Array.isArray(audioChunks) ? [...audioChunks] : [];
    const recorderMime = mediaRecorder?.mimeType || '';
    audioChunks = [];
    mediaRecorder = null;

    if (chunks.length === 0) {
        showChatToast(t('messenger.err.voiceSend'), 'error', 2200);
        return;
    }

    try {
        const mimeType = recorderMime || chunks[0]?.type || 'audio/webm';
        const channel = selectedConversation.value?.channel || '';
        const extension = channel === 'instagram'
            ? (mimeType.includes('wav') ? 'wav' : 'm4a')
            : channel === 'wappi'
                ? (mimeType.includes('ogg') ? 'ogg' : (mimeType.includes('mp4') || mimeType.includes('aac') ? 'm4a' : 'webm'))
                : ((mimeType.includes('mp4') || mimeType.includes('aac')) ? 'm4a' : 'webm');
        const blob = new Blob(chunks, { type: mimeType || 'application/octet-stream' });
        if (blob.size < 1) {
            showChatToast(t('messenger.err.voiceSend'), 'error', 2200);
            return;
        }

        const fileName = `voice.${extension}`;
        // Prefer Blob+filename for Android WebViews where `new File()` can throw.
        let uploadPart = blob;
        try {
            if (typeof File !== 'undefined') {
                uploadPart = new File([blob], fileName, { type: mimeType || blob.type });
            }
        } catch {
            uploadPart = blob;
        }

        const clientId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const localUrl = URL.createObjectURL(blob);

        localMessages.value = [
            ...localMessages.value,
            {
                id: clientId,
                client_id: clientId,
                stable_key: clientId,
                direction: 'outbound',
                body: '',
                attachments: [{
                    type: 'audio',
                    url: localUrl,
                    name: fileName,
                    mime_type: mimeType,
                }],
                status: 'pending',
                sent_at: new Date().toISOString(),
            },
        ];
        inFlightClientIds.add(clientId);
        sendError.value = '';
        shouldStickToBottom = true;
        scrollToBottom(true);
        touchConversationAfterSend(
            { id: conversationId, last_message_at: new Date().toISOString() },
            t('messenger.voiceLabel'),
        );

        const formData = new FormData();
        formData.append('body', '');
        formData.append('audio', uploadPart, fileName);

        try {
            const { data } = await window.axios.post(
                route('messenger.send', conversationId),
                formData,
                { headers: jsonRequestHeaders },
            );

            confirmOptimisticMessage(clientId, data?.message);
            if (data?.message) {
                URL.revokeObjectURL(localUrl);
            }
            if (data?.conversation) {
                touchConversationAfterSend(data.conversation, t('messenger.voiceLabel'));
            }
        } catch (error) {
            inFlightClientIds.delete(clientId);
            localMessages.value = localMessages.value.map((message) => (
                message.id === clientId
                    ? { ...message, status: 'failed' }
                    : message
            ));
            setSendError(
                error?.response?.data?.message
                || error?.response?.data?.errors?.audio?.[0]
                || error?.response?.data?.errors?.body?.[0]
                || error?.message
                || t('messenger.err.voiceSend'),
            );
        }
    } catch (error) {
        showChatToast(
            error?.message || t('messenger.err.voiceSend'),
            'error',
            2800,
        );
    }
}

function formatRecordingTime(seconds) {
    const value = Number(seconds?.value ?? seconds);
    const total = Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0;
    const mins = Math.floor(total / 60);
    const secs = total % 60;

    return `${mins}:${String(secs).padStart(2, '0')}`;
}

function formatTime(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString(localeTag(locale.value), {
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
        return date.toLocaleTimeString(localeTag(locale.value), {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleDateString(localeTag(locale.value), {
        day: '2-digit',
        month: '2-digit',
    });
}

function formatMessageTime(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleTimeString(localeTag(locale.value), {
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
        return t('messenger.today');
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return t('messenger.yesterday');
    }

    return date.toLocaleDateString(localeTag(locale.value), {
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
            key: `message-${messageStableKey(message)}`,
            message,
        });
    }

    return items;
});

function outboundTicks(message) {
    if (message.status === 'failed') {
        return '!';
    }

    // Always show double ticks immediately so send doesn't flicker single → double
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
    if (
        selectedConversation.value?.id
        && conversation?.id === selectedConversation.value.id
        && linkedClient.value
    ) {
        const savedName = String(linkedClient.value.name || '').trim();
        const savedCustomName = String(
            linkedClient.value.custom_fields?.name
            || linkedClient.value.custom_fields?.imya
            || linkedClient.value.custom_fields?.fio
            || '',
        ).trim();

        if (savedCustomName) {
            return savedCustomName;
        }

        if (savedName && savedName !== 'Клиент') {
            return savedName;
        }
    }

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
        return t('messenger.voicePlain');
    case 'image':
        return t('messenger.photo');
    case 'video':
        return t('messenger.video');
    case 'file':
        return t('messenger.file');
    default:
        return t('messenger.attachment');
    }
}

function messageHasContent(message) {
    return Boolean(message.body?.trim())
        || (Array.isArray(message.attachments) && message.attachments.length > 0);
}

/**
 * Split message text into plain text / link segments (URLs + phone numbers).
 * @returns {Array<{ type: 'text'|'link', value: string, href?: string }>}
 */
function linkifyMessageSegments(text) {
    const source = String(text ?? '');
    if (source === '') {
        return [];
    }

    const urlPattern = /https?:\/\/[^\s<]+|www\.[^\s<]+/gi;
    const phonePattern = /(?:\+|00)?(?:\d[\s\-().]*){8,}\d/g;
    /** @type {Array<{ start: number, end: number, type: 'url'|'phone', raw: string }>} */
    const matches = [];

    for (const match of source.matchAll(urlPattern)) {
        matches.push({
            start: match.index,
            end: match.index + match[0].length,
            type: 'url',
            raw: match[0],
        });
    }

    for (const match of source.matchAll(phonePattern)) {
        const start = match.index;
        const end = start + match[0].length;
        const digits = match[0].replace(/\D+/g, '');
        if (digits.length < 9 || digits.length > 15) {
            continue;
        }
        if (matches.some((item) => start < item.end && end > item.start)) {
            continue;
        }
        matches.push({ start, end, type: 'phone', raw: match[0] });
    }

    matches.sort((a, b) => a.start - b.start || b.end - a.end);

    const segments = [];
    let cursor = 0;

    for (const match of matches) {
        if (match.start < cursor) {
            continue;
        }

        if (match.start > cursor) {
            segments.push({ type: 'text', value: source.slice(cursor, match.start) });
        }

        if (match.type === 'url') {
            let raw = match.raw;
            let trailing = '';
            while (/[),.;:!?]$/.test(raw) && !/[/?#&=]$/.test(raw.slice(0, -1))) {
                trailing = `${raw.slice(-1)}${trailing}`;
                raw = raw.slice(0, -1);
            }

            const href = raw.startsWith('http') ? raw : `https://${raw}`;
            segments.push({ type: 'link', value: raw, href, external: true });
            if (trailing) {
                segments.push({ type: 'text', value: trailing });
            }
        } else {
            let digits = match.raw.replace(/\D+/g, '');
            if (digits.startsWith('0') && digits.length === 10) {
                digits = `996${digits.slice(1)}`;
            }

            const href = (digits.startsWith('996') || match.raw.trim().startsWith('+') || digits.length >= 11)
                ? `https://wa.me/${digits}`
                : `tel:${digits}`;

            segments.push({
                type: 'link',
                value: match.raw,
                href,
                external: !href.startsWith('tel:'),
            });
        }

        cursor = match.end;
    }

    if (cursor < source.length) {
        segments.push({ type: 'text', value: source.slice(cursor) });
    }

    return segments.length > 0 ? segments : [{ type: 'text', value: source }];
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

function openSaveQuickReplyModal(message) {
    if (!canSaveAsQuickReply(message)) {
        return;
    }

    saveQuickReplyMessageId = message.id;
    saveQuickReplyPreview.value = message.body?.trim()
        || (message.attachments?.[0]?.type === 'image' ? t('messenger.photo') : t('messenger.voicePlain'));
    saveQuickReplyForm.clearErrors();
    saveQuickReplyForm.title = '';
    showSaveQuickReplyModal.value = true;
}

function tomorrowDateValue() {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function openTaskModal() {
    if (!selectedConversation.value) {
        return;
    }

    taskForm.clearErrors();
    taskForm.note = '';
    taskForm.due_on = tomorrowDateValue();
    showTaskModal.value = true;
}

function closeTaskModal() {
    showTaskModal.value = false;
    taskForm.reset();
    taskForm.clearErrors();
}

function submitTask() {
    if (!selectedConversation.value) {
        return;
    }

    taskForm.post(
        route('messenger.conversations.tasks.store', selectedConversation.value.id),
        {
            preserveScroll: true,
            onSuccess: () => closeTaskModal(),
        },
    );
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

function scrollToBottom(smooth = false) {
    nextTick(() => {
        messagesEnd.value?.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' });
    });
}

</script>

<template>
    <Head :title="t('messenger.title')" />

    <AuthenticatedLayout full-height>
        <div
            class="flex min-h-0 flex-1 flex-col overflow-hidden"
            :class="isMiniApp ? 'p-0' : 'p-0 sm:p-3'"
        >
        <div
            class="messenger-ui relative flex min-h-0 flex-1 overflow-hidden bg-white"
            :class="isMiniApp ? '' : 'rounded-xl border border-[#d1d7db] shadow-sm'"
        >
            <div
                class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center p-4 lg:left-[360px]"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-2 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-1 scale-95"
                >
                    <div
                        v-if="chatToast"
                        :key="chatToast.id"
                        class="flex max-w-[min(100%,22rem)] items-center gap-2.5 rounded-2xl px-4 py-3 text-left text-[13px] font-medium leading-snug shadow-[0_10px_30px_rgba(17,27,33,0.28)] backdrop-blur-md"
                        :class="chatToast.type === 'error'
                            ? 'bg-[#ea4335]/95 text-white'
                            : 'bg-[#111b21]/92 text-white'"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                            :class="chatToast.type === 'error' ? 'bg-white/20' : 'bg-[#00a884]/30'"
                        >
                            <svg
                                v-if="chatToast.type === 'error'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 6l12 12M18 6L6 18"
                                />
                            </svg>
                            <svg
                                v-else
                                class="h-4 w-4 text-[#25d366]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">{{ chatToast.message }}</span>
                    </div>
                </Transition>
            </div>

            <!-- Chat list -->
            <aside
                class="flex h-full min-h-0 w-full flex-col border-[#d1d7db] bg-white lg:w-[360px] lg:shrink-0 lg:border-r"
                :class="selectedConversation ? 'hidden lg:flex' : 'flex'"
            >
                <div
                    class="flex items-center justify-between gap-3 bg-[#f0f2f5] px-4 py-3"
                >
                    <h2 class="text-[20px] font-semibold leading-tight text-[#111b21]">
                        {{ t('messenger.chats') }}
                    </h2>
                    <div class="relative flex items-center gap-1">
                        <button
                            v-if="filterPipelines.length"
                            type="button"
                            class="rounded-full p-2 transition hover:bg-[#e9edef]"
                            :class="funnelFilterActive
                                ? 'bg-[#d9fdd3] text-[#008069]'
                                : 'text-[#54656f]'"
                            :title="t('messenger.filterTitleAttr')"
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
                        <button
                            v-if="messengerConnected"
                            type="button"
                            class="rounded-full p-2 text-[#54656f] transition hover:bg-[#e9edef]"
                            :class="{ 'bg-[#e9edef]': headerMenuOpen }"
                            :title="t('messenger.headerMenu')"
                            @click="toggleHeaderMenu"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="5" r="1.75" />
                                <circle cx="12" cy="12" r="1.75" />
                                <circle cx="12" cy="19" r="1.75" />
                            </svg>
                        </button>
                        <div
                            v-if="headerMenuOpen && messengerConnected"
                            class="absolute right-0 top-11 z-40 min-w-[14rem] overflow-hidden rounded-lg bg-white py-1 text-sm text-[#111b21] shadow-lg ring-1 ring-black/10"
                            @click.stop
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left hover:bg-[#f0f2f5] disabled:opacity-50"
                                :disabled="markingAllRead || localUnreadTotal === 0"
                                @click="markAllConversationsAsRead"
                            >
                                <span>{{ t('messenger.markAllRead') }}</span>
                            </button>
                            <Link
                                :href="route('messenger.quick-replies.index', isMiniApp ? { mini: 1 } : {})"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left hover:bg-[#f0f2f5]"
                                @click="closeHeaderMenu"
                            >
                                <span>{{ t('messenger.quickRepliesTitle') }}</span>
                            </Link>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left hover:bg-[#f0f2f5] disabled:opacity-50"
                                :disabled="syncing"
                                @click="syncConversations"
                            >
                                <span>{{ syncing ? t('messenger.refreshTitle') + '…' : t('messenger.refreshTitle') }}</span>
                            </button>
                        </div>
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
:placeholder="t('messenger.searchPh')"
                            class="w-full rounded-lg border-0 bg-[#f0f2f5] py-2.5 pl-10 pr-3 text-[15px] text-[#111b21] placeholder:text-[#8696a0] focus:ring-2 focus:ring-[#00a884]/30"
                        />
                    </div>
                </div>

                <div
                    v-if="!messengerConnected"
                    class="flex flex-1 items-center justify-center px-6 text-center text-sm text-[#667781]"
                >
                    {{ t('messenger.connectPrefix') }}
                    <Link
                        :href="route('integrations.index')"
                        class="ml-1 text-[#00a884] hover:underline"
                    >
                        {{ t('messenger.connectIntegrations') }}
                    </Link>.
                </div>

                <div
                    v-else-if="conversations.length === 0"
                    class="flex flex-1 items-center justify-center px-6 text-center text-sm text-[#667781]"
                >
                    {{ t('messenger.empty') }}
                </div>

                <ul
                    v-else
                    class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain"
                >
                    <li
                        v-for="conversation in filteredConversations"
                        :key="conversation.id"
                        class="relative"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-[#f5f6f6]"
                            :class="{
                                'bg-[#f0f2f5]': selectedConversation?.id === conversation.id,
                            }"
                            @click="onConversationClick(conversation.id)"
                            @contextmenu="openConversationMenu(conversation.id, $event)"
                            @pointerdown="onConversationPointerDown(conversation.id, $event)"
                            @pointerup="onConversationPointerUp"
                            @pointerleave="onConversationPointerUp"
                            @pointercancel="onConversationPointerUp"
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
                                        class="truncate text-[17px] leading-snug"
                                        :class="conversation.unread_count > 0 ? 'font-semibold text-[#111b21]' : 'text-[#111b21]'"
                                    >
                                        {{ participantLabel(conversation) }}
                                    </p>
                                    <span
                                        class="shrink-0 text-[13px]"
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
                                        v-if="conversation.pipeline_name || conversation.stage_name"
                                        class="flex min-w-0 items-center gap-1.5"
                                    >
                                        <span
                                            v-if="conversation.pipeline_name"
                                            class="inline-flex min-w-0 max-w-[75%] items-center gap-1 truncate rounded px-1.5 py-0.5 text-[12px] font-semibold leading-none"
                                            :style="stageBadgeStyle(conversation.pipeline_color)"
                                            :title="conversation.pipeline_name"
                                        >
                                            <span
                                                v-if="conversation.pipeline_icon"
                                                class="shrink-0 text-[13px] leading-none"
                                            >{{ conversation.pipeline_icon }}</span>
                                            <span class="truncate">
                                                {{ conversation.pipeline_name }}
                                            </span>
                                        </span>
                                        <span
                                            v-if="conversation.stage_name"
                                            class="min-w-0 truncate text-[13px] leading-tight text-[#667781]"
                                            :title="conversation.stage_name"
                                        >
                                            {{ conversation.stage_name }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </button>

                        <div
                            v-if="conversationMenuId === conversation.id"
                            class="absolute right-2 top-12 z-40 min-w-[13rem] overflow-hidden rounded-lg bg-white py-1 text-sm text-[#111b21] shadow-lg ring-1 ring-black/10"
                            @click.stop
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left hover:bg-[#f0f2f5]"
                                @click="markConversationAsUnread(conversation)"
                            >
                                <svg class="h-4 w-4 shrink-0 text-[#54656f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <path stroke-linecap="round" d="M12 8v4M12 16h.01" />
                                </svg>
                                <span>{{ t('messenger.markUnread') }}</span>
                            </button>
                        </div>
                    </li>

                    <li
                        v-if="filteredConversations.length === 0"
                        class="px-4 py-8 text-center text-sm text-[#667781]"
                    >
                        {{ t('messenger.noneFound') }}
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
                        {{ t('messenger.selectChat') }}
                    </p>
                </div>

                <template v-else>
                    <div class="flex min-h-0 flex-1 flex-col">
                    <div
                        class="flex shrink-0 items-center gap-2 border-b border-[#d1d7db] bg-[#f0f2f5] px-2.5 py-2 sm:gap-3 sm:px-4 sm:py-2.5"
                    >
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center rounded-full text-[#54656f] hover:bg-[#e9edef] lg:hidden"
                            :class="isMiniApp ? 'h-11 w-11' : 'p-1'"
                            :aria-label="t('messenger.chats')"
                            @click="backToConversationList"
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

                        <button
                            type="button"
                            class="min-w-0 flex-1 rounded-lg px-1 py-0.5 text-left transition hover:bg-[#e9edef]"
                            :title="linkedClient ? t('messenger.clientData') : t('messenger.saveContact')"
                            @click="openClientModal"
                        >
                            <p class="truncate text-[16px] font-medium leading-snug text-[#111b21] sm:text-[17px]">
                                {{ participantLabel(selectedConversation) }}
                            </p>
                            <p
                                v-if="selectedConversation.participant_username"
                                class="truncate text-[13px] text-[#667781]"
                            >
                                @{{ selectedConversation.participant_username }}
                            </p>
                        </button>

                        <span
                            class="hidden rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase sm:inline"
                            :class="channelBadgeClass(selectedConversation.channel)"
                        >
                            {{ selectedConversation.channel_label }}
                        </span>

                        <button
                            v-if="whatsAppCallPhone"
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white shadow-sm transition hover:bg-[#1ebe57] sm:h-auto sm:w-auto sm:gap-1 sm:px-3 sm:py-1.5"
                            :title="t('messenger.callWhatsApp')"
                            @click="openWhatsAppCall"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V20a1 1 0 01-1 1C10.07 21 3 13.93 3 5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.46.57 3.58a1 1 0 01-.24 1.01l-2.2 2.2z" />
                            </svg>
                            <span class="hidden text-[11px] font-semibold sm:inline sm:text-xs">{{ t('messenger.callWhatsAppShort') }}</span>
                        </button>

                        <button
                            v-if="shopConnected"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-500 px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-amber-600 sm:px-3 sm:text-xs"
:title="t('messenger.sell.btn')"
                            @click="sellModalOpen = true"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                            </svg>
<span class="hidden sm:inline">{{ t('messenger.sell.btn') }}</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-indigo-600 px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:px-3 sm:text-xs"
:title="t('messenger.task')"
                            @click="openTaskModal"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
<span class="hidden sm:inline">{{ t('messenger.task') }}</span>
                        </button>
                    </div>

                    <div
                        v-if="funnelDeal"
                        class="flex shrink-0 flex-wrap items-center gap-2 border-b border-[#d1d7db] bg-[#f7f8fa] px-2.5 py-2 sm:px-4"
                    >
                        <select
                            v-model="dealPipelineForm.pipeline_id"
                            class="min-w-0 max-w-[11rem] rounded-md border-[#d1d7db] bg-white py-1 pl-2 pr-7 text-xs font-medium shadow-sm focus:border-[#00a884] focus:ring-[#00a884]"
                            :style="pipelineNameStyle(funnelDeal.pipeline_color) || { color: '#111b21' }"
                            :disabled="dealUpdating || dealPipelineForm.processing || dealStageForm.processing"
                            :title="t('messenger.changePipeline')"
                            @change="updateDealPipeline"
                        >
                            <option
                                v-for="pipeline in filterPipelines"
                                :key="pipeline.id"
                                :value="String(pipeline.id)"
                            >
                                {{ pipeline.icon ? `${pipeline.icon} ` : '' }}{{ pipeline.name }}
                            </option>
                        </select>
                        <span class="text-xs text-[#667781]">·</span>
                        <select
                            v-model="dealStageForm.stage_id"
                            class="min-w-0 max-w-[12rem] rounded-md border-[#d1d7db] bg-white py-1 pl-2 pr-7 text-xs text-[#111b21] shadow-sm focus:border-[#00a884] focus:ring-[#00a884]"
                            :disabled="dealUpdating || dealStageForm.processing || dealPipelineForm.processing"
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
                            v-if="localMessages.length === 0"
                            class="py-12 text-center text-sm text-[#667781]"
                        >
                            {{ messagesLoading ? t('messenger.loadingMessages') : t('messenger.noMessages') }}
                        </div>

                        <template
                            v-for="item in messagesWithDateDividers"
                            :key="item.key"
                        >
                            <div
                                v-if="item.type === 'date'"
                                class="flex justify-center py-2"
                            >
                                <span class="rounded-lg bg-[#ffffffd9] px-2.5 py-0.5 text-[12px] font-medium text-[#54656f] shadow-sm sm:px-3 sm:text-[13px]">
                                    {{ item.label }}
                                </span>
                            </div>

                            <div
                                v-else
                                class="flex px-0.5"
                                :class="item.message.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                            >
                            <div
                                class="group relative max-w-[82%] cursor-pointer rounded-lg px-2.5 py-1.5 text-[15px] leading-relaxed shadow-sm sm:max-w-[min(100%,28rem)] sm:px-3 sm:py-2"
                                :class="[
                                    item.message.direction === 'outbound'
                                        ? 'rounded-tr-none bg-[#d9fdd3]'
                                        : 'rounded-tl-none bg-white',
                                    item.message.status === 'failed' ? 'ring-1 ring-red-300 opacity-80' : '',
                                    messageMenuId === item.message.id ? 'ring-1 ring-[#00a884]/60' : '',
                                ]"
                                @click.stop="openMessageMenu(item.message, $event)"
                            >
                                <div
                                    v-if="messageMenuId === item.message.id"
                                    class="absolute z-30 min-w-[12rem] overflow-hidden rounded-lg bg-white py-1 text-sm text-[#111b21] shadow-lg ring-1 ring-black/10"
                                    :class="[
                                        item.message.direction === 'outbound' ? 'right-0' : 'left-0',
                                        messageMenuPlacement === 'above' ? 'bottom-full mb-1' : 'top-full mt-1',
                                    ]"
                                    @click.stop
                                >
                                    <button
                                        v-if="canCopyMessage(item.message)"
                                        type="button"
                                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-[#f0f2f5]"
                                        @click="copyMessage(item.message)"
                                    >
                                        <svg class="h-4 w-4 shrink-0 text-[#54656f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <rect x="9" y="9" width="13" height="13" rx="2" />
                                            <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                                        </svg>
                                        <span>{{ t('messenger.copy') }}</span>
                                    </button>
                                    <button
                                        v-if="canEditMessage(item.message)"
                                        type="button"
                                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-[#f0f2f5]"
                                        @click="editMessage(item.message)"
                                    >
                                        <svg class="h-4 w-4 shrink-0 text-[#54656f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                        <span>{{ t('messenger.edit') }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-[#f0f2f5]"
                                        @click="replyToChatMessage(item.message)"
                                    >
                                        <svg class="h-4 w-4 shrink-0 text-[#54656f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4" />
                                        </svg>
                                        <span>{{ t('messenger.replyTo') }}</span>
                                    </button>
                                    <button
                                        v-if="canSaveAsQuickReply(item.message)"
                                        type="button"
                                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-[#f0f2f5]"
                                        @click="saveMessageAsQuickReply(item.message)"
                                    >
                                        <svg class="h-4 w-4 shrink-0 text-[#54656f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                        </svg>
                                        <span>{{ t('messenger.toQuickReplies') }}</span>
                                    </button>
                                </div>

                                <p
                                    v-if="item.message.body?.trim()"
                                    class="whitespace-pre-wrap break-words text-[#111b21]"
                                >
                                    <template
                                        v-for="(segment, segmentIndex) in linkifyMessageSegments(item.message.body)"
                                        :key="segmentIndex"
                                    >
                                        <a
                                            v-if="segment.type === 'link'"
                                            :href="segment.href"
                                            class="text-[#027eb5] underline underline-offset-2 hover:text-[#015f8a]"
                                            :target="segment.external === false ? undefined : '_blank'"
                                            :rel="segment.external === false ? undefined : 'noopener noreferrer'"
                                            @click.stop
                                        >{{ segment.value }}</a>
                                        <template v-else>{{ segment.value }}</template>
                                    </template>
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
{{ t('messenger.refreshHint') }}
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
{{ item.message.loading_label || t('messenger.receiptLoading') }}
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
                                            {{ t('messenger.receiptFailed') }}
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
                                    class="mt-0.5 flex items-end justify-end gap-1 text-[11px] text-[#667781] sm:mt-1 sm:text-[12px]"
                                >
                                    <span>{{ formatMessageTime(item.message.sent_at) }}</span>
                                    <span
                                        v-if="item.message.direction === 'outbound'"
                                        :class="outboundTicksClass(item.message)"
                                        :title="item.message.status === 'failed' ? t('messenger.notSent') : ''"
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
                            v-if="replyToMessage"
                            class="mb-2 flex items-start gap-2 rounded-lg border-l-4 border-[#00a884] bg-white px-3 py-2 shadow-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-[#008069]">
                                    {{ t('messenger.replyingTo') }}
                                </p>
                                <p class="mt-0.5 truncate text-sm text-[#54656f]">
                                    {{ replyToMessage.body }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-1.5 text-[#667781] hover:bg-[#f0f2f5]"
                                :title="t('messenger.cancelReply')"
                                @click="clearReplyToMessage"
                            >
                                ✕
                            </button>
                        </div>

                        <div
                            v-if="isRecording"
                            class="mb-2 flex items-center justify-between rounded-lg bg-white px-4 py-2 text-sm text-[#111b21] shadow-sm"
                        >
                            <span class="flex items-center gap-2">
                                <span class="inline-flex h-2.5 w-2.5 animate-pulse rounded-full bg-red-500" />
{{ t('messenger.recording', { time: formatRecordingTime(recordingSeconds) }) }}
                            </span>
                            <button
                                type="button"
                                class="rounded-full bg-[#00a884] px-3 py-1 text-xs font-medium text-white"
                                @click="stopRecording"
                            >
                                {{ t('messenger.send') }}
                            </button>
                        </div>

                        <div
                            v-if="imagePreviewUrl"
                            class="mb-2 flex items-center gap-3 rounded-lg bg-white px-3 py-2 shadow-sm"
                        >
                            <img
                                :src="imagePreviewUrl"
:alt="t('messenger.preview')"
                                class="h-16 w-16 rounded-md object-cover"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-[#111b21]">
{{ sendForm.image?.name || t('messenger.image') }}
                                </p>
                                <p class="text-xs text-[#667781]">
                                    {{ t('messenger.willSendAsPhoto') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-[#667781] hover:bg-[#f0f2f5]"
:title="t('messenger.remove')"
                                @click="clearImagePreview"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="relative flex items-end gap-2">
                            <input
                                ref="imageInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                class="hidden"
                                @change="onImageSelected"
                            >

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

                                <div
                                    class="flex items-end rounded-[1.5rem] bg-white shadow-sm ring-1 ring-black/5"
                                >
                                    <div class="relative shrink-0 self-end" @click.stop>
                                        <button
                                            type="button"
                                            class="flex h-11 w-11 items-center justify-center rounded-full text-[#8696a0] transition hover:text-[#54656f] disabled:opacity-40"
                                            :class="{ 'text-[#008069]': emojiPickerOpen }"
                                            :disabled="isRecording"
                                            :title="t('messenger.emoji')"
                                            @click="toggleEmojiPicker"
                                        >
                                            <svg
                                                class="h-[22px] w-[22px]"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                aria-hidden="true"
                                            >
                                                <circle cx="12" cy="12" r="9" />
                                                <path stroke-linecap="round" d="M8.5 10.5h.01M15.5 10.5h.01" />
                                                <path stroke-linecap="round" d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2" />
                                            </svg>
                                        </button>

                                        <div
                                            v-if="emojiPickerOpen"
                                            class="absolute bottom-full left-0 z-40 mb-2 w-[17.5rem] rounded-xl bg-white p-2 shadow-lg ring-1 ring-black/10 sm:w-[19rem]"
                                        >
                                            <div class="grid max-h-44 grid-cols-8 gap-0.5 overflow-y-auto">
                                                <button
                                                    v-for="emoji in composerEmojis"
                                                    :key="emoji"
                                                    type="button"
                                                    class="flex h-9 w-9 items-center justify-center rounded-lg text-xl leading-none transition hover:bg-[#f0f2f5] active:bg-[#e9edef]"
                                                    @click="insertEmoji(emoji)"
                                                >
                                                    {{ emoji }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <textarea
                                        ref="messageInput"
                                        v-model="sendForm.body"
                                        rows="1"
                                        :placeholder="t('messenger.messagePh')"
                                        class="max-h-[7.5rem] min-h-[2.75rem] w-full flex-1 resize-none overflow-y-auto border-0 bg-transparent py-2.5 pr-1 text-[15px] leading-snug text-[#111b21] placeholder:text-[#8696a0] focus:ring-0"
                                        :disabled="isRecording"
                                        @keydown="onMessageInputKeydown"
                                        @input="resizeMessageInput"
                                    />

                                    <div class="relative shrink-0 self-end" @click.stop>
                                        <button
                                            type="button"
                                            class="flex h-11 w-11 items-center justify-center rounded-full text-[#8696a0] transition hover:text-[#54656f] disabled:opacity-40"
                                            :class="{ 'text-[#008069]': composerAttachOpen }"
                                            :disabled="isRecording"
                                            :title="t('messenger.attachMenu')"
                                            @click="toggleComposerAttachMenu"
                                        >
                                            <svg
                                                class="h-[22px] w-[22px]"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M21.44 11.05l-8.49 8.49a5 5 0 01-7.07-7.07l9.19-9.19a3 3 0 014.24 4.24l-9.19 9.19a1.5 1.5 0 01-2.12-2.12l8.49-8.49"
                                                />
                                            </svg>
                                        </button>

                                        <div
                                            v-if="composerAttachOpen"
                                            class="absolute bottom-full right-0 z-40 mb-2 min-w-[13.5rem] overflow-hidden rounded-xl bg-white py-1 shadow-lg ring-1 ring-black/10"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 px-3.5 py-2.5 text-left text-[14px] text-[#111b21] transition hover:bg-[#f0f2f5]"
                                                @click="closeComposerAttachMenu(); imageInput?.click()"
                                            >
                                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#bf59cf] text-white">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                                                    </svg>
                                                </span>
                                                <span>{{ t('messenger.attachImage') }}</span>
                                            </button>
                                            <button
                                                v-if="chatGptConnected"
                                                type="button"
                                                class="flex w-full items-center gap-3 px-3.5 py-2.5 text-left text-[14px] text-[#111b21] transition hover:bg-[#f0f2f5] disabled:opacity-40"
                                                :disabled="aiImproving || !sendForm.body.trim()"
                                                @click="closeComposerAttachMenu(); improveWithAi()"
                                            >
                                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#10a37f] text-white">
                                                    <svg
                                                        v-if="!aiImproving"
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="currentColor"
                                                        aria-hidden="true"
                                                    >
                                                        <path d="M22.282 9.821a5.985 5.985 0 00-.516-4.91 6.046 6.046 0 00-6.51-2.9A6.065 6.065 0 004.981 4.18a5.985 5.985 0 00-3.998 2.9 6.046 6.046 0 00.743 7.097 5.98 5.98 0 00.51 4.911 6.051 6.051 0 006.515 2.9A5.985 5.985 0 0013.26 24a6.056 6.056 0 005.772-4.206 5.99 5.99 0 003.997-2.9 6.056 6.056 0 00-.747-7.073z" />
                                                    </svg>
                                                    <svg
                                                        v-else
                                                        class="h-4 w-4 animate-spin"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        aria-hidden="true"
                                                    >
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                                    </svg>
                                                </span>
                                                <span>{{ t('messenger.improveAi') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button
                                v-if="(sendForm.body.trim() || sendForm.image) && !slashQuickRepliesOpen"
                                type="submit"
                                class="mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#00a884] text-white transition hover:bg-[#008f6f] disabled:opacity-40"
                                :disabled="isRecording"
                                :title="t('messenger.send')"
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
                                class="relative mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full transition disabled:opacity-40"
                                :class="isRecording
                                    ? 'bg-[#00a884] text-white shadow-md'
                                    : 'bg-[#00a884] text-white hover:bg-[#008f6f]'"
                                :title="t('messenger.voice')"
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
:alt="t('messenger.image')"
                class="max-h-[92vh] max-w-[92vw] object-contain"
                @click.stop
            >
        </div>

        <Modal :show="showTaskModal" max-width="md" @close="closeTaskModal">
            <form class="p-6" @submit.prevent="submitTask">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ t('messenger.taskModal.title') }}
                </h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ t('messenger.taskModal.hint') }}
                </p>

                <div class="mt-5 space-y-4">
                    <div>
<InputLabel for="task_due_on" :value="t('common.date')" />
                        <TextInput
                            id="task_due_on"
                            v-model="taskForm.due_on"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="taskForm.errors.due_on" />
                    </div>

                    <div>
<InputLabel for="task_note" :value="t('messenger.note')" />
                        <textarea
                            id="task_note"
                            v-model="taskForm.note"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
:placeholder="t('messenger.taskModal.notePh')"
                            required
                        />
                        <InputError class="mt-2" :message="taskForm.errors.note" />
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="closeTaskModal">
                        {{ t('common.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        :disabled="taskForm.processing || !taskForm.note.trim() || !taskForm.due_on"
                    >
                        {{ t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showSaveQuickReplyModal" max-width="md" @close="closeSaveQuickReplyModal">
            <form class="p-6" @submit.prevent="submitSaveQuickReply">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ t('messenger.qrModal.title') }}
                </h3>
                <p class="mt-1 text-sm text-slate-600">
{{ t('messenger.qrModal.hint') }}
                </p>

                <div
                    v-if="saveQuickReplyPreview"
                    class="mt-4 max-h-28 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm whitespace-pre-wrap text-slate-700"
                >
                    {{ saveQuickReplyPreview }}
                </div>

                <div class="mt-4">
<InputLabel for="save_quick_reply_title" :value="t('messenger.qrModal.command')" />
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-500">/</span>
                        <TextInput
                            id="save_quick_reply_title"
                            v-model="saveQuickReplyForm.title"
                            type="text"
                            class="block w-full"
:placeholder="t('messenger.commandPh')"
                            maxlength="120"
                            autocomplete="off"
                        />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
{{ t('messenger.qrModal.availableAs') }}
                        <span class="font-mono">/{{ saveQuickReplyForm.title || t('messenger.qrModal.namePlaceholder') }}</span>
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
                        {{ t('common.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        :disabled="saveQuickReplyForm.processing || !saveQuickReplyForm.title.trim()"
                    >
                        {{ t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showFilterModal" max-width="md" @close="showFilterModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ t('messenger.filterTitle') }}
                </h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ t('messenger.filterHint') }}
                </p>

                <div class="mt-5 space-y-4">
                    <div>
<InputLabel for="filter_pipeline" :value="t('messenger.pipeline')" />
                        <select
                            id="filter_pipeline"
                            v-model="draftFilter.pipeline_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @change="onDraftPipelineChange"
                        >
<option value="">{{ t('messenger.selectPipeline') }}</option>
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
<InputLabel for="filter_stage" :value="t('messenger.stageOptional')" />
                        <select
                            id="filter_stage"
                            v-model="draftFilter.stage_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
<option value="">{{ t('messenger.allStages') }}</option>
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
                        {{ t('common.reset') }}
                    </SecondaryButton>
                    <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                        <SecondaryButton
                            type="button"
                            @click="showFilterModal = false"
                        >
                            {{ t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="button"
                            :disabled="!draftFilter.pipeline_id"
                            @click="applyFunnelFilter"
                        >
                            {{ t('common.apply') }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal
            :show="showClientModal"
            max-width="md"
            @close="showClientModal = false"
        >
            <div class="overflow-hidden">
                <div class="border-b border-[#e9edef] bg-[#f0f2f5] px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                            :class="selectedConversation ? avatarClass(selectedConversation.channel) : 'bg-[#00a884]'"
                        >
                            {{ selectedConversation ? avatarInitials(selectedConversation) : '?' }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate text-[17px] font-semibold text-[#111b21]">
                                {{ linkedClient ? t('messenger.clientData') : t('messenger.saveContact') }}
                            </h3>
                            <p
                                v-if="selectedConversation"
                                class="truncate text-[13px] text-[#667781]"
                            >
                                {{ participantLabel(selectedConversation) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4">
                    <div
                        v-if="clientFieldDefinitions.length === 0"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        {{ t('messenger.fieldsPrefix') }}
                        <Link
                            :href="route('client-fields.index')"
                            class="font-medium text-[#008069] underline"
                        >
                            {{ t('messenger.clientData') }}
                        </Link>.
                    </div>

                    <form
                        v-else
                        class="space-y-4"
                        @submit.prevent="saveClientData"
                    >
                        <div
                            v-if="messengerApiName || messengerApiContact"
                            class="rounded-xl border border-[#d1f4e4] bg-[#f0faf6] px-3.5 py-3"
                        >
                            <p class="text-[12px] font-semibold uppercase tracking-wide text-[#008069]">
                                {{ t('messenger.fromChat') }}
                            </p>
                            <p class="mt-1 truncate text-[15px] font-medium text-[#111b21]">
                                <span v-if="messengerApiName">{{ messengerApiName }}</span>
                                <span
                                    v-if="messengerApiName && messengerApiContact"
                                    class="text-[#8696a0]"
                                > · </span>
                                <span
                                    v-if="messengerApiContact"
                                    class="text-[#54656f]"
                                >{{ messengerApiContact.replace(/^@/, '') }}</span>
                            </p>
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                <button
                                    v-if="messengerApiName"
                                    type="button"
                                    class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-[13px] font-medium text-[#008069] shadow-sm ring-1 ring-[#cceee0] transition hover:bg-[#e7f8f2]"
                                    @click="applyMessengerNameToForm"
                                >
                                    {{ t('messenger.fillName') }}
                                </button>
                                <button
                                    v-if="messengerApiContact"
                                    type="button"
                                    class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-[13px] font-medium text-[#008069] shadow-sm ring-1 ring-[#cceee0] transition hover:bg-[#e7f8f2]"
                                    @click="applyMessengerContactToForm"
                                >
                                    {{ t('messenger.fillPhone') }}
                                </button>
                            </div>
                        </div>

                        <p class="text-[13px] leading-relaxed text-[#667781]">
                            {{ t('messenger.clientFormHint') }}
                        </p>

                        <div class="space-y-3.5">
                            <div
                                v-for="definition in clientFieldDefinitions"
                                :key="definition.id"
                            >
                                <label
                                    :for="`client_field_${definition.key}`"
                                    class="mb-1.5 block text-[13px] font-medium text-[#54656f]"
                                >
                                    {{ definition.label }}
                                    <span
                                        v-if="definition.is_required"
                                        class="text-red-500"
                                    >*</span>
                                </label>

                                <textarea
                                    v-if="definition.type === 'textarea'"
                                    :id="`client_field_${definition.key}`"
                                    v-model="clientForm.fields[definition.key]"
                                    rows="3"
                                    class="block w-full rounded-xl border-[#d1d7db] bg-white px-3.5 py-2.5 text-[15px] text-[#111b21] shadow-sm focus:border-[#00a884] focus:ring-[#00a884]"
                                />

                                <select
                                    v-else-if="definition.type === 'select'"
                                    :id="`client_field_${definition.key}`"
                                    v-model="clientForm.fields[definition.key]"
                                    class="block w-full rounded-xl border-[#d1d7db] bg-white px-3.5 py-2.5 text-[15px] text-[#111b21] shadow-sm focus:border-[#00a884] focus:ring-[#00a884]"
                                >
                                    <option value="">
                                        {{ t('messenger.selectEllipsis') }}
                                    </option>
                                    <option
                                        v-for="option in definition.options"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>

                                <input
                                    v-else
                                    :id="`client_field_${definition.key}`"
                                    v-model="clientForm.fields[definition.key]"
                                    class="block w-full rounded-xl border-[#d1d7db] bg-white px-3.5 py-2.5 text-[15px] text-[#111b21] shadow-sm focus:border-[#00a884] focus:ring-[#00a884]"
                                    :type="definition.type === 'number' ? 'number' : definition.type === 'email' ? 'email' : definition.type === 'date' ? 'date' : definition.type === 'phone' ? 'tel' : 'text'"
                                >

                                <InputError
                                    class="mt-1.5"
                                    :message="clientForm.errors[`fields.${definition.key}`]"
                                />
                            </div>
                        </div>

                        <InputError
                            class="mt-1"
                            :message="clientForm.errors.client || page.props.errors?.client"
                        />

                        <div class="flex gap-2 border-t border-[#e9edef] pt-4">
                            <button
                                type="button"
                                class="flex-1 rounded-xl border border-[#d1d7db] bg-white px-4 py-2.5 text-[14px] font-semibold text-[#54656f] transition hover:bg-[#f0f2f5]"
                                @click="showClientModal = false"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="flex-1 rounded-xl bg-[#00a884] px-4 py-2.5 text-[14px] font-semibold text-white transition hover:bg-[#008f6f] disabled:opacity-50"
                                :disabled="clientForm.processing"
                            >
                                {{ t('common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
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
            {{ t('messenger.webhookMeta', { url: webhookUrl }) }}
        </p>
    </AuthenticatedLayout>
</template>
