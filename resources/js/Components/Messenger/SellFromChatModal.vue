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
const searchOpen = ref(false);
const products = ref([]);
const categories = ref([]);
const warehouses = ref([]);
const paymentAccounts = ref([]);
const currency = ref('KGS');
const cart = ref([]);
const warehouseId = ref(null);
const selectedCategoryId = ref(null);
const cashAccountId = ref(null);
const cashlessAccountId = ref(null);
const paymentCash = ref(0);
const paymentCard = ref(0);

const form = useForm({
    warehouse_id: null,
    client_name: '',
    client_phone: '',
    items: [],
    payments: {},
});

const categoryCards = computed(() => {
    const counts = {};
    for (const product of products.value) {
        const key = product.category_id ?? 0;
        counts[key] = (counts[key] || 0) + 1;
    }

    const list = categories.value
        .map((category) => ({
            ...category,
            count: counts[category.id] || 0,
        }))
        .filter((category) => category.count > 0);

    const uncategorized = counts[0] || 0;
    if (uncategorized > 0) {
        list.push({
            id: 0,
            name: 'Без категории',
            count: uncategorized,
            is_service: false,
        });
    }

    return list;
});

const selectedCategory = computed(() =>
    categoryCards.value.find((c) => c.id === selectedCategoryId.value) || null,
);

const categoryProducts = computed(() => {
    if (selectedCategoryId.value === null) {
        return [];
    }

    return products.value.filter((product) => {
        const key = product.category_id ?? 0;
        return key === selectedCategoryId.value;
    });
});

const searchResults = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (q.length < 1) {
        return [];
    }

    return products.value
        .filter((p) =>
            p.name?.toLowerCase().includes(q)
            || String(p.barcode || '').toLowerCase().includes(q)
            || String(p.category_name || '').toLowerCase().includes(q),
        )
        .slice(0, 12);
});

const cashAccounts = computed(() =>
    paymentAccounts.value.filter((a) => a.type === 'cash'),
);

const cashlessAccounts = computed(() =>
    paymentAccounts.value.filter((a) => a.type === 'cashless' || a.type === 'card'),
);

const cartTotal = computed(() =>
    cart.value.reduce((sum, line) => sum + roundMoney(line.price * line.quantity), 0),
);

const paidTotal = computed(() =>
    roundMoney((Number(paymentCash.value) || 0) + (Number(paymentCard.value) || 0)),
);

const creditTotal = computed(() =>
    roundMoney(Math.max(0, cartTotal.value - paidTotal.value)),
);

watch(
    () => props.show,
    async (open) => {
        if (! open) {
            return;
        }

        form.clearErrors();
        search.value = '';
        searchOpen.value = false;
        selectedCategoryId.value = null;
        cart.value = [];
        paymentCash.value = 0;
        paymentCard.value = 0;
        form.client_name = props.clientName || '';
        form.client_phone = props.clientPhone || '';
        await loadCatalog();
    },
);

watch(search, (value) => {
    searchOpen.value = value.trim().length > 0;
});

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

        let data = {};
        const raw = await response.text();
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch {
            throw new Error(
                response.ok
                    ? 'Некорректный ответ сервера'
                    : `Ошибка сервера (${response.status}). Проверьте деплой CRM и магазина.`,
            );
        }

        if (! response.ok) {
            throw new Error(data.message || `Не удалось загрузить каталог (${response.status})`);
        }

        products.value = data.products || [];
        categories.value = data.categories || [];
        warehouses.value = data.warehouses || [];
        paymentAccounts.value = data.payment_accounts || [];
        currency.value = data.currency || 'KGS';

        const defaultWarehouse = warehouses.value.find((w) => w.is_default) || warehouses.value[0];
        warehouseId.value = defaultWarehouse?.id ?? null;

        const defaultCash = cashAccounts.value.find((a) => a.is_default) || cashAccounts.value[0];
        const defaultCashless = cashlessAccounts.value.find((a) => a.is_default) || cashlessAccounts.value[0];
        cashAccountId.value = defaultCash?.id ?? null;
        cashlessAccountId.value = defaultCashless?.id ?? null;
        paymentCash.value = 0;
        paymentCard.value = 0;

        if (! products.value.length && ! warehouses.value.length) {
            loadError.value = 'Каталог пуст. Добавьте товары и склады в магазине.';
        }
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

function openCategory(categoryId) {
    selectedCategoryId.value = categoryId;
    search.value = '';
    searchOpen.value = false;
}

function backToCategories() {
    selectedCategoryId.value = null;
}

function addProduct(product) {
    const existing = cart.value.find((line) => line.product_id === product.id);
    if (existing) {
        existing.quantity = roundQty(existing.quantity + 1);
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            price: Number(product.sale_price || 0),
            quantity: 1,
            unit_type: 'primary',
        });
    }

    search.value = '';
    searchOpen.value = false;
}

function changeQty(line, delta) {
    line.quantity = roundQty(Math.max(0.001, Number(line.quantity) + delta));
}

function removeLine(line) {
    cart.value = cart.value.filter((item) => item.product_id !== line.product_id);
}

/** Like shop POS: fill this field with (total − other field). */
function fillPaymentRemaining(field) {
    const total = cartTotal.value;
    const cash = Math.max(0, Number(paymentCash.value) || 0);
    const card = Math.max(0, Number(paymentCard.value) || 0);

    if (field === 'cash') {
        paymentCash.value = roundMoney(Math.max(0, total - card));
    } else {
        paymentCard.value = roundMoney(Math.max(0, total - cash));
    }
}

