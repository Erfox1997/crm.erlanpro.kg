<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IntegrationProviderIcon from '@/Components/IntegrationProviderIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    integrations: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: null,
    },
    wappiWebhookUrl: {
        type: String,
        default: '',
    },
    chatGptModels: {
        type: Array,
        default: () => [
            'gpt-4.1-mini',
            'gpt-4.1',
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4-turbo',
            'o4-mini',
            'gpt-3.5-turbo',
        ],
    },
    loadError: {
        type: String,
        default: null,
    },
});

const title = computed(() => props.pageTitle || t('integrations.title'));

const page = usePage();
const showManualToken = reactive({
    instagram: false,
    facebook: false,
});

const tokenInputs = reactive(
    Object.fromEntries(
        (props.integrations ?? []).map((item) => [item.provider, '']),
    ),
);

const profileIdInputs = reactive(
    Object.fromEntries(
        (props.integrations ?? []).map((item) => [
            item.provider,
            item.provider === 'wappi' ? (item.profile_id ?? '') : '',
        ]),
    ),
);

const chatGptModelInput = reactive({
    chatgpt: (props.integrations ?? []).find((item) => item.provider === 'chatgpt')?.model
        || props.chatGptModels?.[0]
        || 'gpt-4.1-mini',
});

const shopUrlInputs = reactive(
    Object.fromEntries(
        (props.integrations ?? []).map((item) => [
            item.provider,
            item.provider === 'shop' ? (item.shop_url ?? '') : '',
        ]),
    ),
);

const forms = reactive(
    Object.fromEntries(
        (props.integrations ?? []).map((item) => [
            item.provider,
            useForm(
                item.provider === 'wappi'
                    ? { api_token: '', profile_id: '' }
                    : item.provider === 'chatgpt'
                        ? { api_token: '', model: item.model || '' }
                        : item.provider === 'shop'
                            ? { api_token: '', shop_url: item.shop_url || '' }
                            : { api_token: '' },
            ),
        ]),
    ),
);

const providerAccent = {
    wappi: 'border-emerald-200 bg-emerald-50/40',
    instagram: 'border-pink-200 bg-pink-50/40',
    telegram: 'border-sky-200 bg-sky-50/40',
    facebook: 'border-blue-200 bg-blue-50/40',
    chatgpt: 'border-teal-200 bg-teal-50/40',
    shop: 'border-amber-200 bg-amber-50/40',
};

function saveToken(provider) {
    const form = forms[provider];
    form.api_token = tokenInputs[provider];
    if (provider === 'chatgpt') {
        form.model = chatGptModelInput.chatgpt;
    }
    form.put(route('integrations.update', provider), {
        preserveScroll: true,
        onSuccess: () => {
            tokenInputs[provider] = '';
            form.reset('api_token');
        },
    });
}

function chatGptCanSave(item) {
    const form = forms.chatgpt;

    return (
        !form.processing &&
        !!chatGptModelInput.chatgpt?.trim() &&
        (!!tokenInputs.chatgpt?.trim() || item.has_token)
    );
}

function saveWappi() {
    const form = forms.wappi;
    form.api_token = tokenInputs.wappi;
    form.profile_id = profileIdInputs.wappi;
    form.put(route('integrations.update', 'wappi'), {
        preserveScroll: true,
        onSuccess: () => {
            tokenInputs.wappi = '';
            form.reset('api_token');
        },
    });
}

function disconnect(provider) {
    if (!confirm(t('integrations.confirmDisconnect'))) {
        return;
    }
    router.delete(route('integrations.destroy', provider), {
        preserveScroll: true,
    });
}

function accountLabel(item) {
    if (!item.account) {
        return null;
    }

    if (item.account.username) {
        return `@${item.account.username}`;
    }

    if (item.account.page_name) {
        return item.account.page_name;
    }

    if (item.account.profile_id) {
        return item.account.name
            ? `${item.account.name} · ${item.account.profile_id}`
            : item.account.profile_id;
    }

    return item.account.name;
}

function isMetaProvider(provider) {
    return provider === 'instagram' || provider === 'facebook';
}

function isWappiProvider(provider) {
    return provider === 'wappi';
}

function isTelegramProvider(provider) {
    return provider === 'telegram';
}

function isChatGptProvider(provider) {
    return provider === 'chatgpt';
}

function isShopProvider(provider) {
    return provider === 'shop';
}

function saveShop() {
    const form = forms.shop;
    form.api_token = tokenInputs.shop;
    form.shop_url = shopUrlInputs.shop;
    form.put(route('integrations.update', 'shop'), {
        preserveScroll: true,
        onSuccess: () => {
            tokenInputs.shop = '';
            form.reset('api_token');
        },
    });
}

