<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    initData: { type: String, required: true },
    initialCounts: {
        type: Object,
        default: () => ({ pending_applications: 0, open_messages: 0 }),
    },
});

const menuOpen = ref(false);
const tab = ref('inbox');
const loading = ref(false);
const error = ref('');
const notice = ref('');

const badgeCounts = ref({
    pending_applications: Number(props.initialCounts?.pending_applications) || 0,
    open_messages: Number(props.initialCounts?.open_messages) || 0,
});

const appFilter = ref(null);
const appsListOpen = ref(false);
const clients = ref([]);
const projects = ref([]);
const activeClient = ref(null);
const modalName = ref('');
const modalProjectIds = ref([]);

const projectList = ref([]);
const newProjectName = ref('');

const inboxProjects = ref([]);
const activeProject = ref(null);
const messages = ref([]);

const replyMessageId = ref(null);
const replyText = ref('');
const replyInput = ref(null);
const replySending = ref(false);

const tabs = [
    { id: 'inbox', label: 'Входящие', badgeKey: 'open_messages' },
    { id: 'projects', label: 'Проекты', badgeKey: null },
    { id: 'applications', label: 'Заявки', badgeKey: 'pending_applications' },
];

const appTabs = [
    { id: 'pending', label: 'Ожидают' },
    { id: 'accepted', label: 'Принятые' },
    { id: 'rejected', label: 'Отклонённые' },
    { id: 'blocked', label: 'Блок' },
    { id: 'all', label: 'Все' },
];

const menuBadgeTotal = computed(() => (
    (badgeCounts.value.pending_applications || 0) + (badgeCounts.value.open_messages || 0)
));

const currentTitle = computed(() => {
    if (tab.value === 'applications' && appsListOpen.value) {
        return appTabs.find((item) => item.id === appFilter.value)?.label || 'Заявки';
    }
    if (tab.value === 'inbox' && activeProject.value) {
        return activeProject.value.name || 'Входящие';
    }

    return tabs.find((item) => item.id === tab.value)?.label || 'Панель';
});

function tabBadge(item) {
    if (!item.badgeKey) return 0;
    return Number(badgeCounts.value[item.badgeKey]) || 0;
}

function applyCounts(counts) {
    if (!counts) return;
    badgeCounts.value = {
        pending_applications: Number(counts.pending_applications) || 0,
        open_messages: Number(counts.open_messages) || 0,
    };
}

