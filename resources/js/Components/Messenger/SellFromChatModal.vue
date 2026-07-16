<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    conversationId: { type: Number, required: true },
    clientName: { type: String, default: '' },
    clientPhone: { type: String, default: '' },
    catalogUrl: { type: String, required: true },
    submitUrl: { type: String, required: true },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const loadError = ref('');
const search = ref('');
const products = ref([]);
const warehouses = ref([]);
const paymentAccounts = ref([]);
const currency = ref('KGS');
const cart = ref([]);
const warehouseId = ref(null);
const payments = ref({});

const form = useForm({
    warehouse_id: null,
    client_name: '',
    client_phone: '',
    items: [],
    payments: {},
});

const filteredProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (! q) {
        return products.value.slice(0, 40);
    }

    return products.value
        .filter((p) =>
            p.name?.toLowerCase().includes(q)
            || String(p.barcode || '').toLowerCase().includes(q),
        )
        .slice(0, 40);
});

const cartTotal = computed(() =>
    cart.value.reduce((sum, line) => sum + roundMoney(line.price * line.quantity), 0),
);

const paidTotal = computed(() =>
    Object.values(payments.value).reduce((sum, amount) => sum + Number(amount || 0), 0),
);

watch(
    () => props.show,
    async (open) => {
        if (! open) {
            return;
        }

        form.clearErrors();
        search.value = '';
        cart.value = [];
        payments.value = {};
        form.client_name = props.clientName || '';
        form.client_phone = props.clientPhone || '';
        await loadCatalog();
    },
);

async function loadCatalog() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await fetch(props.catalogUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const data = await response.json();

        if (! response.ok) {
            throw new Error(data.message || 'Не удалось загрузить каталог');
        }

        products.value = data.products || [];
        warehouses.value = data.warehouses || [];
        paymentAccounts.value = data.payment_accounts || [];
        currency.value = data.currency || 'KGS';

        const defaultWarehouse = warehouses.value.find((w) => w.is_default) || warehouses.value[0];
        warehouseId.value = defaultWarehouse?.id ?? null;

        const defaults = {};
        for (const account of paymentAccounts.value) {
            defaults[account.id] = '';
        }
        payments.value = defaults;
    } catch (error) {
        loadError.value = error.message || 'Ошибка загрузки';
    } finally {
        loading.value = false;
    }
}

function stockFor(product) {
    if (! warehouseId.value) {
        return 0;
    }

    return Number(product.stock?.[String(warehouseId.value)] ?? 0);
}

function addProduct(product) {
    const existing = cart.value.find((line) => line.product_id === product.id);
    if (existing) {
        existing.quantity = roundQty(existing.quantity + 1);
        return;
    }

    cart.value.push({
        product_id: product.id,
        name: product.name,
        price: Number(product.sale_price || 0),
        quantity: 1,
        unit_type: 'primary',
    });
}

function changeQty(line, delta) {
    line.quantity = roundQty(Math.max(0.001, Number(line.quantity) + delta));
}

function removeLine(line) {
    cart.value = cart.value.filter((item) => item.product_id !== line.product_id);
}

function fillFullPayment(accountId) {
    const next = { ...payments.value };
    for (const id of Object.keys(next)) {
        next[id] = '';
    }
    next[accountId] = String(cartTotal.value);
    payments.value = next;
}

function close() {
    emit('close');
}

function submit() {
    if (! warehouseId.value || cart.value.length === 0) {
        return;
    }

    const paymentPayload = {};
    for (const [id, amount] of Object.entries(payments.value)) {
        const value = Number(amount || 0);
        if (value > 0) {
            paymentPayload[id] = value;
        }
    }

    form.warehouse_id = warehouseId.value;
    form.items = cart.value.map((line) => ({
        product_id: line.product_id,
        quantity: line.quantity,
        price: line.price,
        unit_type: line.unit_type || 'primary',
    }));
    form.payments = paymentPayload;

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            close();
            router.reload({ only: ['messages', 'conversations', 'selectedConversation'] });
        },
    });
}

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function roundQty(value) {
    return Math.round((Number(value) || 0) * 1000) / 1000;
}

