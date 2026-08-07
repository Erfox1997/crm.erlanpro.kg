<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const cards = computed(() => [
    {
        key: 'companies_count',
        label: t('admin.dashboard.clients'),
        color: 'bg-indigo-100 text-indigo-600',
    },
    {
        key: 'active_subscriptions',
        label: t('admin.dashboard.activeSubs'),
        color: 'bg-emerald-100 text-emerald-600',
    },
    {
        key: 'expiring_soon',
        label: t('admin.dashboard.expiringSoon'),
        color: 'bg-amber-100 text-amber-600',
    },
    {
        key: 'new_registrations',
        label: t('admin.dashboard.newRegs'),
        color: 'bg-sky-100 text-sky-600',
    },
    {
        key: 'users_count',
        label: t('admin.dashboard.users'),
        color: 'bg-orange-100 text-orange-600',
    },
    {
        key: 'revenue',
        label: t('admin.dashboard.revenue'),
        color: 'bg-teal-100 text-teal-600',
    },
]);
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ t('admin.dashboard.title') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('admin.panel') }}</p>
            </div>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-slate-500">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">
                            {{ stats[card.key] ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl"
                        :class="card.color"
                    >
                        <span class="text-lg font-semibold">•</span>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            <h2 class="text-lg font-semibold text-slate-900">
                {{ t('admin.dashboard.quickLinks') }}
            </h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <Link
                    :href="route('admin.tariffs.index')"
                    class="inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    {{ t('admin.tariffsPlans') }}
                </Link>
                <Link
                    :href="route('admin.companies.index')"
                    class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    {{ t('admin.companiesList') }}
                </Link>
                <Link
                    :href="route('admin.payment-requisites.edit')"
                    class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    {{ t('admin.paymentRequisites') }}
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
