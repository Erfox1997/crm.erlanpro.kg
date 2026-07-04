<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const branding = page.props.branding ?? {};
</script>

<template>
    <div class="min-h-screen bg-slate-950 lg:flex">
        <!-- Брендовая колонка -->
        <div
            class="relative hidden overflow-hidden lg:flex lg:w-[44%] lg:flex-col lg:justify-between lg:px-12 lg:py-14"
        >
            <div
                class="absolute inset-0 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900"
            />
            <div
                class="absolute -right-24 top-1/4 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"
            />
            <div
                class="absolute -left-16 bottom-0 h-72 w-72 rounded-full bg-cyan-500/10 blur-3xl"
            />

            <div class="relative z-10">
                <Link href="/" class="inline-flex items-center gap-3 text-white">
                    <BrandLogo
                        light
                        :name="branding.name ?? 'ErlanPro'"
                        :domain="branding.domain ?? 'crm.erlanpro.kg'"
                    />
                </Link>
                <p
                    class="mt-10 max-w-sm text-2xl font-semibold leading-snug text-white"
                >
                    Управляйте клиентами и сделками в одном месте
                </p>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-300">
                    Воронки продаж, карточки сделок, база клиентов и командная
                    работа — для компаний в Кыргызстане и за её пределами.
                </p>
            </div>

            <p class="relative z-10 text-xs text-slate-500">
                © {{ new Date().getFullYear() }}
                {{ branding.domain ?? 'crm.erlanpro.kg' }}
            </p>
        </div>

        <!-- Форма -->
        <div
            class="flex flex-1 flex-col justify-center bg-slate-50 px-4 py-10 sm:px-8"
        >
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <Link href="/">
                        <BrandLogo
                            :name="branding.name ?? 'ErlanPro'"
                            :domain="branding.domain ?? 'crm.erlanpro.kg'"
                            icon-class="h-9 w-9"
                        />
                    </Link>
                </div>

                <div
                    class="rounded-2xl border border-slate-200/90 bg-white p-8 shadow-xl shadow-slate-900/[0.06] ring-1 ring-slate-900/5"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
