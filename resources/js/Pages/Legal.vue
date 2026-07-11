<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    contactEmail: {
        type: String,
        default: 'support@erlanpro.kg',
    },
    contactPhone: {
        type: String,
        default: '+996 702 300 339',
    },
});

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
                Последнее обновление: 11 июля 2026 г.
            </p>

            <div class="prose prose-slate mt-8 max-w-none text-sm leading-relaxed">
                <section class="mb-8 rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-slate-900">
                        ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ
                    </h2>
                    <dl class="mt-4 space-y-3 text-slate-600">
                        <div>
                            <dt class="font-medium text-slate-800">ПИН</dt>
                            <dd>21706199700221</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-800">Вид деятельности</dt>
                            <dd>Разработка программного обеспечения (ОКУД 62.01.0)</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-800">Юридический адрес</dt>
                            <dd>
                                Кыргызская Республика, Иссык-Кульская область,
                                Ак-Суйский район, Кыдыр Аке айылный аймак,
                                село Новоконстантиновка (Жергез), улица Оторбая,
                                дом 20, 722348
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Сервис CRM ErlanPro
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Сервис
                        <strong>CRM ErlanPro</strong>, доступный по адресу
                        <a
                            href="https://crm.erlanpro.kg"
                            class="text-indigo-600 hover:underline"
                            >https://crm.erlanpro.kg</a
                        >, предоставляется индивидуальным предпринимателем
                        <strong>АСАНАЛИЕВЫМ ЭРЛАНОМ МАЛИКОВИЧЕМ</strong>.
                    </p>
                    <p class="mt-3 text-slate-600">
                        ErlanPro — коммерческое обозначение сервиса CRM.
                        Юридическим лицом, оказывающим услуги, является
                        <strong>ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ</strong>.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">Контакты</h2>
                    <p class="mt-3 text-slate-600">
                        Email:
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-indigo-600 hover:underline"
                            >{{ contactEmail }}</a
                        ><br />
                        Телефон:
                        <a
                            :href="`tel:${contactPhone.replace(/\s/g, '')}`"
                            class="text-indigo-600 hover:underline"
                            >{{ contactPhone }}</a
                        ><br />
                        Сайт:
                        <a
                            href="https://crm.erlanpro.kg"
                            class="text-indigo-600 hover:underline"
                            >https://crm.erlanpro.kg</a
                        >
                    </p>
                </section>
            </div>
        </main>

        <PublicSiteFooter />
    </div>
</template>
