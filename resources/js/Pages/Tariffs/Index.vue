<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    currentSubscription: {
        type: Object,
        required: true,
    },
    tariffs: {
        type: Array,
        default: () => [],
    },
    payment: {
        type: Object,
        default: () => ({}),
    },
});

const selectedTariffId = ref(null);
const paymentSection = ref(null);

const selectedTariff = computed(() =>
    props.tariffs.find((item) => item.id === selectedTariffId.value),
);

const whatsappLink = computed(() => {
    if (!props.payment.whatsapp_url || !selectedTariff.value) {
        return props.payment.whatsapp_url;
    }

    const message = `Здравствуйте! Оплатил тариф «${selectedTariff.value.name}» в CRM ErlanPro. Отправляю скриншот чека.`;

    const digits = props.payment.whatsapp.replace(/\D+/g, '');
    if (!digits) {
        return null;
    }

    return `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
});

function formatPrice(value) {
    return Number(value).toLocaleString('ru-RU');
}

function priceLabel(tariff) {
    if (tariff.is_free) {
        return 'Бесплатно';
    }

    return `${formatPrice(tariff.price)} KGS`;
}

function selectTariff(tariff) {
    if (tariff.is_current) {
        return;
    }

    selectedTariffId.value = tariff.id;
    paymentSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function tariffFeatures(tariff) {
    const items = [
        'Полный доступ ко всем функциям',
        `Срок действия — ${tariff.duration_days} дн.`,
        tariff.max_employees
            ? `До ${tariff.max_employees} сотрудников`
            : 'Без ограничения по сотрудникам',
        tariff.message_retention_days
            ? `Хранение сообщений — ${tariff.message_retention_days} дн.`
            : 'Хранение сообщений без ограничения',
    ];

    if (!tariff.is_free) {
        items.push('Поддержка при подключении');
    }

    return items;
}
</script>

<template>
    <Head title="Тарифы" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Тарифы</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-10 text-center">
                    <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                        Тарифы
                    </h1>
                    <p class="mx-auto mt-3 max-w-2xl text-slate-600">
                        Прозрачные условия — выберите подходящий план и
                        продолжайте работу без ограничений
                    </p>
                </div>

                <div
                    class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="tariff in tariffs"
                        :key="tariff.id"
                        class="group flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:shadow-lg"
                        :class="
                            tariff.is_current
                                ? 'border-teal-400 ring-2 ring-teal-100'
                                : 'border-slate-200'
                        "
                    >
                        <div
                            v-if="tariff.is_current"
                            class="bg-gradient-to-r from-teal-600 to-emerald-500 px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-white"
                        >
                            Ваш тариф
                        </div>

                        <div class="flex flex-1 flex-col p-6">
                            <h2 class="text-xl font-bold text-slate-900">
                                {{ tariff.name }}
                            </h2>
                            <p
                                v-if="tariff.description"
                                class="mt-2 min-h-[2.5rem] text-sm text-slate-500"
                            >
                                {{ tariff.description }}
                            </p>

                            <div class="mt-5">
                                <p class="text-3xl font-bold text-slate-900">
                                    <span
                                        v-if="
                                            tariff.original_price &&
                                            tariff.original_price > tariff.price
                                        "
                                        class="mr-2 text-lg font-normal text-slate-400 line-through"
                                    >
                                        {{ formatPrice(tariff.original_price) }}
                                    </span>
                                    {{ priceLabel(tariff) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    за {{ tariff.duration_days }} дн.
                                </p>
                            </div>

                            <ul class="mt-6 space-y-3 text-sm text-slate-600">
                                <li
                                    v-for="feature in tariffFeatures(tariff)"
                                    :key="feature"
                                    class="flex items-start gap-2"
                                >
                                    <span
                                        class="mt-0.5 text-teal-600"
                                        aria-hidden="true"
                                    >
                                        ✓
                                    </span>
                                    <span>{{ feature }}</span>
                                </li>
                            </ul>

                            <button
                                type="button"
                                class="mt-8 w-full rounded-xl px-4 py-3 text-sm font-semibold transition"
                                :class="
                                    tariff.is_current
                                        ? 'cursor-default bg-gradient-to-r from-teal-600 to-emerald-500 text-white'
                                        : 'bg-slate-900 text-white hover:from-indigo-600 hover:to-teal-600 hover:bg-gradient-to-r'
                                "
                                :disabled="tariff.is_current"
                                @click="selectTariff(tariff)"
                            >
                                {{
                                    tariff.is_current
                                        ? 'Ваш тариф'
                                        : 'Выбрать тариф'
                                }}
                            </button>
                        </div>
                    </article>
                </div>

                <section
                    ref="paymentSection"
                    class="mt-12 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                >
                    <div
                        v-if="selectedTariff"
                        class="mb-6 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900"
                    >
                        Вы выбрали тариф
                        <strong>{{ selectedTariff.name }}</strong>
                        — оплатите по реквизитам ниже и отправьте скриншот
                        чека в WhatsApp.
                    </div>

                    <div class="grid gap-8 lg:grid-cols-[1fr_auto]">
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Платёжные реквизиты
                            </p>
                            <div
                                v-if="payment.text"
                                class="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-slate-700"
                            >
                                {{ payment.text }}
                            </div>
                            <p
                                v-else
                                class="mt-4 text-sm text-slate-500"
                            >
                                Реквизиты пока не добавлены. Обратитесь к
                                администратору.
                            </p>

                            <div
                                v-if="payment.whatsapp"
                                class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900"
                            >
                                <p class="font-medium">
                                    WhatsApp: {{ payment.whatsapp }}
                                </p>
                                <p class="mt-2 text-emerald-800">
                                    После оплаты отправьте скриншот чека в
                                    WhatsApp и укажите выбранный тариф.
                                </p>
                                <a
                                    v-if="whatsappLink"
                                    :href="whatsappLink"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-4 inline-flex rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500"
                                >
                                    Написать в WhatsApp
                                </a>
                            </div>
                        </div>

                        <div v-if="payment.qr_url" class="text-center">
                            <p
                                class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                QR для оплаты
                            </p>
                            <img
                                :src="payment.qr_url"
                                alt="QR для оплаты"
                                class="mx-auto max-h-56 rounded-xl border border-slate-200"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
