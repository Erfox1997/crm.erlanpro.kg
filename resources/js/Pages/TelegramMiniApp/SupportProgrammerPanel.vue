<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    initData: { type: String, required: true },
});

const menuOpen = ref(false);
const tab = ref('applications');
const loading = ref(false);
const error = ref('');
const notice = ref('');

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
const replyOpen = reactive({});
const replyBody = reactive({});

const tabs = [
    { id: 'applications', label: 'Заявки' },
    { id: 'projects', label: 'Проекты' },
    { id: 'inbox', label: 'Входящие' },
];

const appTabs = [
    { id: 'pending', label: 'Ожидают' },
    { id: 'accepted', label: 'Принятые' },
    { id: 'rejected', label: 'Отклонённые' },
    { id: 'blocked', label: 'Блок' },
    { id: 'all', label: 'Все' },
];

const currentTitle = computed(() => {
    if (tab.value === 'applications' && appsListOpen.value) {
        return appTabs.find((item) => item.id === appFilter.value)?.label || 'Заявки';
    }
    if (tab.value === 'inbox' && activeProject.value) {
        return activeProject.value.name || 'Входящие';
    }

    return tabs.find((item) => item.id === tab.value)?.label || 'Панель';
});

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
    return res;
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
    try {
        const res = await api('tma.support.programmer.inbox');
        inboxProjects.value = res.projects || [];
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось загрузить входящие.';
    } finally {
        loading.value = false;
    }
}

async function openInboxProject(project) {
    loading.value = true;
    try {
        const res = await api('tma.support.programmer.inbox.show', project.id);
        activeProject.value = res.project;
        messages.value = res.messages || [];
        messages.value.forEach((m) => {
            replyBody[m.id] = replyBody[m.id] || '';
        });
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

watch(tab, () => {
    refreshCurrentTab();
}, { immediate: true });

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
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось удалить проект.';
    }
}

async function complete(message) {
    if (!confirm('Отметить выполненным и уведомить клиента?')) return;
    try {
        await api('tma.support.programmer.messages.complete', message.id);
        await openInboxProject(activeProject.value);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось отметить выполненным.';
    }
}

async function sendReply(message) {
    const body = (replyBody[message.id] || '').trim();
    if (!body) return;
    try {
        await api('tma.support.programmer.messages.reply', message.id, { body });
        replyOpen[message.id] = false;
        replyBody[message.id] = '';
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось отправить ответ.';
    }
}

async function removeMessage(message) {
    if (!confirm('Удалить сообщение без уведомления клиента?')) return;
    try {
        await window.axios.delete(route('tma.support.programmer.messages.destroy', message.id), {
            data: { init_data: props.initData },
            headers: jsonHeaders(),
        });
        notice.value = 'Сообщение удалено.';
        await openInboxProject(activeProject.value);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось удалить сообщение.';
    }
}
</script>

<template>
    <div class="relative space-y-4">
        <header class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-white">{{ currentTitle }}</h2>
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-white"
                aria-label="Меню"
                @click="menuOpen = !menuOpen"
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
            </button>
        </header>

        <div
            v-if="menuOpen"
            class="fixed inset-0 z-40 bg-black/55"
            @click="menuOpen = false"
        />

        <aside
            class="fixed inset-y-0 right-0 z-50 flex w-[min(100%,20rem)] flex-col border-l border-slate-800 bg-slate-950 p-4 shadow-2xl transition-transform duration-200"
            :class="menuOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <nav class="mt-2 space-y-2">
                <button
                    v-for="item in tabs"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-center rounded-xl px-4 py-3 text-left text-sm font-semibold"
                    :class="tab === item.id
                        ? 'bg-sky-500 text-slate-950'
                        : 'bg-slate-900 text-slate-200'"
                    @click="selectTab(item.id)"
                >
                    {{ item.label }}
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
                        class="flex w-full items-center rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-left text-sm font-semibold text-slate-200"
                        @click="openAppsFilter(item.id)"
                    >
                        {{ item.label }}
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
                    class="space-y-3 rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
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
                            class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white"
                            @click="replyOpen[message.id] = !replyOpen[message.id]"
                        >
                            💬 Ответ
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-semibold text-rose-200"
                            @click="removeMessage(message)"
                        >
                            Удалить
                        </button>
                    </div>

                    <form
                        v-if="replyOpen[message.id]"
                        class="space-y-2 border-t border-slate-800 pt-3"
                        @submit.prevent="sendReply(message)"
                    >
                        <textarea
                            v-model="replyBody[message.id]"
                            rows="3"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-sky-500"
                            placeholder="Ответ клиенту…"
                        />
                        <button
                            type="submit"
                            class="w-full rounded-xl bg-sky-500 px-3 py-2.5 text-sm font-semibold text-slate-950"
                        >
                            Отправить
                        </button>
                    </form>
                </article>

                <p
                    v-if="messages.length === 0"
                    class="rounded-2xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400"
                >
                    Пусто.
                </p>
            </template>
        </template>
    </div>
</template>
