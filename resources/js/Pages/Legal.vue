<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    legal: {
        type: Object,
        required: true,
    },
});

const aboutParagraphs = computed(() =>
    String(props.legal.about ?? '')
        .split(/\n\s*\n/)
        .map((part) => part.trim())
        .filter(Boolean),
);
</script>

<template>
    <Head title="Реквизиты ИП" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <PublicSiteHeader />

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl font-bold text-slate-900">
                Реквизиты и сведения об индивидуальном предпринимателе
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Последнее обновление: {{ legal.updated_at_label }}
            </p>

            <div class="prose prose-slate mt-8 max-w-none text-sm leading-relaxed">
                <section class="mb-8 rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ legal.legal_name }}
                    </h2>
                    <dl class="mt-4 space-y-3 text-slate-600">
                        <div>
                            <dt class="font-medium text-slate-800">ПИН</dt>
                            <dd>{{ legal.pin }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-800">Вид деятельности</dt>
                            <dd>{{ legal.activity }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-800">Юридический адрес</dt>
                            <dd class="whitespace-pre-line">{{ legal.address }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Сервис CRM ErlanPro
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
                    <h2 class="text-lg font-semibold text-slate-900">Контакты</h2>
                    <p class="mt-3 text-slate-600">
                        Email:
                        <a
                            :href="`mailto:${legal.contact_email}`"
                            class="text-indigo-600 hover:underline"
                            >{{ legal.contact_email }}</a
                        ><br />
                        Телефон:
                        <a
                            :href="`tel:${legal.contact_phone.replace(/\s/g, '')}`"
                            class="text-indigo-600 hover:underline"
                            >{{ legal.contact_phone }}</a
                        ><br />
                        Сайт:
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
