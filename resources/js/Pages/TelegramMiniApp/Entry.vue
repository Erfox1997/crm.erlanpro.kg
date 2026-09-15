<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    botConfigured: { type: Boolean, default: false },
    botUsername: { type: String, default: '' },
    conversationId: { type: Number, default: null },
});

const { t } = useI18n();
const status = ref('');
const error = ref('');

function resolveConversationId() {
    if (props.conversationId) {
        return props.conversationId;
    }

    const params = new URLSearchParams(window.location.search);
    const fromQuery = Number(params.get('conversation') || 0);
    if (fromQuery > 0) {
        return fromQuery;
    }

    const tg = window.Telegram?.WebApp;
    const startParam = String(tg?.initDataUnsafe?.start_param || '');
    const match = startParam.match(/^(?:c|conversation)[_-]?(\d+)$/i);
    if (match) {
        return Number(match[1]);
    }

    return null;
}

onMounted(async () => {
    status.value = t('miniApp.opening');

    const tg = window.Telegram?.WebApp;
    if (!tg) {
        error.value = t('miniApp.openViaBot');
        status.value = '';
        return;
    }

    tg.ready();
    tg.expand();
    try {
        tg.disableVerticalSwipes?.();
    } catch {
        // older clients
    }

    const initData = tg.initData || '';
    if (!initData) {
        error.value = t('miniApp.noInitData');
        status.value = '';
        return;
    }

    const conversation = resolveConversationId();

    try {
        const { data } = await window.axios.post(route('tma.auth'), {
            init_data: initData,
            conversation,
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

        error.value = t('miniApp.loginFailed');
        status.value = '';
    } catch (e) {
        error.value = e?.response?.data?.message
            || t('miniApp.accessDenied');
        status.value = '';
    }
});
</script>

<template>
    <Head :title="t('miniApp.title')" />

    <div class="flex min-h-screen items-center justify-center bg-[#0f172a] px-6 text-center text-white">
        <div class="max-w-sm space-y-3">
            <div
                v-if="status"
                class="mx-auto h-10 w-10 animate-spin rounded-full border-[3px] border-sky-400 border-t-transparent"
            />
            <p v-if="status" class="text-sm text-slate-200">{{ status }}</p>
            <p v-if="error" class="text-sm text-rose-300">{{ error }}</p>
            <p v-if="!botConfigured" class="text-xs text-amber-300">
                {{ t('miniApp.botNotConfigured') }}
            </p>
        </div>
    </div>
</template>
