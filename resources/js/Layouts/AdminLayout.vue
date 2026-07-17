<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import CrmSidebarLink from '@/Components/CrmSidebarLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const branding = page.props.branding ?? {};
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="min-h-screen bg-slate-100 lg:flex">
        <aside
            class="flex w-full shrink-0 flex-col border-e border-slate-800 bg-gradient-to-b from-slate-950 via-indigo-950 to-slate-950 text-slate-100 lg:fixed lg:inset-y-0 lg:w-72"
        >
            <div class="border-b border-white/10 px-5 py-5">
                <Link :href="route('admin.dashboard')" class="inline-flex">
                    <BrandLogo
                        light
                        :name="branding.name ?? 'ErlanPro'"
                        :domain="branding.domain ?? 'crm.erlanpro.kg'"
                        icon-class="h-10 w-10"
                    />
                </Link>
                <p class="mt-3 text-xs text-slate-400">Панель управления</p>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div>
                    <p
                        class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                    >
                        Основное
                    </p>
                    <CrmSidebarLink
                        :href="route('admin.dashboard')"
                        :active="route().current('admin.dashboard')"
                    >
                        <template #icon>
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                />
                            </svg>
                        </template>
                        Главное
                    </CrmSidebarLink>
                </div>

                <div>
                    <p
                        class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                    >
                        Управление
                    </p>
                    <div class="space-y-1">
                        <CrmSidebarLink
                            :href="route('admin.tariffs.index')"
                            :active="route().current('admin.tariffs.*')"
                        >
                            <template #icon>
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"
                                    />
                                </svg>
                            </template>
                            Тарифы (Планы)
                        </CrmSidebarLink>

                        <CrmSidebarLink
                            :href="route('admin.companies.index')"
                            :active="route().current('admin.companies.*')"
                        >
                            <template #icon>
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                                    />
                                </svg>
                            </template>
                            Список клиентов
                        </CrmSidebarLink>

                        <CrmSidebarLink
                            :href="route('admin.payment-requisites.edit')"
                            :active="route().current('admin.payment-requisites.*')"
                        >
                            <template #icon>
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"
                                    />
                                </svg>
                            </template>
                            Реквизиты для клиентов
                        </CrmSidebarLink>

                        <CrmSidebarLink
                            :href="route('admin.legal.edit')"
                            :active="route().current('admin.legal.*')"
                        >
                            <template #icon>
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                    />
                                </svg>
                            </template>
                            Реквизиты ИП
                        </CrmSidebarLink>

                        <CrmSidebarLink
                            :href="route('admin.rule-updates.index')"
                            :active="route().current('admin.rule-updates.*')"
                        >
                            <template #icon>
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h.008v.008H15.75V7.5zm0 3h.008v.008H15.75V10.5zM3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"
                                    />
                                </svg>
                            </template>
                            Обновления правил
                        </CrmSidebarLink>
                    </div>
                </div>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div
                    class="rounded-xl bg-white/5 px-4 py-3 text-sm text-slate-200"
                >
                    <p class="font-medium">{{ user?.name }}</p>
                    <p class="text-xs text-slate-400">Программист</p>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="mt-2 text-xs text-indigo-300 hover:text-indigo-200"
                    >
                        Выйти
                    </Link>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1 lg:pl-72">
            <header
                v-if="$slots.header"
                class="border-b border-slate-200 bg-white px-4 py-6 sm:px-8"
            >
                <slot name="header" />
            </header>

            <main class="px-4 py-8 sm:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
