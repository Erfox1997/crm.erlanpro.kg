<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    sales: { type: Object, required: true },
    date: { type: String, required: true },
    totals: { type: Object, required: true },
    shopConnected: { type: Boolean, default: false },
    pageTitle: { type: String, default: 'История продаж' },
});

const editing = ref(null);
const editForm = useForm({
    warehouse_id: null,
    client_name: '',
    client_phone: '',
    items: [],
    payments: {},
});

const rows = computed(() => props.sales.data || []);

function onDateChange(event) {
    router.get(route('shop-sales.index'), { date: event.target.value }, {
        preserveState: true,
        replace: true,
    });
}

function openEdit(sale) {
    const payload = sale.payload || {};
    const saleData = payload.sale || {};
    editing.value = sale;
    editForm.warehouse_id = payload.warehouse_id || saleData.warehouse?.id || null;
    editForm.client_name = payload.client_name || '';
    editForm.client_phone = payload.client_phone || '';
    editForm.items = (saleData.items || []).map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
        price: item.price,
        unit_type: 'primary',
        name: item.name,
    }));
    const payments = {};
    for (const payment of saleData.payments || []) {
        payments[payment.payment_account_id] = payment.amount;
    }
    editForm.payments = payments;
}

function saveEdit() {
    if (! editing.value) {
        return;
    }

    editForm.put(route('shop-sales.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function cancelSale(sale) {
    if (! confirm('Отменить продажу? Клиенту уйдёт уведомление.')) {
        return;
    }

    router.delete(route('shop-sales.destroy', sale.id), {
        preserveScroll: true,
    });
}

function money(value) {
    return Number(value || 0).toLocaleString('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function statusClass(status) {
    if (status === 'cancelled') {
        return 'bg-red-100 text-red-800';
    }
    if (status === 'updated') {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-emerald-100 text-emerald-800';
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h2>
                <Link
                    :href="route('shop-sales.report')"
                    class="text-sm font-medium text-amber-700 hover:text-amber-800"
                >
                    Отчёт по менеджерам →
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div
                    v-if="!shopConnected"
                    class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    Магазин не подключён.
                    <Link :href="route('integrations.index')" class="font-medium underline">Интеграции</Link>
                </div>

                <div class="mb-4 flex flex-wrap items-end justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Дата</label>
                        <input
                            type="date"
                            class="mt-1 rounded-md border-slate-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            :value="date"
                            @change="onDateChange"
                        >
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500">Сумма за день</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ money(totals.total_amount) }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Чек</th>
                                    <th class="px-4 py-3">Дата</th>
                                    <th class="px-4 py-3">Клиент</th>
                                    <th class="px-4 py-3">Менеджер</th>
                                    <th class="px-4 py-3">Сумма</th>
                                    <th class="px-4 py-3">Статус</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sale in rows" :key="sale.id">
                                    <td class="px-4 py-3 font-medium text-slate-900">#{{ sale.number || sale.shop_document_id }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ sale.created_at }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ sale.client || '—' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ sale.manager || '—' }}</td>
                                    <td class="px-4 py-3 font-medium">{{ money(sale.total_amount) }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusClass(sale.status)"
                                        >
                                            {{ sale.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div v-if="sale.can_edit" class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-amber-700 hover:underline"
                                                @click="openEdit(sale)"
                                            >
                                                Изменить
                                            </button>
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-red-600 hover:underline"
                                                @click="cancelSale(sale)"
                                            >
                                                Удалить
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                        Нет продаж за выбранную дату.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="sales.links?.length > 3"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in sales.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-md border px-3 py-1 text-sm"
                        :class="link.active ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-slate-200 text-slate-600'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <div class="p-5" v-if="editing">
                <h3 class="text-lg font-semibold text-slate-900">
                    Изменить чек #{{ editing.number || editing.shop_document_id }}
                </h3>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="(item, index) in editForm.items"
                        :key="index"
                        class="grid grid-cols-[1fr_5rem_6rem] gap-2"
                    >
                        <input
                            :value="item.name"
                            type="text"
                            class="rounded-md border-slate-300 text-sm"
                            disabled
                        >
                        <input
                            v-model.number="item.quantity"
                            type="number"
                            min="0.001"
                            step="1"
                            class="rounded-md border-slate-300 text-sm"
                        >
                        <input
                            v-model.number="item.price"
                            type="number"
                            min="0"
                            step="1"
                            class="rounded-md border-slate-300 text-sm"
                        >
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="editing = null">Отмена</SecondaryButton>
                    <PrimaryButton
                        type="button"
                        :disabled="editForm.processing || !editForm.items.length"
                        @click="saveEdit"
                    >
                        Сохранить и переотправить
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
