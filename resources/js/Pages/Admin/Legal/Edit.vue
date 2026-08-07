<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    legal: {
        type: Object,
        required: true,
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const form = useForm({
    legal_name: props.legal.legal_name ?? '',
    pin: props.legal.pin ?? '',
    activity: props.legal.activity ?? '',
    address: props.legal.address ?? '',
    about: props.legal.about ?? '',
    contact_email: props.legal.contact_email ?? '',
    contact_phone: props.legal.contact_phone ?? '',
    site_url: props.legal.site_url ?? '',
});

function submit() {
    form.put(route('admin.legal.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('admin.legalRequisites')" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ t('admin.legalRequisites') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ t('admin.legal.pageText') }}
                    <Link
                        :href="route('legal')"
                        class="font-medium text-indigo-600 hover:text-indigo-500"
                        target="_blank"
                    >
                        /legal
                    </Link>
                    {{ t('admin.legal.hint') }}
                </p>
            </div>
        </template>

        <form
            class="max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel for="legal_name" :value="t('admin.legal.name')" />
                <TextInput
                    id="legal_name"
                    v-model="form.legal_name"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.legal_name" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel for="pin" :value="t('admin.legal.pin')" />
                    <TextInput
                        id="pin"
                        v-model="form.pin"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.pin" />
                </div>
                <div>
                    <InputLabel for="activity" :value="t('admin.legal.activity')" />
                    <TextInput
                        id="activity"
                        v-model="form.activity"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.activity" />
                </div>
            </div>

            <div>
                <InputLabel for="address" :value="t('admin.legal.address')" />
                <textarea
                    id="address"
                    v-model="form.address"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError class="mt-2" :message="form.errors.address" />
            </div>

            <div>
                <InputLabel for="about" :value="t('admin.legal.about')" />
                <textarea
                    id="about"
                    v-model="form.about"
                    rows="8"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p class="mt-2 text-sm text-slate-500">
                    {{ t('admin.legal.paragraphs') }}
                </p>
                <InputError class="mt-2" :message="form.errors.about" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel for="contact_email" :value="t('common.email')" />
                    <TextInput
                        id="contact_email"
                        v-model="form.contact_email"
                        type="email"
                        class="mt-1 block w-full"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.contact_email"
                    />
                </div>
                <div>
                    <InputLabel for="contact_phone" :value="t('common.phone')" />
                    <TextInput
                        id="contact_phone"
                        v-model="form.contact_phone"
                        class="mt-1 block w-full"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.contact_phone"
                    />
                </div>
            </div>

            <div>
                <InputLabel for="site_url" :value="t('admin.legal.site')" />
                <TextInput
                    id="site_url"
                    v-model="form.site_url"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.site_url" />
            </div>

            <PrimaryButton :disabled="form.processing">
                {{ t('common.save') }}
            </PrimaryButton>
        </form>
    </AdminLayout>
</template>
