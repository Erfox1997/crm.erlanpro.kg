<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    telegramConfigured: {
        type: Boolean,
        default: false,
    },
    announcementChatId: {
        type: String,
        default: null,
    },
    newsBotUsername: {
        type: String,
        default: null,
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const form = useForm({
    title: '',
    body: '',
});

function submit() {
    form.post(route('admin.rule-updates.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('admin.ruleUpdates.newTitle')" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ t('admin.ruleUpdates.newTitle') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ t('admin.ruleUpdates.createHintBefore') }}
                    <Link
                        href="/updates"
                        class="font-medium text-indigo-600 hover:text-indigo-500"
                        target="_blank"
                        >/updates</Link
                    >
                    {{ t('admin.ruleUpdates.createHintMid') }}
                    <template v-if="newsBotUsername"
                        >@{{ newsBotUsername }}</template
                    >
                    {{ t('admin.ruleUpdates.createHintGroup') }}
                    <template v-if="telegramConfigured">
                        <code class="rounded bg-slate-100 px-1">{{
                            announcementChatId
                        }}</code>
                    </template>
                    <template v-else>{{
                        t('admin.ruleUpdates.createHintIfConfigured')
                    }}</template
                    >.
                </p>
            </div>
        </template>

        <form
            class="max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel for="title" :value="t('admin.ruleUpdates.heading')" />
                <TextInput
                    id="title"
                    v-model="form.title"
                    class="mt-1 block w-full"
                    :placeholder="t('admin.ruleUpdates.titlePh')"
                    required
                />
                <InputError class="mt-2" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel for="body" :value="t('admin.ruleUpdates.body')" />
                <textarea
                    id="body"
                    v-model="form.body"
                    rows="12"
                    required
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    :placeholder="t('admin.ruleUpdates.bodyPh')"
                />
                <InputError class="mt-2" :message="form.errors.body" />
                <p class="mt-2 text-xs text-slate-500">
                    {{ t('admin.ruleUpdates.immutable') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="form.processing">
                    {{ t('admin.ruleUpdates.publish') }}
                </PrimaryButton>
                <Link
                    :href="route('admin.rule-updates.index')"
                    class="text-sm text-slate-600 hover:text-slate-900"
                >
                    {{ t('common.cancel') }}
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