function shopCanSave() {
    const form = forms.shop;

    return (
        !form.processing &&
        shopUrlInputs.shop?.trim() &&
        tokenInputs.shop?.trim()
    );
}

function wappiCanSave() {
    const form = forms.wappi;

    return (
        !form.processing &&
        tokenInputs.wappi?.trim() &&
        profileIdInputs.wappi?.trim()
    );
}
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ title }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="loadError"
                    class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ loadError }}
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="grid gap-6">
                    <section
                        v-for="item in (integrations ?? [])"
                        :key="item.provider"
                        class="rounded-xl border bg-white p-6 shadow-sm"
                        :class="providerAccent[item.provider]"
                    >
                        <div class="flex min-w-0 gap-4">
                            <IntegrationProviderIcon
                                :provider="item.provider"
                            />
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <h3
                                        class="text-lg font-semibold text-slate-900"
                                    >
                                        {{ item.name }}
                                    </h3>
                                    <span
                                        v-if="item.has_token"
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                    >
                                        {{ t('integrations.connected') }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                                    >
                                        {{ t('integrations.disconnected') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ item.description }}
                                </p>
                                <p
                                    v-if="item.has_token && accountLabel(item)"
                                    class="mt-2 text-sm font-medium text-slate-800"
                                >
                                    {{ accountLabel(item) }}
                                </p>
                            </div>
                        </div>

                        <InputError
                            v-if="isMetaProvider(item.provider)"
                            class="mt-4"
                            :message="page.props.errors?.[item.provider]"
                        />

                        <div
                            v-if="isMetaProvider(item.provider)"
                            class="mt-5 space-y-3"
                        >
                            <div class="flex flex-wrap gap-2">
                                <a
                                    :href="item.oauth_url"
                                    class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-500"
                                >
                                    {{ item.has_token ? t('integrations.reconnect') : t('integrations.connect') }}
                                </a>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    {{ t('integrations.disconnect') }}
                                </SecondaryButton>
                            </div>

                            <button
                                type="button"
                                class="text-xs text-slate-500 hover:text-slate-700"
                                @click="showManualToken[item.provider] = !showManualToken[item.provider]"
                            >
                                {{ showManualToken[item.provider] ? t('common.hide') : t('integrations.manualToken') }}
                            </button>

                            <form
                                v-if="showManualToken[item.provider]"
                                class="space-y-3 border-t border-slate-200 pt-3"
                                @submit.prevent="saveToken(item.provider)"
                            >
                                <div>
                                    <InputLabel
                                        :for="'token_' + item.provider"
                                        :value="t('integrations.token')"
                                    />
                                    <TextInput
                                        :id="'token_' + item.provider"
                                        v-model="tokenInputs[item.provider]"
                                        type="password"
                                        class="mt-1 block w-full font-mono text-sm"
                                        placeholder="EAA..."
                                        autocomplete="off"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="forms[item.provider].errors.api_token"
                                    />
                                </div>
                                <PrimaryButton
                                    type="submit"
                                    :disabled="
                                        forms[item.provider].processing ||
                                        !tokenInputs[item.provider]?.trim()
                                    "
                                >
                                    {{ t('common.save') }}
                                </PrimaryButton>
                            </form>
                        </div>

                        <form
                            v-else-if="isWappiProvider(item.provider)"
                            class="mt-5 space-y-4"
                            @submit.prevent="saveWappi()"
                        >
                            <div>
                                <InputLabel
                                    for="wappi_api_token"
                                    :value="t('integrations.apiToken')"
                                />
                                <TextInput
                                    id="wappi_api_token"
                                    v-model="tokenInputs.wappi"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? t('integrations.newApiToken')
                                            : t('integrations.wappiTokenHint')
                                    "
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="forms.wappi.errors.api_token"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="wappi_profile_id"
                                    :value="t('integrations.profileId')"
                                />
                                <TextInput
                                    id="wappi_profile_id"
                                    v-model="profileIdInputs.wappi"
                                    type="text"
                                    class="mt-1 block w-full font-mono text-sm"
                                    placeholder="497962cd-95e5"
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="forms.wappi.errors.profile_id"
                                />
                            </div>

                            <div
                                v-if="wappiWebhookUrl"
                                class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2.5 text-xs text-emerald-900"
                            >
                                <p class="font-medium">
                                    {{ t('integrations.webhookAuto') }}
                                </p>
                                <p class="mt-1 break-all font-mono text-[11px] text-emerald-800">
                                    {{ wappiWebhookUrl }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="!wappiCanSave()"
                                >
                                    {{ t('common.save') }}
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    {{ t('integrations.disconnect') }}
                                </SecondaryButton>
                            </div>
                        </form>

                        <form
                            v-else-if="isTelegramProvider(item.provider)"
                            class="mt-5 space-y-4"
                            @submit.prevent="saveToken(item.provider)"
                        >
                            <div>
                                <InputLabel
                                    for="telegram_api_token"
                                    :value="t('integrations.botToken')"
                                />
                                <TextInput
                                    id="telegram_api_token"
                                    v-model="tokenInputs.telegram"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? t('integrations.newBotToken')
                                            : t('integrations.botFatherHint')
                                    "
                                    autocomplete="off"
                                />
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ t('integrations.telegramHint') }}
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="forms.telegram.errors.api_token"
                                />
                            </div>

                            <div
                                v-if="item.webhook_url"
                                class="rounded-lg border border-sky-200 bg-sky-50/60 px-3 py-2.5 text-xs text-sky-900"
                            >
                                <p class="font-medium">
                                    {{ t('integrations.webhookAuto') }}
                                </p>
                                <p class="mt-1 break-all font-mono text-[11px] text-sky-800">
                                    {{ item.webhook_url }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="
                                        forms.telegram.processing ||
                                        !tokenInputs.telegram?.trim()
                                    "
                                >
                                    {{ t('common.save') }}
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    {{ t('integrations.disconnect') }}
                                </SecondaryButton>
                            </div>
                        </form>

                        <form
                            v-else-if="isShopProvider(item.provider)"
                            class="mt-5 space-y-4"
                            @submit.prevent="saveShop()"
                        >
                            <div>
                                <InputLabel
                                    for="shop_url"
                                    :value="t('integrations.shopUrl')"
                                />
                                <TextInput
                                    id="shop_url"
                                    v-model="shopUrlInputs.shop"
                                    type="url"
                                    class="mt-1 block w-full font-mono text-sm"
                                    placeholder="https://mag.ulan.kg"
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="forms.shop.errors.shop_url"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="shop_api_token"
                                    :value="t('integrations.apiKey')"
                                />
                                <TextInput
                                    id="shop_api_token"
                                    v-model="tokenInputs.shop"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? t('integrations.newShopKey')
                                            : t('integrations.shopKeyPh')
                                    "
                                    autocomplete="off"
                                />
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ t('integrations.shopHint') }}
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="forms.shop.errors.api_token"
                                />
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="!shopCanSave()"
                                >
                                    {{ t('common.save') }}
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    {{ t('integrations.disconnect') }}
                                </SecondaryButton>
                            </div>
                        </form>

                        <form
                            v-else-if="isChatGptProvider(item.provider)"
                            class="mt-5 space-y-4"
                            @submit.prevent="saveToken(item.provider)"
                        >
                            <div>
                                <InputLabel
                                    for="chatgpt_api_token"
                                    :value="t('integrations.openaiKey')"
                                />
                                <TextInput
                                    id="chatgpt_api_token"
                                    v-model="tokenInputs.chatgpt"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? t('integrations.openaiLeaveEmpty')
                                            : 'sk-...'
                                    "
                                    autocomplete="off"
                                />
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ t('integrations.openaiFrom') }}
                                    <a
                                        href="https://platform.openai.com/api-keys"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-teal-700 underline"
                                    >platform.openai.com</a>.
                                    {{ t('integrations.openaiMessenger') }}
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="forms.chatgpt.errors.api_token"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="chatgpt_model"
                                    :value="t('integrations.model')"
                                />
                                <select
                                    id="chatgpt_model"
                                    v-model="chatGptModelInput.chatgpt"
                                    class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option
                                        v-for="model in chatGptModels"
                                        :key="model"
                                        :value="model"
                                    >
                                        {{ model }}
                                    </option>
                                    <option
                                        v-if="item.model && !chatGptModels.includes(item.model)"
                                        :value="item.model"
                                    >
                                        {{ item.model }}
                                    </option>
                                </select>
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ t('integrations.modelHint') }}
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="forms.chatgpt.errors.model"
                                />
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="!chatGptCanSave(item)"
                                >
                                    {{ t('common.save') }}
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    {{ t('integrations.disconnect') }}
                                </SecondaryButton>
                            </div>
                        </form>

                        <form
                            v-else-if="!isMetaProvider(item.provider)"
                            class="mt-5 space-y-4"
                            @submit.prevent="saveToken(item.provider)"
                        >
                            <div>
                                <InputLabel
                                    :for="'token_' + item.provider"
                                    :value="t('integrations.apiTokenGeneric')"
                                />
                                <TextInput
                                    :id="'token_' + item.provider"
                                    v-model="tokenInputs[item.provider]"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? t('integrations.newToken')
                                            : t('integrations.apiTokenGeneric')
                                    "
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="
                                        forms[item.provider].errors.api_token
                                    "
                                />
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="
                                        forms[item.provider].processing ||
                                        !tokenInputs[item.provider]?.trim()
                                    "
                                >
                                    {{ t('common.save') }}
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    {{ t('integrations.disconnect') }}
                                </SecondaryButton>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
