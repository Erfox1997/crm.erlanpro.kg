<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
    tariffs: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const form = useForm({
    tariff_id: props.company.tariff_id,
    subscription_ends_at: props.company.subscription_ends_at
        ? props.company.subscription_ends_at.slice(0, 10)
        : '',
    is_active: props.company.is_active,
});

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function addDays(date, days) {
    const result = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    result.setDate(result.getDate() + days);

    return result;
}

function baseSubscriptionDate() {
    if (props.company.subscription_ends_at) {
        const [year, month, day] = props.company.subscription_ends_at
            .slice(0, 10)
            .split('-')
            .map(Number);

        return new Date(year, month - 1, day);
    }

    const today = new Date();

    return new Date(today.getFullYear(), today.getMonth(), today.getDate());
}

watch(
    () => form.tariff_id,
    (tariffId) => {
        const tariff = props.tariffs.find(
            (item) => Number(item.id) === Number(tariffId),
        );

        if (!tariff || tariff.is_free || !tariff.duration_days) {
            return;
        }

        form.subscription_ends_at = formatDate(
            addDays(baseSubscriptionDate(), Number(tariff.duration_days)),
        );
    },
);

function submit() {
    form.put(route('admin.companies.update', props.company.id));
}

function blockCompany() {
    if (!confirm(t('admin.companies.blockConfirm'))) {
        return;
    }

    router.post(route('admin.companies.block', props.company.id));
}

function unblockCompany() {
    router.post(route('admin.companies.unblock', props.company.id));
}

function deleteCompany() {
    if (!confirm(t('admin.companies.deleteConfirm'))) {
        return;
    }

    router.delete(route('admin.companies.destroy', props.company.id));
}
</script>

<template>
    <Head :title="company.name" />

    <AdminLayout>
        <template #header>
            <div>
                <Link
                    :href="route('admin.companies.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                >
                    {{ t('admin.companies.back') }}
                </Link>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ company.name }}
                    </h1>
                    <span
                        v-if="company.is_blocked"
                        class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700"
                    >
                        {{ t('admin.companies.blocked') }}
                    </span>
                </div>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
            <div class="space-y-6">
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ t('admin.companies.info') }}
                    </h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">
                                {{ t('admin.companies.owner') }}
                            </dt>
                            <dd class="text-right text-slate-900">
                                {{ company.owner_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ t('common.email') }}</dt>
                            <dd class="text-right text-slate-900">
                                {{ company.owner_email ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">
                                {{ t('admin.companies.registered') }}
                            </dt>
                            <dd class="text-right text-slate-900">
                                {{ company.created_at ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">
                                {{ t('admin.companies.usersCount') }}
                            </dt>
                            <dd class="text-right text-slate-900">
                                {{ company.users_count ?? 0 }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">
                                {{ t('admin.companies.crmClients') }}
                            </dt>
                            <dd class="text-right text-slate-900">
                                {{ company.clients_count ?? 0 }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">
                                {{ t('admin.companies.deals') }}
                            </dt>
                            <dd class="text-right text-slate-900">
                                {{ company.deals_count ?? 0 }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ t('admin.companies.dangerZone') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ t('admin.companies.dangerZoneHint') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <DangerButton
                            v-if="!company.is_blocked"
                            type="button"
                            @click="blockCompany"
                        >
                            {{ t('admin.companies.block') }}
                        </DangerButton>
                        <SecondaryButton
                            v-else
                            type="button"
                            @click="unblockCompany"
                        >
                            {{ t('admin.companies.unblock') }}
                        </SecondaryButton>

                        <DangerButton type="button" @click="deleteCompany">
                            {{ t('admin.companies.delete') }}
                        </DangerButton>
                    </div>
                </div>
            </div>

            <form
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <h2 class="text-lg font-semibold text-slate-900">
                    {{ t('admin.companies.subscription') }}
                </h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel
                            for="tariff_id"
                            :value="t('admin.companies.tariff')"
                        />
                        <select
                            id="tariff_id"
                            v-model="form.tariff_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="tariff in tariffs"
                                :key="tariff.id"
                                :value="tariff.id"
                            >
                                {{
                                    tariff.is_free
                                        ? tariff.name
                                        : `${tariff.name} (${tariff.duration_days} ${t('admin.companies.days')})`
                                }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="form.errors.tariff_id"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="subscription_ends_at"
                            :value="t('admin.companies.validUntil')"
                        />
                        <TextInput
                            id="subscription_ends_at"
                            v-model="form.subscription_ends_at"
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            {{ t('admin.companies.validUntilHint') }}
                        </p>
                        <InputError
                            class="mt-2"
                            :message="form.errors.subscription_ends_at"
                        />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600"
                        />
                        {{ t('admin.companies.active') }}
                    </label>
                </div>

                <PrimaryButton class="mt-6" :disabled="form.processing">
                    {{ t('common.save') }}
                </PrimaryButton>
            </form>
        </div>
    </AdminLayout>
</template>
