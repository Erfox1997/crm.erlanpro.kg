<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps({
    botConfigured: { type: Boolean, default: false },
    botUsername: { type: String, default: '' },
});

const status = ref('Открываем мессенджер…');
const error = ref('');

onMounted(async () => {
    const tg = window.Telegram?.WebApp;
    if (!tg) {
        error.value = 'Откройте эту страницу через кнопку Mini App в Telegram-боте.';
        status.value = '';
        return;
    }

    tg.ready();
    tg.expand();

    const initData = tg.initData || '';
    if (!initData) {
        error.value = 'Нет данных Telegram. Нажмите /start в боте и откройте мессенджер снова.';
        status.value = '';
        return;
    }

    try {
        const { data } = await window.axios.post(route('tma.auth'), {
            init_data: initData,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (data?.redirect) {
            window.location.href = data.redirect;
            return;
        }

        error.value = 'Не удалось войти.';
        status.value = '';
    } catch (e) {
        error.value = e?.response?.data?.message
            || 'Доступ запрещён. Попросите добавить ваш @username в «Сотрудники».';
        status.value = '';
    }
});
</script>

<template>
    <Head title="Telegram Mini App" />

    <div class="flex min-h-screen items-center justify-center bg-[#0f172a] px-6 text-center text-white">
        <div class="max-w-sm space-y-3">
            <div
                v-if="status"
                class="mx-auto h-10 w-10 animate-spin rounded-full border-[3px] border-sky-400 border-t-transparent"
            />
            <p v-if="status" class="text-sm text-slate-200">{{ status }}</p>
            <p v-if="error" class="text-sm text-rose-300">{{ error }}</p>
            <p v-if="!botConfigured" class="text-xs text-amber-300">
                Бот менеджеров не настроен на сервере.
            </p>
        </div>
    </div>
</template>
