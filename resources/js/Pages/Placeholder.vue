<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    title: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ title }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm"
                >
                    <p class="text-slate-600">
                        {{ t('common.underConstruction') }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
