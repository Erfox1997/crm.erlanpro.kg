<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    requisites: {
        type: Object,
        required: true,
    },
    pageTitle: {
        type: String,
        default: 'Реквизиты',
    },
});

const qrPreview = ref(props.requisites.qr_url);

const form = useForm({
    text: props.requisites.text ?? '',
    whatsapp: props.requisites.whatsapp ?? '',
    qr: null,
});

function onQrChange(event) {
    const file = event.target.files?.[0];
    form.qr = file ?? null;

    if (file) {
        qrPreview.value = URL.createObjectURL(file);
    }
}

function submit() {
    form.post(route('admin.payment-requisites.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ pageTitle }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Реквизиты, QR-код и WhatsApp для оплаты подписки
                </p>
            </div>
        </template>

        <form
            class="max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel for="text" value="Платёжные реквизиты" />
                <textarea
                    id="text"
                    v-model="form.text"
                    rows="8"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Банк, получатель, счёт, назначение платежа..."
                />
                <InputError class="mt-2" :message="form.errors.text" />
            </div>

            <div>
                <InputLabel for="qr" value="QR для оплаты" />
                <p class="mt-1 text-sm text-slate-500">
                    Загрузите изображение QR-кода банка для оплаты подписки.
                </p>
                <input
                    id="qr"
                    type="file"
                    accept="image/*"
                    class="mt-3 block w-full text-sm text-slate-600"
                    @change="onQrChange"
                />
                <InputError class="mt-2" :message="form.errors.qr" />
                <img
                    v-if="qrPreview"
                    :src="qrPreview"
                    alt="QR для оплаты"
                    class="mt-4 max-h-56 rounded-lg border border-slate-200"
                />
            </div>

            <div>
                <InputLabel for="whatsapp" value="WhatsApp" />
                <TextInput
                    id="whatsapp"
                    v-model="form.whatsapp"
                    class="mt-1 block w-full"
                    placeholder="+996..."
                />
                <p class="mt-2 text-sm text-slate-500">
                    После оплаты клиент может отправить скриншот чека в WhatsApp.
                </p>
                <InputError class="mt-2" :message="form.errors.whatsapp" />
            </div>

            <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>
        </form>
    </AdminLayout>
</template>
