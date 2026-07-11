<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    variant: {
        type: String,
        default: 'light',
    },
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const branding = page.props.branding ?? {};

const isDark = (variant) => variant === 'dark';
</script>

<template>
    <header
        :class="
            isDark(variant)
                ? 'flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between'
                : 'border-b border-slate-200 bg-white'
        "
    >
        <div
            :class="
                isDark(variant)
                    ? 'flex w-full items-center justify-between gap-4'
                    : 'mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-5 sm:px-6'
            "
        >
            <Link href="/">
                <BrandLogo
                    :light="isDark(variant)"
                    :name="branding.name ?? 'ErlanPro'"
                    :domain="branding.domain ?? 'crm.erlanpro.kg'"
                    :icon-class="isDark(variant) ? undefined : 'h-8 w-8'"
                />
            </Link>

            <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                <Link
                    :href="route('legal')"
                    :class="
                        isDark(variant)
                            ? 'rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-sm font-medium text-slate-100 transition hover:bg-white/10 sm:px-4 sm:py-2.5'
                            : 'rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100'
                    "
                    title="ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ"
                >
                    Реквизиты ИП
                </Link>

                <template v-if="canLogin">
                    <Link
                        v-if="page.props.auth?.user"
                        :href="route('dashboard')"
                        :class="
                            isDark(variant)
                                ? 'rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-400'
                                : 'rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500'
                        "
                    >
                        Перейти в CRM
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            :class="
                                isDark(variant)
                                    ? 'rounded-lg px-3 py-2 text-sm font-medium text-slate-300 transition hover:text-white sm:px-4 sm:py-2.5'
                                    : 'rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 sm:px-4 sm:py-2.5'
                            "
                        >
                            Вход
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            :class="
                                isDark(variant)
                                    ? 'rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400'
                                    : 'rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500'
                            "
                        >
                            Создать аккаунт
                        </Link>
                    </template>
                </template>
            </nav>
        </div>
    </header>
</template>
