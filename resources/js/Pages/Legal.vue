<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    legal: {
        type: Object,
        required: true,
    },
});

const { t } = useI18n();

const aboutParagraphs = computed(() =>
    String(props.legal.about ?? '')
        .split(/\n\s*\n/)
        .map((part) => part.trim())
        .filter(Boolean),
);
</script>

<template>
    <Head :title="t('legal.title')" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <PublicSiteHeader />

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl font-bold text-slate-900">
                {{ t('legal.heading') }}
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ t('legal.updated', { date: legal.updated_at_label }) }}
            </p>

            <div class="prose prose-slate mt-8 max-w-none text-sm leading-relaxed">
                <section class="mb-8 rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ legal.legal_name }}
                    </h2>
                    <dl class="mt-4 space-y-3 text-slate-600">
                        <div>
                            <dt class="font-medium text-slate-800">{{ t('legal.pin') }}</dt>
                            <dd>{{ legal.pin }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-800">{{ t('legal.activity') }}</dt>
                            <dd>{{ legal.activity }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-800">{{ t('legal.address') }}</dt>
                            <dd class="whitespace-pre-line">{{ legal.address }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ t('legal.service') }}
                    </h2>
                    <p
                        v-for="(paragraph, index) in aboutParagraphs"
                        :key="index"
                        class="mt-3 whitespace-pre-line text-slate-600"
                    >
                        {{ paragraph }}
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ t('legal.contacts') }}
                    </h2>
                    <p class="mt-3 text-slate-600">
                        {{ t('common.email') }}:
                        <a
                            :href="`mailto:${legal.contact_email}`"
                            class="text-indigo-600 hover:underline"
                            >{{ legal.contact_email }}</a
                        ><br />
                        {{ t('common.phone') }}:
                        <a
                            :href="`tel:${legal.contact_phone.replace(/\s/g, '')}`"
                            class="text-indigo-600 hover:underline"
                            >{{ legal.contact_phone }}</a
                        ><br />
                        {{ t('legal.site') }}:
                        <a
                            :href="legal.site_url"
                            class="text-indigo-600 hover:underline"
                            >{{ legal.site_url }}</a
                        >
                    </p>
                </section>
            </div>
        </main>

        <PublicSiteFooter />
    </div>
</template>
