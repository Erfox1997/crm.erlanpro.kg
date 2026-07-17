<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChannelIcon from '@/Components/Messenger/ChannelIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    filter: { type: String, default: 'open' },
    pageTitle: { type: String, default: 'Задачи' },
});

const filters = [
    { value: 'open', label: 'Открытые' },
    { value: 'done', label: 'Выполненные' },
    { value: 'all', label: 'Все' },
];

function setFilter(value) {
    router.get(route('tasks.index'), { filter: value }, {
        preserveState: true,
        replace: true,
    });
}

function completeTask(task) {
    router.post(route('tasks.complete', task.id), {}, { preserveScroll: true });
}

function reopenTask(task) {
    router.post(route('tasks.reopen', task.id), {}, { preserveScroll: true });
}

function destroyTask(task) {
    if (! confirm('Удалить задачу?')) {
        return;
    }

    router.delete(route('tasks.destroy', task.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="item in filters"
                        :key="item.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition"
                        :class="filter === item.value
                            ? 'bg-indigo-600 text-white'
                            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                        @click="setFilter(item.value)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                    <ul class="divide-y divide-slate-100">
                        <li
                            v-for="task in tasks"
                            :key="task.id"
                            class="px-4 py-4 sm:px-5"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="task.completed
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : task.overdue
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ task.completed ? 'Выполнено' : task.overdue ? 'Просрочено' : 'К выполнению' }}
                                        </span>
                                        <span class="text-sm font-semibold text-slate-900">
                                            {{ task.due_on_label }}
                                        </span>
                                    </div>

                                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800">
                                        {{ task.note }}
                                    </p>

                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1">
                                            <ChannelIcon
                                                v-if="task.channel"
                                                :channel="task.channel"
                                            />
                                            <Link
                                                v-if="task.conversation_id"
                                                :href="route('messenger.index', { conversation: task.conversation_id })"
                                                class="font-medium text-indigo-700 hover:underline"
                                            >
                                                {{ task.client_label }}
                                            </Link>
                                            <span v-else>{{ task.client_label }}</span>
                                        </span>
                                        <span>·</span>
                                        <span>{{ task.author || '—' }}</span>
                                        <span>·</span>
                                        <span>{{ task.created_at }}</span>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <PrimaryButton
                                        v-if="!task.completed"
                                        type="button"
                                        class="!px-3 !py-1.5 !text-xs"
                                        @click="completeTask(task)"
                                    >
                                        Выполнено
                                    </PrimaryButton>
                                    <SecondaryButton
                                        v-else
                                        type="button"
                                        class="!px-3 !py-1.5 !text-xs"
                                        @click="reopenTask(task)"
                                    >
                                        Открыть снова
                                    </SecondaryButton>
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                        @click="destroyTask(task)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </li>

                        <li
                            v-if="tasks.length === 0"
                            class="px-4 py-12 text-center text-sm text-slate-500"
                        >
                            Задач пока нет. Создайте задачу из чата в мессенджере.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
