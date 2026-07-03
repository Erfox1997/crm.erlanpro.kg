<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IntegrationProviderIcon from '@/Components/IntegrationProviderIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
});

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
                    Подключите мессенджеры и соцсети через API-токен вашей
                    компании.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
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
                                </div>
                            </div>
                        </div>

                        <form
                            class="mt-6 space-y-4"
                            @submit.prevent="saveToken(item.provider)"
                        >
                            <div>
                                <InputLabel
                                    :for="'token_' + item.provider"
                                    :value="
                                        item.provider === 'instagram'
                                            ? 'Маркер доступа Instagram'
                                            : 'API токен'
                                    "
                                />
                                <p
                                    v-if="item.provider === 'instagram'"
                                    class="mb-2 text-xs leading-relaxed text-slate-500"
                                >
                                    В
                                    <a
                                        href="https://developers.facebook.com/"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-indigo-600 hover:underline"
                                    >Meta for Developers</a>
                                    нажмите «Сгенерировать маркер» у аккаунта
                                    erlanpro.kg, скопируйте строку и вставьте
                                    сюда. ID и секрет приложения уже в
                                    <code class="rounded bg-slate-100 px-1">.env</code>.
                                </p>
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
