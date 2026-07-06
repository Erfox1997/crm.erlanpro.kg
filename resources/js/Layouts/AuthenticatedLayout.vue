<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import CrmSidebarLink from '@/Components/CrmSidebarLink.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const page = usePage();
const branding = page.props.branding ?? {};
const subscription = computed(() => page.props.subscription ?? null);

const subscriptionLine = computed(() => {
    if (!subscription.value?.tariff_name) {
        return '';
    }

    if (!subscription.value.ends_at) {
        return subscription.value.tariff_name;
    }

    if (subscription.value.is_expired) {
        return `${subscription.value.tariff_name} · истекла ${subscription.value.ends_at}`;
    }

    return `${subscription.value.tariff_name} · до ${subscription.value.ends_at}`;
});

const subscriptionTextClass = computed(() => {
    if (!subscription.value) {
        return 'text-slate-600';
    }

    if (subscription.value.is_expired) {
        return 'text-red-700';
    }

    if (subscription.value.expires_soon) {
        return 'text-amber-800';
    }

    return 'text-slate-600';
});

const NAV_MODE_KEY = 'crm-sidebar-mode';

const navMode = ref('expanded');
const mobileDrawerOpen = ref(false);

onMounted(() => {
    const stored = localStorage.getItem(NAV_MODE_KEY);
    if (['expanded', 'collapsed', 'hidden'].includes(stored)) {
        navMode.value = stored;
    }
});

watch(navMode, (v) => {
    localStorage.setItem(NAV_MODE_KEY, v);
});

const collapseLabels = computed(
    () => navMode.value === 'collapsed',
);

function toggleCollapse() {
    navMode.value = navMode.value === 'collapsed' ? 'expanded' : 'collapsed';
}

function hideSidebar() {
    navMode.value = 'hidden';
}

function showSidebar() {
    navMode.value = 'expanded';
}

function closeMobileDrawer() {
    mobileDrawerOpen.value = false;
}

function toggleMobileDrawer() {
    mobileDrawerOpen.value = !mobileDrawerOpen.value;
}

const userInitials = computed(() => {
    const name = page.props.auth.user?.name ?? '';

    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});
</script>

