<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IntegrationProviderIcon from '@/Components/IntegrationProviderIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    integrations: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Интеграции',
    },
});

const page = usePage();
const showManualInstagramToken = ref(false);

const tokenInputs = reactive(
    Object.fromEntries(
        props.integrations.map((item) => [item.provider, '']),
    ),
);

const forms = reactive(
    Object.fromEntries(
        props.integrations.map((item) => [
            item.provider,
            useForm({ api_token: '' }),
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

function disconnect(provider) {
    if (!confirm('Отключить интеграцию и удалить сохранённый токен?')) {
        return;
    }
    router.delete(route('integrations.destroy', provider), {
        preserveScroll: true,
    });
}

function instagramAccountLabel(item) {
    if (!item.account) {
        return null;
    }

    if (item.account.username) {
        return `@${item.account.username}`;
    }

    return item.account.name;
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    {{ pageTitle }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Подключите мессенджеры и соцсети к вашей компании.
                </p>
            </div>
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
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="flex min-w-0 gap-4">
                                <IntegrationProviderIcon
                                    :provider="item.provider"
                                />
                                <div class="min-w-0">
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
                                    <p class="mt-2 text-sm text-slate-600">
                                        {{ item.description }}
                                    </p>
                                    <p
                                        v-if="item.provider === 'instagram' && item.has_token && instagramAccountLabel(item)"
                                        class="mt-2 text-sm font-medium text-slate-800"
                                    >
                                        Аккаунт: {{ instagramAccountLabel(item) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <InputError
                            v-if="item.provider === 'instagram'"
                            class="mt-4"
                            :message="page.props.errors?.instagram"
                        />

                        <div
                            v-if="item.provider === 'instagram'"
                            class="mt-6 space-y-4"
                        >
                            <div class="rounded-lg border border-pink-100 bg-white/80 p-4">
                                <p class="text-sm font-medium text-slate-900">
                                    Подключение через Meta OAuth
                                </p>
                                <p class="mt-2 text-xs leading-relaxed text-slate-500">
                                    Нажмите кнопку ниже — откроется окно Meta.
                                    Войдите в аккаунт, к которому привязан
                                    Instagram erlanpro.kg, и подтвердите доступ.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a
                                        :href="item.oauth_url"
                                        class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        {{ item.has_token ? 'Переподключить через Meta' : 'Подключить через Meta' }}
                                    </a>
                                    <SecondaryButton
                                        v-if="item.has_token"
                                        type="button"
                                        @click="disconnect(item.provider)"
                                    >
                                        Отключить
                                    </SecondaryButton>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                                <p class="font-medium text-slate-800">
                                    Webhook для Meta (шаг 3 в кабинете разработчика)
                                </p>
                                <p class="mt-2">
                                    <span class="font-medium">URL:</span>
                                    <code class="ml-1 break-all rounded bg-white px-1 py-0.5">{{ item.webhook_url }}</code>
                                </p>
                                <p class="mt-2">
                                    <span class="font-medium">Verify Token:</span>
                                    значение из
                                    <code class="rounded bg-white px-1">META_WEBHOOK_VERIFY_TOKEN</code>
                                    в .env (например
                                    <code class="rounded bg-white px-1">crm-ulan-meta-webhook</code>)
                                </p>
                                <p class="mt-2">
                                    <span class="font-medium">OAuth Redirect URI</span>
                                    (шаг 4 — Instagram Login):
                                    <code class="ml-1 break-all rounded bg-white px-1 py-0.5">{{ item.oauth_callback_url }}</code>
                                </p>
                            </div>

                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                @click="showManualInstagramToken = !showManualInstagramToken"
                            >
                                {{ showManualInstagramToken ? 'Скрыть ручной ввод токена' : 'Ввести токен вручную (запасной вариант)' }}
                            </button>

                            <form
                                v-if="showManualInstagramToken"
                                class="space-y-4 border-t border-slate-200 pt-4"
                                @submit.prevent="saveToken(item.provider)"
                            >
                                <div>
                                    <InputLabel
                                        :for="'token_' + item.provider"
                                        value="Маркер доступа Instagram"
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
                                    Сохранить токен вручную
                                </PrimaryButton>
                            </form>
                        </div>

                        <form
                            v-else
                            class="mt-6 space-y-4"
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
                                            ? 'Введите новый токен для замены'
                                            : 'Вставьте API токен'
                                    "
                                    autocomplete="off"
                                />
                                <p
                                    v-if="item.has_token"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Токен сохранён. Для смены введите новый —
                                    старый будет заменён.
                                </p>
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
                                    Сохранить токен
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