function jsonHeaders() {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function api(routeName, params = {}, data = {}, method = 'post') {
    error.value = '';
    notice.value = '';
    const payload = { init_data: props.initData, ...data };
    const { data: res } = await window.axios({
        method,
        url: route(routeName, params),
        data: method === 'delete' ? undefined : payload,
        params: method === 'delete' ? payload : undefined,
        headers: jsonHeaders(),
    });
    if (res.message) {
        notice.value = res.message;
    }
    if (res.counts) {
        applyCounts(res.counts);
    }
    return res;
}

async function loadCounts() {
    try {
        const res = await api('tma.support.programmer.counts');
        applyCounts(res.counts);
    } catch {
        // badges are non-critical
    }
}

async function loadApplications() {
    if (!appFilter.value) {
        clients.value = [];
        return;
    }

    loading.value = true;
    try {
        const res = await api('tma.support.programmer.applications', {}, { status: appFilter.value });
        clients.value = res.clients || [];
        projects.value = res.projects || [];
        if (activeClient.value) {
            const fresh = clients.value.find((c) => c.id === activeClient.value.id);
            if (fresh) {
                openClient(fresh);
            } else {
                closeClient();
            }
        }
        await loadCounts();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось загрузить заявки.';
    } finally {
        loading.value = false;
    }
}

async function loadProjects() {
    loading.value = true;
    try {
        const res = await api('tma.support.programmer.projects');
        projectList.value = res.projects || [];
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось загрузить проекты.';
    } finally {
        loading.value = false;
    }
}

async function loadInbox() {
    loading.value = true;
    activeProject.value = null;
    messages.value = [];
    cancelReply();
    try {
        const res = await api('tma.support.programmer.inbox');
        inboxProjects.value = res.projects || [];
        applyCounts(res.counts);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось загрузить входящие.';
    } finally {
        loading.value = false;
    }
}

async function openInboxProject(project) {
    loading.value = true;
    cancelReply();
    try {
        const res = await api('tma.support.programmer.inbox.show', project.id);
        activeProject.value = res.project;
        messages.value = res.messages || [];
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось загрузить сообщения.';
    } finally {
        loading.value = false;
    }
}

async function refreshCurrentTab() {
    if (tab.value === 'applications') {
        if (appsListOpen.value && appFilter.value) {
            await loadApplications();
        } else {
            clients.value = [];
            loading.value = false;
            await loadCounts();
        }
    } else if (tab.value === 'projects') {
        await loadProjects();
    } else if (activeProject.value) {
        await openInboxProject(activeProject.value);
    } else {
        await loadInbox();
    }
}

function selectTab(id) {
    tab.value = id;
    menuOpen.value = false;
    activeProject.value = null;
    appsListOpen.value = false;
    appFilter.value = null;
    cancelReply();
    closeClient();
}

async function openAppsFilter(id) {
    appFilter.value = id;
    appsListOpen.value = true;
    closeClient();
    await loadApplications();
}

function backToAppFilters() {
    appsListOpen.value = false;
    appFilter.value = null;
    clients.value = [];
    closeClient();
}

function openMenu() {
    menuOpen.value = true;
    loadCounts();
}

watch(tab, () => {
    refreshCurrentTab();
}, { immediate: true });

onMounted(() => {
    loadCounts();
});

function openClient(client) {
    activeClient.value = client;
    modalName.value = client.name || '';
    modalProjectIds.value = [...(client.project_ids || [])];
}

function closeClient() {
    activeClient.value = null;
    modalName.value = '';
    modalProjectIds.value = [];
}

function toggleModalProject(projectId) {
    const list = [...modalProjectIds.value];
    const idx = list.indexOf(projectId);
    if (idx === -1) {
        list.push(projectId);
    } else {
        list.splice(idx, 1);
    }
    modalProjectIds.value = list;
}

async function accept() {
    const client = activeClient.value;
    if (!client) return;
    if (!(modalProjectIds.value.length > 0)) {
        error.value = 'Выберите проект.';
        return;
    }
    try {
        await api('tma.support.programmer.applications.accept', { supportClient: client.id }, {
            name: modalName.value,
            project_ids: modalProjectIds.value,
        });
        closeClient();
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось принять.';
    }
}

async function saveClient() {
    const client = activeClient.value;
    if (!client) return;
    try {
        await api('tma.support.programmer.applications.projects', { supportClient: client.id }, {
            name: modalName.value,
            project_ids: modalProjectIds.value,
        });
        closeClient();
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось сохранить.';
    }
}

async function reject() {
    const client = activeClient.value;
    if (!client) return;
    if (!confirm('Отклонить?')) return;
    try {
        await api('tma.support.programmer.applications.reject', { supportClient: client.id });
        closeClient();
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось отклонить.';
    }
}

async function block() {
    const client = activeClient.value;
    if (!client) return;
    if (!confirm('Заблокировать?')) return;
    try {
        await api('tma.support.programmer.applications.block', { supportClient: client.id });
        closeClient();
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось заблокировать.';
    }
}

async function unblock() {
    const client = activeClient.value;
    if (!client) return;
    try {
        await api('tma.support.programmer.applications.unblock', { supportClient: client.id });
        closeClient();
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось разблокировать.';
    }
}

async function createProject() {
    const name = newProjectName.value.trim();
    if (!name) return;
    try {
        await api('tma.support.programmer.projects.store', {}, { name });
        newProjectName.value = '';
        await loadProjects();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось создать проект.';
    }
}

async function renameProject(project) {
    const name = prompt('Новое название проекта', project.name);
    if (!name || !name.trim()) return;
    try {
        await api('tma.support.programmer.projects.update', project.id, { name: name.trim() });
        await loadProjects();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось обновить проект.';
    }
}

async function removeProject(project) {
    if (!confirm(`Удалить проект «${project.name}»?`)) return;
    try {
        await window.axios.delete(route('tma.support.programmer.projects.destroy', project.id), {
            data: { init_data: props.initData },
            headers: jsonHeaders(),
        });
        notice.value = 'Проект удалён.';
        await loadProjects();
        await loadCounts();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось удалить проект.';
    }
}

function cancelReply() {
    replyMessageId.value = null;
    replyText.value = '';
    replySending.value = false;
}

async function focusReplyInput() {
    await nextTick();
    const el = replyInput.value;
    if (!el) return;
    el.focus();
    // Telegram Desktop WebView often needs a delayed re-focus
    setTimeout(() => {
        replyInput.value?.focus();
    }, 80);
}

function startReply(message) {
    if (replyMessageId.value === message.id) {
        cancelReply();
        return;
    }
    replyMessageId.value = message.id;
    replyText.value = '';
    focusReplyInput();
}

async function complete(message) {
    if (!confirm('Отметить выполненным и уведомить клиента?')) return;
    if (replyMessageId.value === message.id) {
        cancelReply();
    }
    try {
        await api('tma.support.programmer.messages.complete', message.id);
        await openInboxProject(activeProject.value);
        await loadCounts();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось отметить выполненным.';
    }
}

async function sendReply() {
    const messageId = replyMessageId.value;
    const body = replyText.value.trim();
    if (!messageId || !body || replySending.value) return;

    replySending.value = true;
    try {
        await api('tma.support.programmer.messages.reply', messageId, { body });
        cancelReply();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось отправить ответ.';
        replySending.value = false;
        focusReplyInput();
    }
}

async function removeMessage(message) {
    if (!confirm('Удалить сообщение без уведомления клиента?')) return;
    if (replyMessageId.value === message.id) {
        cancelReply();
    }
    try {
        await window.axios.delete(route('tma.support.programmer.messages.destroy', message.id), {
            data: { init_data: props.initData },
            headers: jsonHeaders(),
        });
        notice.value = 'Сообщение удалено.';
        await openInboxProject(activeProject.value);
        await loadCounts();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось удалить сообщение.';
    }
}
</script>

<template>
    <div
        class="relative space-y-4"
        :class="replyMessageId ? 'pb-40' : ''"
    >
        <header class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-white">{{ currentTitle }}</h2>
            <button
                type="button"
                class="relative inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-white"
                aria-label="Меню"
                @click="menuOpen ? (menuOpen = false) : openMenu()"
            >
                <svg
                    v-if="!menuOpen"
                    class="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                </svg>
                <svg
                    v-else
                    class="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span
                    v-if="!menuOpen && menuBadgeTotal > 0"
                    class="absolute -right-1.5 -top-1.5 min-w-[1.25rem] rounded-full bg-amber-400 px-1 py-0.5 text-center text-[10px] font-bold leading-none text-slate-950"
                >
                    +{{ menuBadgeTotal }}
                </span>
            </button>
        </header>

        <div
            v-if="menuOpen"
            class="fixed inset-0 z-40 bg-black/55"
            @click="menuOpen = false"
        />

        <aside
            class="fixed inset-y-0 right-0 z-50 flex w-[min(100%,20rem)] flex-col border-l border-slate-800 bg-slate-950 p-4 shadow-2xl transition-transform duration-200"
            :class="menuOpen
                ? 'translate-x-0 pointer-events-auto'
                : 'translate-x-full pointer-events-none'"
            :aria-hidden="!menuOpen"
        >
            <nav class="mt-2 space-y-2">
                <button
                    v-for="item in tabs"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-left text-sm font-semibold"
                    :class="tab === item.id
                        ? 'bg-sky-500 text-slate-950'
                        : 'bg-slate-900 text-slate-200'"
                    @click="selectTab(item.id)"
                >
                    <span>{{ item.label }}</span>
                    <span
                        v-if="tabBadge(item) > 0"
                        class="rounded-full px-2 py-0.5 text-xs font-bold"
                        :class="tab === item.id
                            ? 'bg-slate-950/20 text-slate-950'
                            : 'bg-amber-400 text-slate-950'"
                    >
                        +{{ tabBadge(item) }}
                    </span>
                </button>
            </nav>
        </aside>

        <p v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
            {{ error }}
        </p>
        <p v-if="notice" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
            {{ notice }}
        </p>
        <p v-if="loading" class="text-center text-sm text-slate-400">Загрузка…</p>

        <template v-if="tab === 'applications' && !loading">
            <template v-if="!appsListOpen">
                <div class="space-y-2">
                    <button
                        v-for="item in appTabs"
                        :key="item.id"
                        type="button"
                        class="flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-left text-sm font-semibold text-slate-200"
                        @click="openAppsFilter(item.id)"
                    >
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.id === 'pending' && badgeCounts.pending_applications > 0"
                            class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-slate-950"
                        >
                            +{{ badgeCounts.pending_applications }}
                        </span>
                    </button>
                </div>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="text-sm text-sky-400"
                    @click="backToAppFilters"
                >
                    ← Назад
                </button>

                <div class="space-y-2">
                    <button
                        v-for="client in clients"
                        :key="client.id"
                        type="button"
                        class="flex w-full items-center rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-3.5 text-left text-base font-semibold text-white"
                        @click="openClient(client)"
                    >
                        {{ client.name || 'Без имени' }}
                    </button>
                </div>

                <p
                    v-if="clients.length === 0"
                    class="rounded-2xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400"
                >
                    Пусто.
                </p>
            </template>

            <div
                v-if="activeClient"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-black/60 p-4 sm:items-center"
                @click.self="closeClient"
            >
                <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-700 bg-slate-950 p-4 shadow-2xl">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-white">Клиент</h3>
                        <button
                            type="button"
                            class="text-sm text-slate-400"
                            @click="closeClient"
                        >
                            ✕
                        </button>
                    </div>

                    <label class="mb-1 block text-xs text-slate-400">Имя</label>
                    <input
                        v-model="modalName"
                        type="text"
                        maxlength="120"
                        class="mb-4 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                    >

                    <p class="mb-2 text-xs text-slate-400">Проекты</p>
                    <div class="mb-4 space-y-2">
                        <button
                            v-for="project in projects"
                            :key="project.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm font-semibold"
                            :class="modalProjectIds.includes(project.id)
                                ? 'border-sky-500 bg-sky-500/15 text-sky-100'
                                : 'border-slate-700 bg-slate-900 text-slate-200'"
                            @click="toggleModalProject(project.id)"
                        >
                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded border text-xs"
                                :class="modalProjectIds.includes(project.id)
                                    ? 'border-sky-400 bg-sky-500 text-slate-950'
                                    : 'border-slate-500'"
                            >
                                {{ modalProjectIds.includes(project.id) ? '✓' : '' }}
                            </span>
                            {{ project.name }}
                        </button>
                        <p
                            v-if="projects.length === 0"
                            class="text-sm text-slate-500"
                        >
                            Сначала создайте проекты.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <button
                            v-if="activeClient.status === 'pending' && !activeClient.is_blocked"
                            type="button"
                            class="w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950"
                            @click="accept"
                        >
                            Принять
                        </button>
                        <button
                            v-if="!activeClient.is_blocked"
                            type="button"
                            class="w-full rounded-xl bg-sky-500 px-4 py-3 text-sm font-semibold text-slate-950"
                            @click="saveClient"
                        >
                            Сохранить
                        </button>
                        <button
                            v-if="activeClient.status === 'pending' && !activeClient.is_blocked"
                            type="button"
                            class="w-full rounded-xl bg-amber-500/20 px-4 py-3 text-sm font-semibold text-amber-200"
                            @click="reject"
                        >
                            Отклонить
                        </button>
                        <button
                            v-if="!activeClient.is_blocked"
                            type="button"
                            class="w-full rounded-xl bg-rose-500/20 px-4 py-3 text-sm font-semibold text-rose-200"
                            @click="block"
                        >
                            Блок
                        </button>
                        <button
                            v-else
                            type="button"
                            class="w-full rounded-xl bg-slate-700 px-4 py-3 text-sm font-semibold text-white"
                            @click="unblock"
                        >
                            Разблок
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <template v-else-if="tab === 'projects' && !loading">
            <form class="flex gap-2" @submit.prevent="createProject">
                <input
                    v-model="newProjectName"
                    type="text"
                    maxlength="160"
                    placeholder="Новый проект"
                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                >
                <button
                    type="submit"
                    class="rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-semibold text-slate-950"
                >
                    +
                </button>
            </form>

            <article
                v-for="project in projectList"
                :key="project.id"
                class="flex items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
            >
                <div>
                    <p class="font-semibold text-white">{{ project.name }}</p>
                    <p class="text-xs text-slate-400">
                        клиентов: {{ project.clients_count }} · открытых: {{ project.open_messages_count }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white"
                        @click="renameProject(project)"
                    >
                        Изм.
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-semibold text-rose-200"
                        @click="removeProject(project)"
                    >
                        Удал.
                    </button>
                </div>
            </article>
        </template>

        <template v-else-if="tab === 'inbox' && !loading">
            <template v-if="!activeProject">
                <button
                    v-for="project in inboxProjects"
                    :key="project.id"
                    type="button"
                    class="flex w-full items-center justify-between rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-left"
                    @click="openInboxProject(project)"
                >
                    <span class="font-semibold text-white">{{ project.name }}</span>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="project.open_messages_count > 0
                            ? 'bg-amber-400 text-slate-950'
                            : 'bg-slate-700 text-slate-300'"
                    >
                        {{ project.open_messages_count }}
                    </span>
                </button>
                <p
                    v-if="inboxProjects.length === 0"
                    class="rounded-2xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400"
                >
                    Пусто.
                </p>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="text-sm text-sky-400"
                    @click="activeProject = null; loadInbox()"
                >
                    ← Назад к проектам
                </button>
                <h3 class="text-lg font-semibold text-white">{{ activeProject.name }}</h3>

                <article
                    v-for="message in messages"
                    :key="message.id"
                    class="space-y-3 rounded-2xl border p-4"
                    :class="replyMessageId === message.id
                        ? 'border-sky-500 bg-sky-500/10'
                        : 'border-slate-800 bg-slate-900/80'"
                >
                    <div>
                        <p class="font-semibold text-white">
                            {{ message.client?.name || 'Клиент' }}
                            <span
                                v-if="message.client?.username"
                                class="text-sm font-normal text-slate-400"
                            >
                                @{{ message.client.username }}
                            </span>
                        </p>
                        <p class="text-xs text-slate-400">{{ message.created_at }}</p>
                        <p
                            v-if="message.body && message.media_type !== 'voice' && message.media_type !== 'photo'"
                            class="mt-2 whitespace-pre-wrap text-sm text-slate-200"
                        >
                            {{ message.body }}
                        </p>
                        <p
                            v-else-if="message.body && message.body !== '[Фото]' && message.body !== '[Голосовое]'"
                            class="mt-2 whitespace-pre-wrap text-sm text-slate-200"
                        >
                            {{ message.body }}
                        </p>

                        <audio
                            v-if="message.media_type === 'voice' && message.media_url"
                            class="mt-3 w-full"
                            controls
                            preload="metadata"
                            :src="message.media_url"
                        />
                        <img
                            v-else-if="message.media_type === 'photo' && message.media_url"
                            class="mt-3 max-h-72 w-full rounded-xl object-contain"
                            :src="message.media_url"
                            alt=""
                        >
                        <p
                            v-else-if="message.media_type === 'voice' || message.media_type === 'photo'"
                            class="mt-2 text-sm text-slate-400"
                        >
                            Медиа недоступно
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-semibold text-slate-950"
                            @click="complete(message)"
                        >
                            ✅ Готово
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-xs font-semibold"
                            :class="replyMessageId === message.id
                                ? 'bg-sky-500 text-slate-950'
                                : 'bg-slate-700 text-white'"
                            @click="startReply(message)"
                        >
                            💬 {{ replyMessageId === message.id ? 'Отмена' : 'Ответ' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-semibold text-rose-200"
                            @click="removeMessage(message)"
                        >
                            Удалить
                        </button>
                    </div>
                </article>

                <p
                    v-if="messages.length === 0"
                    class="rounded-2xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400"
                >
                    Пусто.
                </p>
            </template>
        </template>

        <form
            v-if="replyMessageId"
            class="fixed inset-x-0 bottom-0 z-[70] border-t border-slate-700 bg-slate-950/95 p-3 backdrop-blur"
            @submit.prevent="sendReply"
        >
            <div class="mx-auto max-w-md space-y-2">
                <textarea
                    ref="replyInput"
                    v-model="replyText"
                    rows="3"
                    inputmode="text"
                    enterkeyhint="send"
                    autocomplete="off"
                    class="w-full resize-none rounded-xl border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                    placeholder="Ответ клиенту…"
                    @pointerdown.stop
                />
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200"
                        @click="cancelReply"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        class="flex-1 rounded-xl bg-sky-500 px-3 py-2.5 text-sm font-semibold text-slate-950 disabled:opacity-50"
                        :disabled="replySending || !replyText.trim()"
                    >
                        {{ replySending ? 'Отправка…' : 'Отправить' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