<template>
    <div class="min-h-screen bg-[#eef2f8] md:flex">
        <!-- Мобильная подложка -->
        <div
            v-show="mobileDrawerOpen"
            class="fixed inset-0 z-40 bg-slate-900/50 md:hidden"
            aria-hidden="true"
            @click="closeMobileDrawer"
        />

        <!-- Кнопка вернуть меню (десктоп, полностью скрыто) -->
        <button
            v-if="navMode === 'hidden'"
            type="button"
            class="fixed left-0 top-1/2 z-30 hidden -translate-y-1/2 rounded-r-lg border border-slate-700 border-l-0 bg-slate-800 px-1.5 py-8 text-slate-200 shadow-lg hover:bg-slate-700 md:block"
            title="Показать меню"
            @click="showSidebar"
        >
            <svg
                class="h-5 w-5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8.25 4.5l7.5 7.5-7.5 7.5"
                />
            </svg>
        </button>

        <aside
            :class="[
                'relative shrink-0 flex-col overflow-hidden border-white/10 bg-gradient-to-b from-slate-950 via-slate-900 to-indigo-950 text-slate-100',
                'fixed inset-y-0 left-0 z-50 flex h-full w-[min(18rem,calc(100vw-3rem))] max-w-[18rem] border-e transition-transform duration-200 ease-out',
                'md:static md:z-0 md:h-auto md:max-w-none md:translate-x-0',
                mobileDrawerOpen ? 'translate-x-0' : '-translate-x-full',
                'md:translate-x-0',
                navMode === 'hidden' ? 'md:hidden' : '',
                navMode === 'collapsed' ? 'md:w-16 md:min-w-16' : 'md:w-64',
            ]"
        >
            <div
                class="pointer-events-none absolute -right-16 top-24 h-48 w-48 rounded-full bg-indigo-500/20 blur-3xl"
            />
            <div
                class="pointer-events-none absolute -left-10 bottom-20 h-40 w-40 rounded-full bg-teal-500/10 blur-3xl"
            />

            <div
                class="relative flex items-center justify-between gap-2 border-b border-white/10 px-3 py-3"
            >
                <Link
                    :href="route('dashboard')"
                    class="flex min-w-0 items-center gap-2 font-semibold text-white"
                    @click="closeMobileDrawer"
                >
                    <BrandLogo
                        light
                        :show-domain="!collapseLabels"
                        :name="branding.name ?? 'ErlanPro'"
                        :domain="branding.domain ?? 'crm.erlanpro.kg'"
                        icon-class="h-8 w-8"
                    />
                </Link>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="hidden rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white md:inline-flex"
                        :title="
                            collapseLabels
                                ? 'Развернуть панель'
                                : 'Свернуть в иконки'
                        "
                        @click="toggleCollapse"
                    >
                        <svg
                            v-if="!collapseLabels"
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"
                            />
                        </svg>
                        <svg
                            v-else
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="hidden rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white md:inline-flex"
                        title="Скрыть меню"
                        @click="hideSidebar"
                    >
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white md:hidden"
                        aria-label="Закрыть меню"
                        @click="closeMobileDrawer"
                    >
                        <svg
                            class="h-6 w-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <nav
                class="relative flex flex-1 flex-col gap-1 overflow-y-auto px-2 py-3"
            >
                <p
                    v-if="!collapseLabels"
                    class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                >
                    Рабочая зона
                </p>

                <CrmSidebarLink
                    :href="route('dashboard')"
                    title="Дашборд"
                    :active="!!route().current('dashboard')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                            />
                        </svg>
                    </template>
                    Дашборд
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('messenger.index')"
                    title="Месенджер"
                    :active="!!route().current('messenger.index')"
                    :badge="$page.props.messengerUnread"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"
                            />
                        </svg>
                    </template>
                    Месенджер
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('messenger.quick-replies.index')"
                    title="Быстрые ответы"
                    :active="!!route().current('messenger.quick-replies.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM8.625 12h7.5M8.625 15h4.125M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </template>
                    Быстрые ответы
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('client-fields.index')"
                    title="Данные клиента"
                    :active="!!route().current('client-fields.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                            />
                        </svg>
                    </template>
                    Данные клиента
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('funnels.index')"
                    title="Воронки"
                    :active="
                        !!(
                            route().current('funnels.*') ||
                            route().current('deals.*')
                        )
                    "
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"
                            />
                        </svg>
                    </template>
                    Воронки
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('employees.index')"
                    title="Сотрудники"
                    :active="!!route().current('employees.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                            />
                        </svg>
                    </template>
                    Сотрудники
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('tasks.index')"
                    title="Задачи"
                    :active="!!route().current('tasks.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM16.5 6.108V4.875c0-.621-.504-1.125-1.125-1.125h-1.5c-.621 0-1.125.504-1.125 1.125v3.375M16.5 6.108V6.75c0 .621-.504 1.125-1.125 1.125H15M3.375 8.25h-.375A2.25 2.25 0 003 10.5v9.75c0 .621.504 1.125 1.125 1.125h1.5c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H3.375z"
                            />
                        </svg>
                    </template>
                    Задачи
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('warehouse.index')"
                    title="Склад"
                    :active="!!route().current('warehouse.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"
                            />
                        </svg>
                    </template>
                    Склад
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('integrations.index')"
                    title="Интеграции"
                    :active="!!route().current('integrations.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"
                            />
                        </svg>
                    </template>
                    Интеграции
                </CrmSidebarLink>

                <CrmSidebarLink
                    :href="route('tariffs.index')"
                    title="Тарифы"
                    :active="!!route().current('tariffs.*')"
                    :collapsed="collapseLabels"
                    @navigate="closeMobileDrawer"
                >
                    <template #icon>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m0-12h9.75m-9.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm9.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
                            />
                        </svg>
                    </template>
                    Тарифы
                </CrmSidebarLink>
            </nav>

            <div
                class="relative mt-auto border-t border-white/10 p-3"
                :class="collapseLabels ? 'md:px-2' : ''"
            >
                <div
                    v-if="page.props.company"
                    class="rounded-xl bg-white/5 px-3 py-3 backdrop-blur-sm"
                    :class="collapseLabels ? 'md:p-2 md:text-center' : ''"
                >
                    <p
                        class="truncate text-sm font-medium text-white"
                        :class="collapseLabels ? 'md:sr-only' : ''"
                    >
                        {{ page.props.company.name }}
                    </p>
                    <p
                        v-if="page.props.company?.tariff"
                        class="mt-1 text-xs text-slate-400"
                        :class="collapseLabels ? 'md:sr-only' : ''"
                    >
                        {{ page.props.company.tariff.name }}
                    </p>
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col md:min-h-0">
            <header
                class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200/70 bg-white/85 px-3 py-2.5 shadow-sm backdrop-blur-md sm:px-5"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="inline-flex shrink-0 rounded-md p-2 text-slate-600 hover:bg-slate-100 md:hidden"
                        aria-label="Открыть меню"
                        @click="toggleMobileDrawer"
                    >
                        <svg
                            class="h-6 w-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        </svg>
                    </button>

                    <div
                        v-if="subscriptionLine"
                        class="hidden min-w-0 items-center gap-2 rounded-full border px-3 py-1 text-xs sm:inline-flex"
                        :class="
                            subscription?.is_expired
                                ? 'border-red-200 bg-red-50 text-red-800'
                                : subscription?.expires_soon
                                  ? 'border-amber-200 bg-amber-50 text-amber-900'
                                  : 'border-teal-200/80 bg-teal-50/90 text-teal-900'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="
                                subscription?.is_expired
                                    ? 'bg-red-500'
                                    : subscription?.expires_soon
                                      ? 'bg-amber-500'
                                      : 'bg-teal-500'
                            "
                        />
                        <span class="truncate">{{ subscriptionLine }}</span>
                    </div>

                    <p
                        v-if="subscriptionLine"
                        class="min-w-0 truncate text-xs sm:hidden"
                        :class="subscriptionTextClass"
                    >
                        {{ subscriptionLine }}
                    </p>

                    <Link
                        v-if="subscription?.tariff_name"
                        :href="route('tariffs.index')"
                        class="shrink-0 rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition hover:bg-slate-800"
                    >
                        {{
                            subscription?.is_expired || subscription?.expires_soon
                                ? 'Продлить'
                                : 'Тарифы'
                        }}
                    </Link>
                </div>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <span class="inline-flex rounded-full">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:shadow"
                            >
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-teal-500 text-xs font-bold text-white"
                                >
                                    {{ userInitials || 'U' }}
                                </span>
                                <span class="hidden max-w-[10rem] truncate sm:inline">
                                    {{ $page.props.auth.user.name }}
                                </span>
                                <svg
                                    class="h-4 w-4 text-slate-400"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </span>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">
                            Профиль
                        </DropdownLink>
                        <DropdownLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Выйти
                        </DropdownLink>
                    </template>
                </Dropdown>
            </header>

            <div
                v-if="$page.props.flash?.success"
                class="border-b border-green-100 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ $page.props.flash.success }}
            </div>

            <header
                v-if="$slots.header"
                class="border-b border-slate-200/60 bg-white/50"
            >
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1 pb-8">
                <slot />
            </main>
        </div>
    </div>
</template>
