<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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
        default: 'Компания',
    },
});

const form = useForm({
    tariff_id: props.company.tariff_id,
    subscription_ends_at: props.company.subscription_ends_at
        ? props.company.subscription_ends_at.slice(0, 10)
        : '',
    is_active: props.company.is_active,
});

function submit() {
    form.put(route('admin.companies.update', props.company.id));
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div>
                <Link
                    :href="route('admin.companies.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                >
                    ← К списку клиентов
                </Link>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">
                    {{ company.name }}
                </h1>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-lg font-semibold text-slate-900">
                    Информация
                </h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Владелец</dt>
                        <dd class="text-right text-slate-900">
                            {{ company.owner_name ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="text-right text-slate-900">
                            {{ company.owner_email ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Регистрация</dt>
                        <dd class="text-right text-slate-900">
                            {{ company.created_at ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Пользователей</dt>
                        <dd class="text-right text-slate-900">
                            {{ company.users_count ?? 0 }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Клиентов CRM</dt>
                        <dd class="text-right text-slate-900">
                            {{ company.clients_count ?? 0 }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Сделок</dt>
                        <dd class="text-right text-slate-900">
                            {{ company.deals_count ?? 0 }}
                        </dd>
                    </div>
                </dl>
            </div>

            <form
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <h2 class="text-lg font-semibold text-slate-900">Подписка</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="tariff_id" value="Тариф" />
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
                                {{ tariff.name }}
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
                            value="Действует до"
                        />
                        <TextInput
                            id="subscription_ends_at"
                            v-model="form.subscription_ends_at"
                            type="date"
                            class="mt-1 block w-full"
                        />
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
                        Активна
                    </label>
                </div>

                <PrimaryButton class="mt-6" :disabled="form.processing">
                    Сохранить
                </PrimaryButton>
            </form>
        </div>
    </AdminLayout>
</template>
