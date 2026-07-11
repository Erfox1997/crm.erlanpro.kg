<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    appName: {
        type: String,
        default: 'CRM ErlanPro',
    },
});

const page = usePage();
const branding = page.props.branding ?? {};
</script>

<template>
    <Head title="CRM ErlanPro — crm.erlanpro.kg" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.22),transparent)]"
        />
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_60%_40%_at_100%_0%,rgba(99,102,241,0.12),transparent)]"
        />

        <div class="relative mx-auto max-w-6xl px-4 pb-20 pt-8 sm:px-6 lg:px-8">
            <header
                class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link href="/">
                    <BrandLogo
                        light
                        :name="branding.name ?? 'ErlanPro'"
                        :domain="branding.domain ?? 'crm.erlanpro.kg'"
                    />
                </Link>

                <nav
                    v-if="canLogin"
                    class="flex flex-wrap items-center gap-3 sm:justify-end"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-400"
                    >
                        Перейти в CRM
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:text-white"
                        >
                            Вход
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400"
                        >
                            Создать аккаунт
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="mt-16 sm:mt-24">
                <div class="mx-auto max-w-3xl text-center">
                    <p
                        class="inline-flex rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-medium text-emerald-200"
                    >
                        Запуск за несколько минут · бесплатная регистрация
                    </p>
                    <h1
                        class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl sm:leading-tight"
                    >
                        Управляйте клиентами и сделками в одном месте
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-400"
                    >
                        Воронки продаж, карточки сделок, база клиентов и
                        командная работа — для компаний любого масштаба.
                    </p>

                    <div
                        v-if="canLogin && !$page.props.auth.user"
                        class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row"
                    >
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex w-full justify-center rounded-xl bg-emerald-500 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-500/30 transition hover:bg-emerald-400 sm:w-auto"
                        >
                            Начать бесплатно
                        </Link>
                        <Link
                            :href="route('login')"
                            class="inline-flex w-full justify-center rounded-xl border border-white/15 bg-white/5 px-8 py-3.5 text-base font-semibold text-white backdrop-blur transition hover:bg-white/10 sm:w-auto"
                        >
                            Уже есть аккаунт
                        </Link>
                    </div>
                </div>

                <div
                    class="mx-auto mt-20 grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-300"
                        >
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
                                    d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"
                                />
                            </svg>
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-white">
                            Воронка продаж
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Этапы сделок, drag-and-drop, понятный статус.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-500/20 text-cyan-300"
                        >
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
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-white">
                            Клиенты
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            База контактов с телефоном и email.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-pink-500/20 text-pink-300"
                        >
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
                                    d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"
                                />
                            </svg>
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-white">
                            Instagram
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Входящие Direct-сообщения в одном окне.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/20 text-blue-300"
                        >
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
                                    d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"
                                />
                            </svg>
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-white">
                            Facebook
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Диалоги Messenger без переключения вкладок.
                        </p>
                    </div>
                </div>
            </main>

            <footer class="mt-16 text-center">
                <p class="text-sm font-medium text-slate-300">
                    ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Сервис CRM ErlanPro · {{ branding.domain ?? 'crm.erlanpro.kg' }}
                </p>
            </footer>

            <PublicSiteFooter variant="dark" />
        </div>
    </div>
</template>
