<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    appName: {
        type: String,
        default: 'CRM',
    },
});
</script>

<template>
    <Head title="Главная" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(99,102,241,0.25),transparent)]"
        />
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_60%_40%_at_100%_0%,rgba(6,182,212,0.12),transparent)]"
        />

        <div class="relative mx-auto max-w-6xl px-4 pb-20 pt-8 sm:px-6 lg:px-8">
            <header
                class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link href="/" class="inline-flex items-center gap-3">
                    <ApplicationLogo
                        class="h-10 w-10 shrink-0 fill-current text-white"
                    />
                    <span class="text-lg font-semibold tracking-tight text-white"
                        >crm.ulan.kg</span
                    >
                </Link>

                <nav
                    v-if="canLogin"
                    class="flex flex-wrap items-center gap-3 sm:justify-end"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-100"
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
                        class="inline-flex rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-medium text-indigo-200"
                    >
                        Облачная CRM для бизнеса
                    </p>
                    <h1
                        class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl sm:leading-tight"
                    >
                        Воронки, клиенты и сделки без лишней суеты
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-400"
                    >
                        Заведите компанию за минуту, ведите сделки по этапам и
                        держите контакты под рукой. Один аккаунт — одна
                        команда, изоляция данных по компаниям.
                    </p>

                    <div
                        v-if="canLogin && !$page.props.auth.user"
                        class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row"
                    >
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex w-full justify-center rounded-xl bg-indigo-500 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-400 sm:w-auto"
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
                    class="mx-auto mt-20 grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3"
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
                            Колонки по этапам, перетаскивание карточек, понятный
                            статус каждой сделки.
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
                            Клиенты и контакты
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Единая база с телефоном и email, быстрый поиск и
                            привязка к сделкам.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm sm:col-span-2 lg:col-span-1"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-300"
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
                                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"
                                />
                            </svg>
                        </div>
                        <h2 class="mt-4 text-lg font-semibold text-white">
                            Дашборд и цифры
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Сводка по сделкам, выручке с успешных этапов и
                            количеству клиентов.
                        </p>
                    </div>
                </div>
            </main>

            <footer class="mt-24 border-t border-white/10 pt-10 text-center">
                <p class="text-sm text-slate-500">
                    {{ appName }} · безопасное хранение данных по компаниям
                </p>
            </footer>
        </div>
    </div>
</template>
