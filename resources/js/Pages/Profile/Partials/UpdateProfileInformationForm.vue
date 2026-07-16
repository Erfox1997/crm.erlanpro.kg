<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    managerBotUsername: {
        type: String,
        default: '',
    },
    telegramLinked: {
        type: Boolean,
        default: false,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    telegram_username: user.telegram_username || '',
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Профиль
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Имя, email и Telegram для входа в Mini App.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Имя" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="telegram_username" value="Telegram username" />

                <TextInput
                    id="telegram_username"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.telegram_username"
                    placeholder="ivan_manager"
                    autocomplete="off"
                />

                <p class="mt-1 text-xs text-slate-500">
                    Без @.
                    <template v-if="managerBotUsername">
                        Затем откройте
                        <a
                            :href="`https://t.me/${managerBotUsername}`"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-sky-700 underline"
                        >@{{ managerBotUsername }}</a>
                        и нажмите /start.
                    </template>
                    <template v-else>
                        Затем нажмите /start в боте менеджеров.
                    </template>
                    <span
                        class="ml-1 font-medium"
                        :class="telegramLinked ? 'text-emerald-600' : 'text-amber-600'"
                    >
                        {{ telegramLinked ? '✓ привязан' : 'ожидает /start' }}
                    </span>
                </p>

                <InputError class="mt-2" :message="form.errors.telegram_username" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Email не подтверждён.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Отправить письмо ещё раз
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Ссылка для подтверждения отправлена на email.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Сохранено.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
