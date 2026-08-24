<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    project: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
    pageTitle: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();
const replyOpen = reactive({});
const forms = reactive({});

props.messages.forEach((message) => {
    forms[message.id] = useForm({ body: '' });
});

function toggleReply(id) {
    replyOpen[id] = !replyOpen[id];
}

function sendReply(message) {
    forms[message.id].post(route('admin.support.messages.reply', message.id), {
        preserveScroll: true,
        onSuccess: () => {
            forms[message.id].reset('body');
            replyOpen[message.id] = false;
        },
    });
}

function complete(message) {
    if (!confirm('Отметить выполненным и уведомить клиента?')) return;
    router.post(route('admin.support.messages.complete', message.id), {}, { preserveScroll: true });
}

function removeMessage(message) {
    if (!confirm('Удалить сообщение без уведомления клиента?')) return;
    router.delete(route('admin.support.messages.destroy', message.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle || project.name" />
    <AdminLayout>
        <template #header>
            <div>
                <Link
                    :href="route('admin.support.inbox.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                >
                    ← Входящие
                </Link>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">
                    {{ project.name }}
                </h1>
            </div>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="space-y-4">
            <article
                v-for="message in messages"
                :key="message.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ message.client?.name || 'Клиент' }}
                            <span
                                v-if="message.client?.username"
                                class="text-sm font-normal text-slate-500"
                            >
                                @{{ message.client.username }}
                            </span>
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ message.client?.phone || '—' }} · {{ message.created_at }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-800">
                    {{ message.body }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <PrimaryButton type="button" @click="complete(message)">
                        ✅ Выполнено
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="toggleReply(message.id)">
                        💬 Ответить
                    </SecondaryButton>
                    <DangerButton type="button" @click="removeMessage(message)">
                        Удалить
                    </DangerButton>
                </div>

                <form
                    v-if="replyOpen[message.id]"
                    class="mt-4 space-y-3 border-t border-slate-100 pt-4"
                    @submit.prevent="sendReply(message)"
                >
                    <textarea
                        v-model="forms[message.id].body"
                        rows="3"
                        required
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Уточнение или ответ клиенту…"
                    />
                    <PrimaryButton
                        type="submit"
                        :disabled="forms[message.id].processing"
                    >
                        Отправить ответ
                    </PrimaryButton>
                </form>
            </article>

            <p
                v-if="messages.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500"
            >
                Нет открытых сообщений по этому проекту.
            </p>
        </div>
    </AdminLayout>
</template>
