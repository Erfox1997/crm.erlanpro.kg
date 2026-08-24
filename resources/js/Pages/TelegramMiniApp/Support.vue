<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    botConfigured: { type: Boolean, default: false },
    botUsername: { type: String, default: '' },
});

const { t } = useI18n();

const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const success = ref('');
const initData = ref('');
const application = ref(null);

const form = reactive({
    name: '',
    phone: '',
    company_name: '',
    message: '',
});

const status = computed(() => application.value?.status ?? null);
const canApply = computed(() => !status.value || status.value === 'rejected');

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

        application.value = data.application ?? null;
        if (data.user) {
            const fullName = [data.user.first_name, data.user.last_name]
                .filter(Boolean)
                .join(' ')
                .trim();
            if (!form.name && fullName) {
                form.name = fullName;
            }
        }

        if (application.value && application.value.status !== 'rejected') {
            form.name = application.value.name || form.name;
            form.phone = application.value.phone || '';
            form.company_name = application.value.company_name || '';
            form.message = application.value.message || '';
        }
    } catch (e) {
        error.value = e?.response?.data?.message || t('supportMiniApp.loadFailed');
    } finally {
        loading.value = false;
    }
});

async function submit() {
    error.value = '';
    success.value = '';
    submitting.value = true;

    try {
        const { data } = await window.axios.post(route('tma.support.apply'), {
            init_data: initData.value,
            name: form.name,
            phone: form.phone,
            company_name: form.company_name,
            message: form.message,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        application.value = data.application ?? { status: 'pending' };
        success.value = data.message || t('supportMiniApp.sent');

        const tg = window.Telegram?.WebApp;
        tg?.HapticFeedback?.notificationOccurred?.('success');
    } catch (e) {
        error.value = e?.response?.data?.message || t('supportMiniApp.sendFailed');
        if (e?.response?.data?.application) {
            application.value = e.response.data.application;
        }
    } finally {
        submitting.value = false;
    }
}

function closeApp() {
    window.Telegram?.WebApp?.close?.();
}
</script>

<template>
    <Head :title="t('supportMiniApp.title')" />

    <div class="min-h-screen bg-slate-950 px-4 py-6 text-white">
        <div class="mx-auto max-w-md space-y-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-400">
                    ErlanPro
                </p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight">
                    {{ t('supportMiniApp.title') }}
                </h1>
                <p class="mt-2 text-sm text-slate-300">
                    {{ t('supportMiniApp.subtitle') }}
                </p>
            </div>

            <div
                v-if="loading"
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 text-center text-sm text-slate-300"
            >
                {{ t('supportMiniApp.loading') }}
            </div>

            <template v-else>
                <div
                    v-if="status === 'accepted'"
                    class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5"
                >
                    <p class="text-base font-semibold text-emerald-300">
                        {{ t('supportMiniApp.acceptedTitle') }}
                    </p>
                    <p class="mt-2 text-sm text-emerald-100/90">
                        {{ t('supportMiniApp.acceptedBody') }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950"
                        @click="closeApp"
                    >
                        {{ t('supportMiniApp.close') }}
                    </button>
                </div>

                <div
                    v-else-if="status === 'pending'"
                    class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5"
                >
                    <p class="text-base font-semibold text-amber-300">
                        {{ t('supportMiniApp.pendingTitle') }}
                    </p>
                    <p class="mt-2 text-sm text-amber-50/90">
                        {{ t('supportMiniApp.pendingBody') }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950"
                        @click="closeApp"
                    >
                        {{ t('supportMiniApp.close') }}
                    </button>
                </div>

                <form
                    v-else
                    class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/80 p-5"
                    @submit.prevent="submit"
                >
                    <div
                        v-if="status === 'rejected'"
                        class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200"
                    >
                        {{ t('supportMiniApp.rejectedHint') }}
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">
                            {{ t('supportMiniApp.name') }}
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="120"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">
                            {{ t('supportMiniApp.phone') }}
                        </label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            maxlength="64"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                            :placeholder="t('supportMiniApp.phonePh')"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">
                            {{ t('supportMiniApp.company') }}
                        </label>
                        <input
                            v-model="form.company_name"
                            type="text"
                            maxlength="160"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">
                            {{ t('supportMiniApp.message') }}
                        </label>
                        <textarea
                            v-model="form.message"
                            required
                            maxlength="2000"
                            rows="5"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500"
                            :placeholder="t('supportMiniApp.messagePh')"
                        />
                    </div>

                    <p v-if="error" class="text-sm text-rose-300">{{ error }}</p>
                    <p v-if="success" class="text-sm text-emerald-300">{{ success }}</p>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-sky-500 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-50"
                        :disabled="submitting || !canApply"
                    >
                        {{ submitting ? t('supportMiniApp.sending') : t('supportMiniApp.submit') }}
                    </button>
                </form>
            </template>

            <p v-if="!botConfigured" class="text-center text-xs text-amber-300">
                {{ t('supportMiniApp.botNotConfigured') }}
            </p>
        </div>
    </div>
</template>