function money(value) {
    return Number(value || 0).toLocaleString('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Продажа</h3>
                    <p class="text-xs text-slate-500">Быстрый чек по телефону</p>
                </div>
                <button
                    type="button"
                    class="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                    @click="close"
                >
                    ✕
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4">
                <div v-if="loading" class="py-10 text-center text-sm text-slate-500">
                    Загрузка товаров…
                </div>
                <div v-else-if="loadError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ loadError }}
                </div>
                <template v-else>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Склад" />
                            <select
                                v-model="warehouseId"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                <option
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :value="warehouse.id"
                                >
                                    {{ warehouse.name }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.warehouse_id" />
                        </div>
                        <div>
                            <InputLabel value="Телефон клиента" />
                            <input
                                v-model="form.client_phone"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                placeholder="+996…"
                            >
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Найти товар" />
                        <input
                            v-model="search"
                            type="search"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Название или штрихкод"
                            autofocus
                        >
                    </div>

                    <div class="space-y-2">
                        <button
                            v-for="product in filteredProducts"
                            :key="product.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-left shadow-sm active:scale-[0.99]"
                            @click="addProduct(product)"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ product.name }}</p>
                                <p class="text-xs text-slate-500">
                                    Остаток: {{ stockFor(product) }}
                                    <span v-if="product.barcode"> · {{ product.barcode }}</span>
                                </p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-amber-700">
                                {{ money(product.sale_price) }}
                            </span>
                        </button>
                        <p v-if="filteredProducts.length === 0" class="text-sm text-slate-500">
                            Ничего не найдено
                        </p>
                    </div>

                    <div v-if="cart.length" class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                        <p class="text-sm font-semibold text-slate-900">Корзина</p>
                        <div
                            v-for="line in cart"
                            :key="line.product_id"
                            class="rounded-lg bg-white px-3 py-2 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-slate-900">{{ line.name }}</p>
                                <button
                                    type="button"
                                    class="text-xs text-red-600"
                                    @click="removeLine(line)"
                                >
                                    Удалить
                                </button>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-lg font-semibold"
                                        @click="changeQty(line, -1)"
                                    >
                                        −
                                    </button>
                                    <input
                                        v-model.number="line.quantity"
                                        type="number"
                                        min="0.001"
                                        step="1"
                                        class="w-16 rounded-lg border-slate-300 text-center text-sm"
                                    >
                                    <button
                                        type="button"
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-lg font-semibold"
                                        @click="changeQty(line, 1)"
                                    >
                                        +
                                    </button>
                                </div>
                                <div class="text-right">
                                    <input
                                        v-model.number="line.price"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="w-24 rounded-lg border-slate-300 text-right text-sm"
                                    >
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ money(line.price * line.quantity) }} {{ currency }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="text-right text-base font-semibold text-slate-900">
                            Итого: {{ money(cartTotal) }} {{ currency }}
                        </p>
                    </div>

                    <div v-if="cart.length" class="space-y-2">
                        <p class="text-sm font-semibold text-slate-900">Оплата</p>
                        <div
                            v-for="account in paymentAccounts"
                            :key="account.id"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="payments[account.id]"
                                type="number"
                                min="0"
                                step="1"
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                :placeholder="account.name"
                            >
                            <button
                                type="button"
                                class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700"
                                @click="fillFullPayment(account.id)"
                            >
                                Всё
                            </button>
                        </div>
                        <p class="text-xs text-slate-500">
                            Оплачено: {{ money(paidTotal) }} · Долг: {{ money(Math.max(0, cartTotal - paidTotal)) }}
                        </p>
                    </div>

                    <InputError :message="form.errors.shop || form.errors.items || form.errors.client_phone" />
                </template>
            </div>

            <div class="flex gap-2 border-t border-slate-200 px-4 py-3">
                <SecondaryButton type="button" class="flex-1 justify-center" @click="close">
                    Отмена
                </SecondaryButton>
                <PrimaryButton
                    type="button"
                    class="flex-1 justify-center !bg-amber-600 hover:!bg-amber-500"
                    :disabled="form.processing || !cart.length || !warehouseId || loading"
                    @click="submit"
                >
                    Продать {{ cart.length ? `· ${money(cartTotal)}` : '' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
