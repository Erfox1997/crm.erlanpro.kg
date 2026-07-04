<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    provider: {
        type: String,
        required: true,
    },
    providerLabel: {
        type: String,
        required: true,
    },
    pages: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    page_id: props.pages[0]?.page_id ?? '',
});

function submit() {
    form.post(route('integrations.meta.oauth.select-page.store', props.provider));
}
</script>

<template>
    <Head title="Выбор Facebook-страницы" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    Выбор Facebook-страницы
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Подключение {{ providerLabel }} через Meta OAuth
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
                <form
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
                    @submit.prevent="submit"
                >
                    <p class="text-sm text-gray-600">
                        У вашего Facebook-аккаунта несколько страниц. Выберите,
                        какую подключить к CRM.
                    </p>

                    <div class="mt-4 space-y-3">
                        <label
                            v-for="page in pages"
                            :key="page.page_id"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                            :class="form.page_id === page.page_id
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 hover:border-gray-300'"
                        >
                            <input
                                v-model="form.page_id"
                                type="radio"
                                class="mt-1"
                                :value="page.page_id"
                            >
                            <span>
                                <span class="block font-medium text-gray-900">
                                    {{ page.page_name || page.page_id }}
                                </span>
                                <span
                                    v-if="page.instagram_username"
                                    class="mt-1 block text-xs text-gray-500"
                                >
                                    Instagram: @{{ page.instagram_username }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <InputError
                        class="mt-4"
                        :message="form.errors.page_id"
                    />

                    <div class="mt-6 flex flex-wrap gap-2">
                        <PrimaryButton
                            type="submit"
                            :disabled="form.processing || !form.page_id"
                        >
                            Подключить выбранную страницу
                        </PrimaryButton>
                        <Link :href="route('integrations.index')">
                            <SecondaryButton type="button">
                                Отмена
                            </SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
