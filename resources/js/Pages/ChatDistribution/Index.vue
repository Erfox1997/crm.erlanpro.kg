<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    modes: {
        type: Array,
        default: () => [],
    },
    eligibleAgents: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Распределение чата',
    },
});

const form = useForm({
    mode: props.mode,
});

function submit() {
    form.put(route('chat-distribution.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <div class="bg-slate-100 py-8 sm:py-10">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="mb-6">
                    <h1 class="text-xl font-semibold text-slate-900">Распределение чата</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Выберите, как новые входящие диалоги будут попадать к сотрудникам.
                    </p>
                </div>

                <form
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
                    @submit.prevent="submit"
                >
                    <div class="space-y-3 p-5 sm:p-6">
                        <label
                            v-for="option in modes"
                            :key="option.value"
                            class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                            :class="
                                form.mode === option.value
                                    ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-200'
                                    : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                            "
                        >
                            <input
                                v-model="form.mode"
                                type="radio"
                                class="mt-1 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                :value="option.value"
                                name="mode"
                            >
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ option.label }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ option.description }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                        <div class="mb-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Сотрудники с доступом к Месенджеру
                            </p>
                            <p
                                v-if="eligibleAgents.length === 0"
                                class="mt-2 text-sm text-amber-700"
                            >
                                Пока нет сотрудников с доступом к Месенджеру. Создайте должности с этим правом и назначьте сотрудников.
                            </p>
                            <ul v-else class="mt-2 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
                                <li
                                    v-for="agent in eligibleAgents"
                                    :key="agent.id"
                                    class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900">{{ agent.name }}</p>
                                        <p class="truncate text-slate-500">{{ agent.email }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs text-slate-400">
                                        {{ agent.position_name || 'Без должности' }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing || form.mode === mode">
                                {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
