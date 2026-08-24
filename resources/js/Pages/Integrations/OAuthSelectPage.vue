<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
    page_ids: props.pages.map((page) => page.page_id),
});

function togglePage(pageId) {
    const index = form.page_ids.indexOf(pageId);
    if (index === -1) {
        form.page_ids.push(pageId);
    } else {
        form.page_ids.splice(index, 1);
    }
}

function submit() {
    form.post(route('integrations.meta.oauth.select-page.store', props.provider));
}
</script>

<template>
    <Head :title="t('integrations.oauthTitle')" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    {{ t('integrations.oauthTitle') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('integrations.oauthConnect', { provider: providerLabel }) }}
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
                        {{ t('integrations.oauthMultiHint') }}
                    </p>

                    <div class="mt-4 space-y-3">
                        <label
                            v-for="page in pages"
                            :key="page.page_id"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                            :class="form.page_ids.includes(page.page_id)
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 hover:border-gray-300'"
                        >
                            <input
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-indigo-600"
                                :checked="form.page_ids.includes(page.page_id)"
                                @change="togglePage(page.page_id)"
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
                        :message="form.errors.page_ids"
                    />

                    <div class="mt-6 flex flex-wrap gap-2">
                        <PrimaryButton
                            type="submit"
                            :disabled="form.processing || form.page_ids.length === 0"
                        >
                            {{ t('integrations.oauthSubmitMulti', { count: form.page_ids.length }) }}
                        </PrimaryButton>
                        <Link :href="route('integrations.index')">
                            <SecondaryButton type="button">
                                {{ t('common.cancel') }}
                            </SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
