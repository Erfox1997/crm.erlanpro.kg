<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Вход" />

        <div class="mb-8">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Вход в аккаунт
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                Введите email и пароль, чтобы открыть CRM
            </p>
        </div>

        <div
            v-if="status"
            class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-5">
                <InputLabel for="password" value="Пароль" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-5 flex items-center">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-slate-600"
                        >Запомнить меня</span
                    >
                </label>
            </div>

            <div class="mt-8 flex flex-col gap-4">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                    :disabled="form.processing"
                >
                    Войти
                </button>

                <div
                    class="flex flex-col gap-3 text-center text-sm sm:flex-row sm:justify-between sm:text-left"
                >
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-slate-600 underline decoration-slate-300 underline-offset-2 transition hover:text-indigo-600"
                    >
                        Забыли пароль?
                    </Link>
                    <Link
                        :href="route('register')"
                        class="text-slate-600 underline decoration-slate-300 underline-offset-2 transition hover:text-indigo-600 sm:ml-auto"
                    >
                        Создать аккаунт
                    </Link>
                </div>
            </div>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            <Link
                href="/"
                class="font-medium text-indigo-600 hover:text-indigo-500"
            >
                ← На главную
            </Link>
        </p>
    </GuestLayout>
</template>