function clampPayment(field) {
    const total = cartTotal.value;
    let cash = Math.max(0, Number(paymentCash.value) || 0);
    let card = Math.max(0, Number(paymentCard.value) || 0);

    if (field === 'card') {
        const maxCard = roundMoney(Math.max(0, total - cash));
        if (card > maxCard) {
            card = maxCard;
        }
    }

    paymentCash.value = cash;
    paymentCard.value = card;
}

function closeSearchSoon() {
    window.setTimeout(() => {
        searchOpen.value = false;
    }, 180);
}

function close() {
    emit('close');
}

function submit() {
    if (! warehouseId.value || cart.value.length === 0) {
        return;
    }

    const paymentPayload = {};
    const cash = Math.max(0, Number(paymentCash.value) || 0);
    const card = Math.max(0, Number(paymentCard.value) || 0);
    if (cash > 0 && cashAccountId.value) {
        paymentPayload[cashAccountId.value] = cash;
    }
    if (card > 0 && cashlessAccountId.value) {
        paymentPayload[cashlessAccountId.value] = card;
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
                    <p class="text-xs text-slate-500">Категории или поиск — быстрый чек</p>
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
                    Загрузка каталога…
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

                    <div class="relative">
                        <InputLabel value="Поиск товара" />
                        <input
                            v-model="search"
                            type="search"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Начните вводить название…"
                            autocomplete="off"
                            @focus="searchOpen = search.trim().length > 0"
                            @blur="closeSearchSoon"
                        >
                        <div
                            v-if="searchOpen && searchResults.length"
                            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <button
                                v-for="product in searchResults"
                                :key="'s-' + product.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-amber-50"
                                @mousedown.prevent="addProduct(product)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ product.name }}</p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ product.category_name || 'Без категории' }}
                                        · ост. {{ stockFor(product) }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-amber-700">
                                    {{ money(product.sale_price) }}
                                </span>
                            </button>
                        </div>
                        <p
                            v-else-if="searchOpen && search.trim() && !searchResults.length"
                            class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 shadow-lg"
                        >
                            Ничего не найдено
                        </p>
                    </div>

                    <div v-if="selectedCategoryId === null" class="space-y-2">
                        <p class="text-sm font-semibold text-slate-900">Категории</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <button
                                v-for="category in categoryCards"
                                :key="category.id"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-4 text-left shadow-sm transition active:scale-[0.99] hover:border-amber-300 hover:bg-amber-50/40"
                                @click="openCategory(category.id)"
                            >
                                <p class="text-sm font-semibold text-slate-900">{{ category.name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ category.count }} тов.</p>
                            </button>
                        </div>
                        <p v-if="!categoryCards.length" class="text-sm text-slate-500">
                            Нет категорий с товарами
                        </p>
                    </div>

                    <div v-else class="space-y-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-sm font-medium text-amber-700"
                            @click="backToCategories"
                        >
                            ← Категории
                        </button>
                        <p class="text-sm font-semibold text-slate-900">
                            {{ selectedCategory?.name }}
                        </p>
                        <button
                            v-for="product in categoryProducts"
                            :key="product.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-left shadow-sm active:scale-[0.99]"
                            @click="addProduct(product)"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ product.name }}</p>
                                <p class="text-xs text-slate-500">Остаток: {{ stockFor(product) }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-amber-700">
                                {{ money(product.sale_price) }}
                            </span>
                        </button>
                        <p v-if="!categoryProducts.length" class="text-sm text-slate-500">
                            В категории пусто
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
                                    <p class="text-sm font-semibold tabular-nums text-slate-800">
                                        {{ money(line.price) }} {{ currency }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        × {{ line.quantity }} = {{ money(line.price * line.quantity) }} {{ currency }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="text-right text-base font-semibold text-slate-900">
                            Итого: {{ money(cartTotal) }} {{ currency }}
                        </p>
                    </div>

                    <div v-if="cart.length" class="space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Оплата</p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    Наличные
                                </label>
                                <select
                                    v-model="cashAccountId"
                                    class="mb-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                >
                                    <option
                                        v-for="account in cashAccounts"
                                        :key="'cash-' + account.id"
                                        :value="account.id"
                                    >
                                        {{ account.name }}
                                    </option>
                                </select>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="paymentCash"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        @focus="$event.target.select()"
                                        @input="clampPayment('cash')"
                                    >
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                                        title="Заполнить остаток"
                                        @click="fillPaymentRemaining('cash')"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-blue-700">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    Безнал
                                </label>
                                <select
                                    v-model="cashlessAccountId"
                                    class="mb-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                >
                                    <option
                                        v-for="account in cashlessAccounts"
                                        :key="'card-' + account.id"
                                        :value="account.id"
                                    >
                                        {{ account.name }}
                                    </option>
                                </select>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="paymentCard"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        @focus="$event.target.select()"
                                        @input="clampPayment('card')"
                                    >
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100"
                                        title="Заполнить остаток"
                                        @click="fillPaymentRemaining('card')"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500">
                            Оплачено: {{ money(paidTotal) }} · Долг: {{ money(creditTotal) }}
                        </p>
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Пример: безнал 400 → галочка на наличных поставит остаток {{ money(cartTotal) }} − 400.
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
