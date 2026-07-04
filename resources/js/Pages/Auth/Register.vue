<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    company_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms_accepted: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Регистрация" />

        <div class="mb-8">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Регистрация компании
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                Укажите название организации и свои данные — появится отдельное
                рабочее пространство CRM
            </p>
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="company_name" value="Название компании" />

                <TextInput
                    id="company_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.company_name"
                    required
                    autofocus
                    autocomplete="organization"
                />

                <InputError class="mt-2" :message="form.errors.company_name" />
            </div>

            <div class="mt-5">
                <InputLabel for="name" value="Ваше имя" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-5">
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

            <div class="mt-5">
                <InputLabel for="password" value="Пароль" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-5">
                <InputLabel
                    for="password_confirmation"
                    value="Подтверждение пароля"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-6">
                <label class="flex items-start gap-3">
                    <input
                        id="terms_accepted"
                        v-model="form.terms_accepted"
                        type="checkbox"
                        class="mt-1 rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        required
                    />
                    <span class="text-sm leading-relaxed text-slate-600">
                        Я принимаю
                        <Link
                            :href="route('terms')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-indigo-600 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-500"
                        >
                            Пользовательское соглашение
                        </Link>
                        и
                        <Link
                            :href="route('privacy')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-indigo-600 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-500"
                        >
                            Политику конфиденциальности
                        </Link>
                    </span>
                </label>

                <InputError class="mt-2" :message="form.errors.terms_accepted" />
            </div>

            <div class="mt-8 flex flex-col gap-4">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                    :disabled="form.processing || !form.terms_accepted"
                >
                    Зарегистрироваться
                </button>

                <p class="text-center text-sm text-slate-600">
                    Уже есть аккаунт?
                    <Link
                        :href="route('login')"
                        class="font-semibold text-indigo-600 hover:text-indigo-500"
                    >
                        Войти
                    </Link>
                </p>
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
