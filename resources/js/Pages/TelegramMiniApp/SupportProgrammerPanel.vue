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

const appFilter = ref('pending');
const clients = ref([]);
const projects = ref([]);
const selected = reactive({});

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

const currentTitle = computed(() => tabs.find((item) => item.id === tab.value)?.label || 'Панель');

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

function syncSelected(list) {
    Object.keys(selected).forEach((key) => delete selected[key]);
    list.forEach((client) => {
        selected[client.id] = [...(client.project_ids || [])];
    });
}

async function loadApplications() {
    loading.value = true;
    try {
        const res = await api('tma.support.programmer.applications', {}, { status: appFilter.value });
        clients.value = res.clients || [];
        projects.value = res.projects || [];
        syncSelected(clients.value);
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
        await loadApplications();
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
}

watch(tab, () => {
    refreshCurrentTab();
}, { immediate: true });

watch(appFilter, () => {
    if (tab.value === 'applications') {
        loadApplications();
    }
});

function toggleProject(clientId, projectId) {
    const list = selected[clientId] || [];
    const idx = list.indexOf(projectId);
    if (idx === -1) {
        list.push(projectId);
    } else {
        list.splice(idx, 1);
    }
    selected[clientId] = list;
}

async function accept(client) {
    if (!(selected[client.id]?.length > 0)) {
        error.value = 'Выберите хотя бы один проект.';
        return;
    }
    try {
        await api('tma.support.programmer.applications.accept', client.id, {
            project_ids: selected[client.id],
        });
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось принять.';
    }
}

async function saveProjects(client) {
    if (!(selected[client.id]?.length > 0)) {
        error.value = 'Выберите хотя бы один проект.';
        return;
    }
    try {
        await api('tma.support.programmer.applications.projects', client.id, {
            project_ids: selected[client.id],
        });
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось сохранить проекты.';
    }
}

async function reject(client) {
    if (!confirm('Отклонить заявку?')) return;
    try {
        await api('tma.support.programmer.applications.reject', client.id);
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось отклонить.';
    }
}

async function block(client) {
    if (!confirm('Заблокировать клиента?')) return;
    try {
        await api('tma.support.programmer.applications.block', client.id);
        await loadApplications();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Не удалось заблокировать.';
    }
}

async function unblock(client) {
    try {
        await api('tma.support.programmer.applications.unblock', client.id);
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
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Меню
                </p>
                <h2 class="text-lg font-semibold text-white">{{ currentTitle }}</h2>
            </div>
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-white"
                aria-label="Открыть меню"
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
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm font-semibold text-white">Разделы</p>
                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-sm text-slate-400"
                    @click="menuOpen = false"
                >
                    Закрыть
                </button>
            </div>
            <nav class="space-y-2">
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
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="item in appTabs"
                    :key="item.id"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-medium"
                    :class="appFilter === item.id
                        ? 'bg-sky-500 text-slate-950'
                        : 'bg-slate-800 text-slate-300'"
                    @click="appFilter = item.id"
                >
                    {{ item.label }}
                </button>
            </div>

            <article
                v-for="client in clients"
                :key="client.id"
                class="space-y-3 rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
            >
                <div>
                    <p class="font-semibold text-white">
                        {{ client.name || 'Без имени' }}
                        <span v-if="client.username" class="text-sm font-normal text-slate-400">
                            @{{ client.username }}
                        </span>
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ client.phone || '—' }} · {{ client.company_name || 'без компании' }} · {{ client.created_at }}
                    </p>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-200">{{ client.message }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="project in projects"
                        :key="project.id"
                        class="inline-flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs"
                        :class="(selected[client.id] || []).includes(project.id)
                            ? 'border-sky-500 bg-sky-500/10 text-sky-200'
                            : 'border-slate-700 text-slate-300'"
                    >
                        <input
                            type="checkbox"
                            :checked="(selected[client.id] || []).includes(project.id)"
                            @change="toggleProject(client.id, project.id)"
                        >
                        {{ project.name }}
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="client.status === 'pending' && !client.is_blocked"
                        type="button"
                        class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-semibold text-slate-950"
                        @click="accept(client)"
                    >
                        Принять
                    </button>
                    <button
                        v-if="client.status === 'accepted' && !client.is_blocked"
                        type="button"
                        class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white"
                        @click="saveProjects(client)"
                    >
                        Сохранить проекты
                    </button>
                    <button
                        v-if="client.status === 'pending' && !client.is_blocked"
                        type="button"
                        class="rounded-lg bg-amber-500/20 px-3 py-2 text-xs font-semibold text-amber-200"
                        @click="reject(client)"
                    >
                        Отклонить
                    </button>
                    <button
                        v-if="!client.is_blocked"
                        type="button"
                        class="rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-semibold text-rose-200"
                        @click="block(client)"
                    >
                        Блок
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white"
                        @click="unblock(client)"
                    >
                        Разблок
                    </button>
                </div>
            </article>

            <p
                v-if="clients.length === 0"
                class="rounded-2xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400"
            >
                Клиентских заявок пока нет.
            </p>
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
                    Проектов пока нет.
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
                        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-200">{{ message.body }}</p>
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
                    Открытых сообщений нет.
                </p>
            </template>
        </template>
    </div>
</template>
