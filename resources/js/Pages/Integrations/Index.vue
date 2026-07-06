<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IntegrationProviderIcon from '@/Components/IntegrationProviderIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    integrations: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Интеграции',
    },
    wappiWebhookUrl: {
        type: String,
        default: '',
    },
});

const page = usePage();
const showManualToken = reactive({
    instagram: false,
    facebook: false,
});

const tokenInputs = reactive(
    Object.fromEntries(
        props.integrations.map((item) => [item.provider, '']),
    ),
);

const profileIdInputs = reactive(
    Object.fromEntries(
        props.integrations.map((item) => [
            item.provider,
            item.provider === 'wappi' ? (item.profile_id ?? '') : '',
        ]),
    ),
);

const forms = reactive(
    Object.fromEntries(
        props.integrations.map((item) => [
            item.provider,
            useForm(
                item.provider === 'wappi'
                    ? { api_token: '', profile_id: '' }
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
};

function saveToken(provider) {
    const form = forms[provider];
    form.api_token = tokenInputs[provider];
    form.put(route('integrations.update', provider), {
        preserveScroll: true,
        onSuccess: () => {
            tokenInputs[provider] = '';
            form.reset('api_token');
        },
    });
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
    if (!confirm('Отключить интеграцию?')) {
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
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ pageTitle }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="grid gap-6">
                    <section
                        v-for="item in integrations"
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
                                        Подключено
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                                    >
                                        Не подключено
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
                                    {{ item.has_token ? 'Переподключить' : 'Подключить' }}
                                </a>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    Отключить
                                </SecondaryButton>
                            </div>

                            <button
                                type="button"
                                class="text-xs text-slate-500 hover:text-slate-700"
                                @click="showManualToken[item.provider] = !showManualToken[item.provider]"
                            >
                                {{ showManualToken[item.provider] ? 'Скрыть' : 'Токен вручную' }}
                            </button>

                            <form
                                v-if="showManualToken[item.provider]"
                                class="space-y-3 border-t border-slate-200 pt-3"
                                @submit.prevent="saveToken(item.provider)"
                            >
                                <div>
                                    <InputLabel
                                        :for="'token_' + item.provider"
                                        value="Page token"
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
                                    Сохранить
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
                                    value="Токен API"
                                />
                                <TextInput
                                    id="wappi_api_token"
                                    v-model="tokenInputs.wappi"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? 'Новый токен API'
                                            : 'Токен API из личного кабинета Wappi'
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
                                    value="ID профиля"
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
                                    Webhook (настраивается автоматически при сохранении)
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
                                    Сохранить
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    Отключить
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
                                    value="API токен"
                                />
                                <TextInput
                                    :id="'token_' + item.provider"
                                    v-model="tokenInputs[item.provider]"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="
                                        item.has_token
                                            ? 'Новый токен'
                                            : 'API токен'
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
                                    Сохранить
                                </PrimaryButton>
                                <SecondaryButton
                                    v-if="item.has_token"
                                    type="button"
                                    @click="disconnect(item.provider)"
                                >
                                    Отключить
                                </SecondaryButton>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
