<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tariff: {
        type: Object,
        default: null,
    },
    pageTitle: {
        type: String,
        default: 'Тариф',
    },
});

const isEdit = computed(() => props.tariff !== null);

const form = useForm({
    name: props.tariff?.name ?? '',
    description: props.tariff?.description ?? '',
    price: props.tariff?.price ?? 0,
    original_price: props.tariff?.original_price ?? '',
    duration_days: props.tariff?.duration_days ?? 30,
    is_free: props.tariff?.is_free ?? false,
    is_active: props.tariff?.is_active ?? true,
    sort_order: props.tariff?.sort_order ?? 0,
    max_employees: props.tariff?.max_employees ?? '',
    message_retention_days: props.tariff?.message_retention_days ?? '',
    max_deals: props.tariff?.max_deals ?? '',
});

function submit() {
    const payload = {
        ...form.data(),
        original_price: form.original_price === '' ? null : form.original_price,
        max_employees: form.max_employees === '' ? null : form.max_employees,
        message_retention_days:
            form.message_retention_days === ''
                ? null
                : form.message_retention_days,
        max_deals: form.max_deals === '' ? null : form.max_deals,
    };

    if (isEdit.value) {
        form.transform(() => payload).put(route('admin.tariffs.update', props.tariff.id));
        return;
    }

    form.transform(() => payload).post(route('admin.tariffs.store'));
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ pageTitle }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">Тариф</p>
            </div>
        </template>

        <form
            class="max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel for="name" value="Название" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    class="mt-1 block w-full"
                    required
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel
                    for="description"
                    value="Описание (необязательно)"
                />
                <TextInput
                    id="description"
                    v-model="form.description"
                    class="mt-1 block w-full"
                    placeholder="Краткое описание для страницы тарифов"
                />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel for="price" value="Цена" />
                    <TextInput
                        id="price"
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="mt-1 block w-full"
                        :disabled="form.is_free"
                    />
                    <InputError class="mt-2" :message="form.errors.price" />
                </div>
                <div>
                    <InputLabel
                        for="original_price"
                        value="Старая цена (необязательно)"
                    />
                    <TextInput
                        id="original_price"
                        v-model="form.original_price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="mt-1 block w-full"
                        :disabled="form.is_free"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.original_price"
                    />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel for="duration_days" value="Срок (дней)" />
                    <TextInput
                        id="duration_days"
                        v-model="form.duration_days"
                        type="number"
                        min="1"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.duration_days"
                    />
                </div>
                <div>
                    <InputLabel for="sort_order" value="Порядок сортировки" />
                    <TextInput
                        id="sort_order"
                        v-model="form.sort_order"
                        type="number"
                        min="0"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.sort_order" />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-800">Ограничения тарифа</p>
                <p class="mt-1 text-xs text-slate-500">
                    Пустое значение — без ограничения. Сообщения старше указанного
                    срока удаляются автоматически.
                </p>

                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel for="max_employees" value="Макс. сотрудников" />
                        <TextInput
                            id="max_employees"
                            v-model="form.max_employees"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            placeholder="Без ограничения"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.max_employees"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="message_retention_days"
                            value="Хранение сообщений (дней)"
                        />
                        <TextInput
                            id="message_retention_days"
                            v-model="form.message_retention_days"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            placeholder="Без ограничения"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.message_retention_days"
                        />
                    </div>
                    <div>
                        <InputLabel for="max_deals" value="Макс. сделок" />
                        <TextInput
                            id="max_deals"
                            v-model="form.max_deals"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            placeholder="Без ограничения"
                        />
                        <InputError class="mt-2" :message="form.errors.max_deals" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="form.is_free"
                        type="checkbox"
                        class="rounded border-slate-300 text-indigo-600"
                    />
                    Бесплатный тариф
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-slate-300 text-indigo-600"
                    />
                    Активен
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <PrimaryButton :disabled="form.processing">
                    Сохранить
                </PrimaryButton>
                <Link :href="route('admin.tariffs.index')">
                    <SecondaryButton type="button">Отмена</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
