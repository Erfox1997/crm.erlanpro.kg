<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import CrmSidebarLink from '@/Components/CrmSidebarLink.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    fullHeight: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const branding = page.props.branding ?? {};
const subscription = computed(() => page.props.subscription ?? null);
const pagePermissions = computed(() => page.props.pagePermissions ?? []);
const telegramMiniApp = computed(() => Boolean(page.props.telegramMiniApp));

function canAccessPage(key) {
    const user = page.props.auth?.user;
    if (user?.company_role === 'owner' || user?.is_platform_admin) {
        return true;
    }

    const permissions = pagePermissions.value;
    if (!Array.isArray(permissions) || permissions.length === 0) {
        return false;
    }

    return permissions.includes(key);
}

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

function resetPageScroll() {
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
}

function syncDocumentScrollLock(locked) {
    document.documentElement.style.overflow = locked ? 'hidden' : '';
    document.body.style.overflow = locked ? 'hidden' : '';
}

watch(
    () => props.fullHeight,
    (locked) => {
        syncDocumentScrollLock(locked);

        if (!locked) {
            resetPageScroll();
        }
    },
    { immediate: true },
);

let removeNavigateListener = null;

onMounted(() => {
    removeNavigateListener = router.on('navigate', () => {
        resetPageScroll();
    });
});

onUnmounted(() => {
    removeNavigateListener?.();
    syncDocumentScrollLock(false);
});
</script>

<template>
    <div
        class="flex flex-col bg-[#eef2f8] md:flex-row"
        :class="fullHeight ? 'h-svh overflow-hidden' : 'min-h-svh'"
    >
        <!-- Мобильная подложка -->
        <div
            v-show="mobileDrawerOpen && !telegramMiniApp"
            class="fixed inset-0 z-40 bg-slate-900/50 md:hidden"
            aria-hidden="true"
            @click="closeMobileDrawer"
        />

        <!-- Кнопка вернуть меню (десктоп, полностью скрыто) -->
        <button
            v-if="navMode === 'hidden' && !telegramMiniApp"
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
            v-if="!telegramMiniApp"
            :class="[
                'shrink-0 flex-col overflow-hidden border-white/10 bg-gradient-to-b from-slate-950 via-slate-900 to-indigo-950 text-slate-100',
                mobileDrawerOpen
                    ? 'fixed inset-y-0 left-0 z-50 flex h-full w-[min(18rem,calc(100vw-3rem))] max-w-[18rem] translate-x-0 border-e'
                    : 'max-md:hidden',
                'md:static md:z-0 md:flex md:h-auto md:w-64 md:max-w-none md:translate-x-0 md:border-e',
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
                    v-if="canAccessPage('dashboard')"
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
                    v-if="canAccessPage('shop-sales')"
                    :href="route('shop-sales.index')"
                    title="Продажи магазина"
                    :active="!!route().current('shop-sales.index')"
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
                                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
                            />
                        </svg>
                    </template>
                    Продажи магазина
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('shop-sales')"
                    :href="route('shop-sales.report')"
                    title="Отчёт по менеджерам"
                    :active="!!route().current('shop-sales.report')"
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
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"
                            />
                        </svg>
                    </template>
                    Отчёт по менеджерам
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('messenger')"
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
                    v-if="canAccessPage('tasks')"
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
                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </template>
                    Задачи
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('comments')"
                    :href="route('comments.index')"
                    title="Комментарии"
                    :active="!!route().current('comments.*')"
                    :badge="$page.props.commentsUnread"
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
                                d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"
                            />
                        </svg>
                    </template>
                    Комментарии
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('quick-replies')"
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
                    v-if="canAccessPage('client-fields')"
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
                    v-if="canAccessPage('funnels')"
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
                    v-if="canAccessPage('broadcasts')"
                    :href="route('broadcasts.index')"
                    title="Рассылка"
                    :active="!!route().current('broadcasts.*')"
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
                                d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"
                            />
                        </svg>
                    </template>
                    Рассылка
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('integrations')"
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
                    v-if="canAccessPage('tariffs')"
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

                <CrmSidebarLink
                    v-if="canAccessPage('positions')"
                    :href="route('positions.index')"
                    title="Должности"
                    :active="!!route().current('positions.*')"
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
                                d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"
                            />
                        </svg>
                    </template>
                    Должности
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('employees')"
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
                                d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"
                            />
                        </svg>
                    </template>
                    Сотрудники
                </CrmSidebarLink>

                <CrmSidebarLink
                    v-if="canAccessPage('chat-distribution')"
                    :href="route('chat-distribution.index')"
                    title="Распределение чата"
                    :active="!!route().current('chat-distribution.*')"
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
                                d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"
                            />
                        </svg>
                    </template>
                    Распределение чата
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

        <div
            class="flex min-h-0 min-w-0 w-full flex-1 flex-col"
            :class="fullHeight ? 'overflow-hidden' : ''"
        >
            <header
                v-if="!telegramMiniApp"
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
                class="shrink-0 border-b border-slate-200/60 bg-white/50"
            >
                <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-5 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main
                :class="fullHeight
                    ? 'flex min-h-0 flex-1 flex-col overflow-hidden'
                    : 'flex-1 pb-6 sm:pb-8'"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
