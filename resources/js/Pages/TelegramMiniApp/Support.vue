<script setup>
import SupportProgrammerPanel from '@/Pages/TelegramMiniApp/SupportProgrammerPanel.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    botConfigured: { type: Boolean, default: false },
    botUsername: { type: String, default: '' },
});

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const initData = ref('');
const isProgrammer = ref(false);

onMounted(async () => {
    const tg = window.Telegram?.WebApp;
    if (!tg) {
        error.value = t('supportMiniApp.openViaBot');
        loading.value = false;
        return;
    }

    tg.ready();
    tg.expand();
    initData.value = tg.initData || '';

    if (!initData.value) {
        error.value = t('supportMiniApp.noInitData');
        loading.value = false;
        return;
    }

    try {
        const { data } = await window.axios.post(route('tma.support.bootstrap'), {
            init_data: initData.value,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        isProgrammer.value = Boolean(data.is_programmer);
    } catch (e) {
        error.value = e?.response?.data?.message || t('supportMiniApp.loadFailed');
    } finally {
        loading.value = false;
    }
});

function closeApp() {
    window.Telegram?.WebApp?.close?.();
}
</script>

<template>
    <Head :title="isProgrammer ? t('supportMiniApp.programmerTitle') : t('supportMiniApp.clientClosedTitle')" />

    <div class="min-h-screen bg-slate-950 px-4 py-6 text-white">
        <div class="mx-auto max-w-md space-y-5">
            <template v-if="!isProgrammer">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-400">
                        ErlanPro
                    </p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight">
                        {{ t('supportMiniApp.clientClosedTitle') }}
                    </h1>
                </div>
            </template>

            <div
                v-if="loading"
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 text-center text-sm text-slate-300"
            >
                {{ t('supportMiniApp.loading') }}
            </div>

            <SupportProgrammerPanel
                v-else-if="isProgrammer"
                :init-data="initData"
            />

            <div
                v-else
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5"
            >
                <p v-if="error" class="mb-3 text-sm text-rose-300">{{ error }}</p>
                <p class="text-sm text-slate-200">
                    {{ t('supportMiniApp.clientClosedBody') }}
                </p>
                <button
                    type="button"
                    class="mt-4 w-full rounded-xl bg-sky-500 px-4 py-3 text-sm font-semibold text-slate-950"
                    @click="closeApp"
                >
                    {{ t('supportMiniApp.close') }}
                </button>
            </div>
        </div>
    </div>
</template>
