<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    pipelines: {
        type: Array,
        default: () => [],
    },
    selectedPipelineId: {
        type: Number,
        default: null,
    },
    pipeline: {
        type: Object,
        default: null,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    clients: {
        type: Array,
        default: () => [],
    },
    linkablePipelines: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: '',
    },
});

const pipelineForm = useForm({ name: '' });
const editPipelineForm = useForm({ name: '' });
const editStageForm = useForm({ name: '' });
const tunnelForm = useForm({
    from_stage_id: '',
    to_stage_id: '',
});
const tunnelTargetPipelineId = ref('');
const dealForm = useForm({
    title: '',
    amount: '',
    client_id: '',
    pipeline_id: props.selectedPipelineId ?? '',
});
const stageForm = useForm({
    stages: [
        { name: '', color: '#94a3b8' },
        { name: '', color: '#94a3b8' },
    ],
});
const reorderForm = useForm({
    stage_ids: [],
});

const defaultStageRow = () => ({ name: '', color: '#94a3b8' });

const reorderMode = ref(false);
const orderedStages = ref([]);

const showCreateModal = ref(false);
const showEditPipelineModal = ref(false);
const showEditStageModal = ref(false);
const showDealModal = ref(false);
const showStageModal = ref(false);
const showDeleteStageModal = ref(false);
const editingStage = ref(null);
const deletingStage = ref(null);

const stageColorPresets = computed(() => [
    { value: '#94a3b8', label: t('funnels.colors.gray') },
    { value: '#3b82f6', label: t('funnels.colors.blue') },
    { value: '#22c55e', label: t('funnels.colors.green') },
    { value: '#ef4444', label: t('funnels.colors.red') },
    { value: '#f59e0b', label: t('funnels.colors.orange') },
    { value: '#8b5cf6', label: t('funnels.colors.purple') },
]);

const numberLocaleTag = computed(() => (locale.value === 'en' ? 'en-US' : 'ru-RU'));

const displayStages = computed(() =>
    reorderMode.value ? orderedStages.value : props.stages,
);

const tunnelTargetStages = computed(() => {
    if (!tunnelTargetPipelineId.value) {
        return [];
    }
    const pipeline = props.linkablePipelines.find(
        (p) => p.id === Number(tunnelTargetPipelineId.value),
    );

    return pipeline?.stages ?? [];
});

const hasLinkablePipelines = computed(
    () => props.linkablePipelines.length > 0,
);

const submitPipeline = () => {
    pipelineForm.post(route('pipelines.store'), {
        preserveScroll: true,
        onSuccess: () => {
            pipelineForm.reset('name');
            showCreateModal.value = false;
        },
    });
};

function openEditPipeline() {
    if (!props.pipeline) {
        return;
    }
    editPipelineForm.name = props.pipeline.name;
    editPipelineForm.clearErrors();
    showEditPipelineModal.value = true;
}

const submitEditPipeline = () => {
    if (!props.pipeline) {
        return;
    }
    editPipelineForm.patch(route('pipelines.update', props.pipeline.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditPipelineModal.value = false;
        },
    });
};

function openEditStage(stage) {
    editingStage.value = stage;
    editStageForm.name = stage.name;
    editStageForm.clearErrors();
    tunnelForm.clearErrors();
    tunnelForm.from_stage_id = stage.id;
    if (stage.tunnel) {
        tunnelTargetPipelineId.value = String(stage.tunnel.to_pipeline_id);
        tunnelForm.to_stage_id = String(stage.tunnel.to_stage_id);
    } else {
        tunnelTargetPipelineId.value = props.linkablePipelines[0]?.id
            ? String(props.linkablePipelines[0].id)
            : '';
        tunnelForm.to_stage_id = '';
    }
    showEditStageModal.value = true;
}

function onTunnelPipelineChange() {
    tunnelForm.to_stage_id = '';
}

const submitStageTunnel = () => {
    if (!editingStage.value || !tunnelForm.to_stage_id) {
        return;
    }
    tunnelForm.from_stage_id = editingStage.value.id;
    tunnelForm.post(route('stage-tunnels.store'), {
        preserveScroll: true,
        onSuccess: () => closeEditStageModal(),
    });
};

const removeStageTunnel = () => {
    const tunnel = editingStage.value?.tunnel;
    if (!tunnel?.id) {
        return;
    }
    router.delete(route('stage-tunnels.destroy', tunnel.id), {
        preserveScroll: true,
        onSuccess: () => closeEditStageModal(),
    });
};

function closeEditStageModal() {
    showEditStageModal.value = false;
    editingStage.value = null;
    editStageForm.reset();
    editStageForm.clearErrors();
    tunnelForm.reset();
    tunnelForm.clearErrors();
    tunnelTargetPipelineId.value = '';
}

const submitEditStage = () => {
    if (!editingStage.value) {
        return;
    }
    editStageForm.patch(route('stages.update', editingStage.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEditStageModal(),
    });
};

const submitDeal = () => {
    dealForm.post(route('deals.store'), {
        preserveScroll: true,
        onSuccess: () => {
            dealForm.reset('title', 'amount', 'client_id');
            dealForm.pipeline_id = props.selectedPipelineId ?? '';
            showDealModal.value = false;
        },
    });
};

const submitStage = () => {
    if (!props.pipeline) {
        return;
    }

    const stages = stageForm.stages
        .map((s) => ({ name: s.name.trim(), color: s.color }))
        .filter((s) => s.name);

    if (!stages.length) {
        stageForm.setError('stages', t('funnels.needOneStage'));

        return;
    }

    stageForm.clearErrors();
    stageForm
        .transform(() => ({ stages }))
        .post(route('stages.store', props.pipeline.id), {
            preserveScroll: true,
            onSuccess: () => closeStageModal(),
        });
};

function resetStageForm() {
    stageForm.clearErrors();
    stageForm.stages = [defaultStageRow(), defaultStageRow()];
}

function openStageModal() {
    resetStageForm();
    showStageModal.value = true;
}

function closeStageModal() {
    showStageModal.value = false;
    resetStageForm();
}

function addStageRow() {
    if (stageForm.stages.length >= 20) {
        return;
    }
    stageForm.stages.push(defaultStageRow());
}

function removeStageRow(index) {
    if (stageForm.stages.length <= 1) {
        return;
    }
    stageForm.stages.splice(index, 1);
}

function startReorder() {
    orderedStages.value = props.stages.map((s) => ({ ...s }));
    reorderMode.value = true;
}

function cancelReorder() {
    reorderMode.value = false;
    orderedStages.value = [];
    reorderForm.clearErrors();
}

function moveStage(index, offset) {
    const target = index + offset;
    if (target < 0 || target >= orderedStages.value.length) {
        return;
    }
    const list = orderedStages.value.slice();
    const [item] = list.splice(index, 1);
    list.splice(target, 0, item);
    orderedStages.value = list;
}

const saveReorder = () => {
    if (!props.pipeline) {
        return;
    }
    reorderForm.stage_ids = orderedStages.value.map((s) => s.id);
    reorderForm.patch(route('stages.reorder', props.pipeline.id), {
        preserveScroll: true,
        onSuccess: () => cancelReorder(),
    });
};

watch(
    () => props.selectedPipelineId,
    (id) => {
        dealForm.pipeline_id = id ?? '';
        cancelReorder();
    },
    { immediate: true },
);

function canDeleteStage(stage) {
    return props.stages.length > 1 && !(stage.deals?.length ?? 0);
}

function openDeleteStage(stage) {
    deletingStage.value = stage;
    showDeleteStageModal.value = true;
}

function closeDeleteStageModal() {
    showDeleteStageModal.value = false;
    deletingStage.value = null;
}

const confirmDeleteStage = () => {
    if (!deletingStage.value) {
        return;
    }
    router.delete(route('stages.destroy', deletingStage.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteStageModal(),
    });
};

const formatMoney = (n) =>
    new Intl.NumberFormat(numberLocaleTag.value, {
        style: 'currency',
        currency: 'KGS',
        maximumFractionDigits: 0,
    }).format(n);

function openBoard(pipelineId) {
    router.get(
        route('funnels.index'),
        { pipeline: pipelineId },
        { preserveState: true, preserveScroll: false },
    );
}

function onDragStart(e, dealId) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(dealId));
}

function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function onDropStage(e, stageId) {
    e.preventDefault();
    const raw = e.dataTransfer.getData('text/plain');
    const dealId = parseInt(raw, 10);
    if (!dealId || !stageId) {
        return;
    }
    router.patch(
        route('deals.update-stage', dealId),
        { stage_id: stageId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="pageTitle || t('funnels.title')" />

    <AuthenticatedLayout>
        <div class="py-4">
            <div class="mx-auto max-w-[1600px] space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="!pipelines.length"
                    class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center"
                >
                    <p class="text-slate-600">
                        {{ t('funnels.empty') }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#2fc26e] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#26a85f]"
                        @click="showCreateModal = true"
                    >
                        {{ t('funnels.create') }}
                    </button>
                </div>

                <template v-else>
                    <div
                        class="rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            class="flex items-stretch gap-1 overflow-x-auto border-b border-slate-100 p-2"
                        >
                            <button
                                v-for="p in pipelines"
                                :key="p.id"
                                type="button"
                                class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                                :class="
                                    selectedPipelineId === p.id
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'text-slate-700 hover:bg-slate-100'
                                "
                                @click="openBoard(p.id)"
                            >
                                <span
                                    v-if="p.is_default"
                                    class="text-amber-300"
                                    :title="t('funnels.primary')"
                                >★</span>
                                <span class="max-w-[12rem] truncate">{{
                                    p.name
                                }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:border-[#2fc26e] hover:text-[#26a85f]"
                                :title="t('funnels.new')"
                                @click="showCreateModal = true"
                            >
                                <span class="text-lg leading-none">+</span>
                            </button>
                        </div>

                        <div v-if="pipeline" class="p-4">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2">
                                    <h3
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{ pipeline.name }}
                                    </h3>
                                    <button
                                        v-if="!reorderMode"
                                        type="button"
                                        class="rounded p-1 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600"
                                        :title="t('funnels.rename')"
                                        @click="openEditPipeline"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <button
                                    v-if="stages.length > 1 && !reorderMode"
                                    type="button"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    @click="startReorder"
                                >
                                    {{ t('funnels.reorder') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!pipeline"
                        class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"
                    >
                        {{ t('funnels.select') }}
                    </div>

                    <InputError
                        v-if="pipeline"
                        :message="$page.props.errors?.stage"
                    />

                    <div
                        v-if="pipeline && reorderMode"
                        class="flex flex-col gap-3 rounded-lg bg-indigo-50 px-4 py-3 ring-1 ring-indigo-200 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm text-indigo-900">
                            {{ t('funnels.reorderHintFull') }}
                        </p>
                        <div class="flex shrink-0 gap-2">
                            <SecondaryButton
                                type="button"
                                @click="cancelReorder"
                            >
                                {{ t('common.cancel') }}
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                :disabled="reorderForm.processing"
                                @click="saveReorder"
                            >
                                {{ t('common.save') }}
                            </PrimaryButton>
                        </div>
                    </div>

                    <div
                        v-if="pipeline"
                        class="flex gap-4 overflow-x-auto pb-4 pt-1"
                    >
                        <div
                            v-for="(stage, index) in displayStages"
                            :key="stage.id"
                            class="flex w-[300px] shrink-0 flex-col rounded-xl bg-slate-100/80 ring-1 ring-slate-200"
                            :class="{
                                'ring-2 ring-indigo-400': reorderMode,
                            }"
                            @dragover="!reorderMode && onDragOver($event)"
                            @drop="
                                !reorderMode &&
                                    onDropStage($event, stage.id)
                            "
                        >
                            <div
                                class="flex items-center gap-2 border-b border-slate-200 px-3 py-3"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            stage.color || '#94a3b8',
                                    }"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800"
                                >
                                    {{ stage.name }}
                                </span>
                                <span
                                    v-if="stage.tunnel && !reorderMode"
                                    class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700"
                                    :title="`→ ${stage.tunnel.to_pipeline_name}: ${stage.tunnel.to_stage_name}`"
                                >
                                    →
                                </span>
                                <template v-if="reorderMode">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-slate-500 hover:bg-white hover:text-indigo-600 disabled:opacity-30"
                                        :title="t('funnels.moveLeft')"
                                        :disabled="index === 0"
                                        @click="moveStage(index, -1)"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.75 19.5 8.25 12l7.5-7.5"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-slate-500 hover:bg-white hover:text-indigo-600 disabled:opacity-30"
                                        :title="t('funnels.moveRight')"
                                        :disabled="
                                            index === displayStages.length - 1
                                        "
                                        @click="moveStage(index, 1)"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m8.25 4.5 7.5 7.5-7.5 7.5"
                                            />
                                        </svg>
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600"
                                        :title="t('funnels.stageSettings')"
                                        @click="openEditStage(stage)"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canDeleteStage(stage)"
                                        type="button"
                                        class="rounded p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                        :title="t('funnels.deleteStageBtn')"
                                        @click="openDeleteStage(stage)"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                            />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                            <div
                                v-if="!reorderMode"
                                class="flex min-h-[120px] flex-1 flex-col gap-2 p-2"
                            >
                                <div
                                    v-for="deal in stage.deals"
                                    :key="deal.id"
                                    draggable="true"
                                    class="cursor-grab rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-200 transition hover:shadow active:cursor-grabbing"
                                    @dragstart="onDragStart($event, deal.id)"
                                >
                                    <p
                                        class="font-medium leading-snug text-slate-900"
                                    >
                                        {{ deal.title }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-medium text-indigo-700"
                                    >
                                        {{ formatMoney(deal.amount) }}
                                    </p>
                                    <p
                                        v-if="deal.client"
                                        class="mt-1 truncate text-xs text-slate-500"
                                    >
                                        {{ deal.client.name }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between gap-2"
                                    >
                                        <p
                                            v-if="deal.assignee"
                                            class="truncate text-xs text-slate-400"
                                        >
                                            {{ deal.assignee.name }}
                                        </p>
                                        <Link
                                            :href="
                                                route('deals.destroy', deal.id)
                                            "
                                            method="delete"
                                            as="button"
                                            class="ml-auto shrink-0 text-xs text-red-600 hover:text-red-800"
                                            preserve-scroll
                                        >
                                            {{ t('common.delete') }}
                                        </Link>
                                    </div>
                                </div>
                                <p
                                    v-if="!stage.deals?.length"
                                    class="py-8 text-center text-xs text-slate-400"
                                >
                                    {{ t('funnels.dropDeal') }}
                                </p>
                            </div>
                            <p
                                v-else
                                class="px-3 py-6 text-center text-xs text-slate-400"
                            >
                                {{ t('funnels.sortMode') }}
                            </p>
                        </div>

                        <button
                            v-if="!reorderMode"
                            type="button"
                            class="flex w-[220px] shrink-0 flex-col items-center justify-center gap-2 self-stretch rounded-xl border-2 border-dashed border-slate-300 bg-white/50 p-6 text-slate-500 transition hover:border-indigo-400 hover:bg-indigo-50/50 hover:text-indigo-600"
                            @click="openStageModal"
                        >
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-2xl font-light leading-none text-slate-600"
                            >
                                +
                            </span>
                            <span class="text-sm font-medium">
                                {{ t('funnels.addStages') }}
                            </span>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <Modal
            :show="showCreateModal"
            max-width="md"
            @close="showCreateModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('funnels.new') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ t('funnels.defaultStages') }}
                </p>
                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitPipeline"
                >
                    <div>
                        <InputLabel
                            for="modal_pipeline_name"
                            :value="t('common.name')"
                        />
                        <TextInput
                            id="modal_pipeline_name"
                            v-model="pipelineForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            :placeholder="t('funnels.namePh')"
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="pipelineForm.errors.name"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <SecondaryButton
                            type="button"
                            @click="showCreateModal = false"
                        >
                            {{ t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="pipelineForm.processing"
                        >
                            {{ t('common.create') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showEditPipelineModal"
            max-width="md"
            @close="showEditPipelineModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('funnels.rename') }}
                </h2>
                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitEditPipeline"
                >
                    <div>
                        <InputLabel
                            for="edit_pipeline_name"
                            :value="t('common.name')"
                        />
                        <TextInput
                            id="edit_pipeline_name"
                            v-model="editPipelineForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="editPipelineForm.errors.name"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <SecondaryButton
                            type="button"
                            @click="showEditPipelineModal = false"
                        >
                            {{ t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="editPipelineForm.processing"
                        >
                            {{ t('common.save') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showDealModal"
            max-width="lg"
            @close="showDealModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('funnels.newDeal') }}
                </h2>
                <p v-if="pipeline" class="mt-1 text-sm text-gray-600">
                    {{ t('funnels.pipelineLabel', { name: pipeline.name }) }}
                </p>
                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitDeal"
                >
                    <div>
                        <InputLabel for="deal_title" :value="t('funnels.dealTitle')" />
                        <TextInput
                            id="deal_title"
                            v-model="dealForm.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            :placeholder="t('funnels.dealTitlePh')"
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="dealForm.errors.title"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="deal_amount" :value="t('funnels.amount')" />
                            <TextInput
                                id="deal_amount"
                                v-model="dealForm.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 block w-full"
                                placeholder="0"
                            />
                            <InputError
                                class="mt-2"
                                :message="dealForm.errors.amount"
                            />
                        </div>
                        <div>
                            <InputLabel for="deal_client" :value="t('common.client')" />
                            <select
                                id="deal_client"
                                v-model="dealForm.client_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">{{ t('funnels.clientNone') }}</option>
                                <option
                                    v-for="c in clients"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="dealForm.errors.client_id"
                            />
                        </div>
                    </div>
                    <InputError :message="dealForm.errors.pipeline_id" />
                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <SecondaryButton
                            type="button"
                            @click="showDealModal = false"
                        >
                            {{ t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="dealForm.processing"
                        >
                            {{ t('funnels.createDeal') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showStageModal"
            max-width="lg"
            @close="closeStageModal"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('funnels.newStages') }}
                </h2>
                <p v-if="pipeline" class="mt-1 text-sm text-gray-600">
                    {{ t('funnels.newStagesHint', { name: pipeline.name }) }}
                </p>
                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitStage"
                >
                    <div class="max-h-[24rem] space-y-3 overflow-y-auto pr-1">
                        <div
                            v-for="(row, index) in stageForm.stages"
                            :key="index"
                            class="rounded-lg border border-slate-200 bg-slate-50/50 p-3"
                        >
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <InputLabel
                                        :for="'stage_name_' + index"
                                        :value="t('funnels.stageN', { n: index + 1 })"
                                    />
                                    <TextInput
                                        :id="'stage_name_' + index"
                                        v-model="row.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        :placeholder="
                                            index === 0
                                                ? t('funnels.stagePh')
                                                : t('funnels.stageName')
                                        "
                                        :autofocus="index === 0"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="
                                            stageForm.errors[
                                                'stages.' + index + '.name'
                                            ]
                                        "
                                    />
                                </div>
                                <button
                                    v-if="stageForm.stages.length > 1"
                                    type="button"
                                    class="mt-7 rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    :title="t('funnels.removeRow')"
                                    @click="removeStageRow(index)"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M6 18 18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div class="mt-3">
                                <span
                                    class="text-xs font-medium text-slate-500"
                                >
                                    {{ t('funnels.color') }}
                                </span>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <button
                                        v-for="preset in stageColorPresets"
                                        :key="preset.value + '-' + index"
                                        type="button"
                                        class="h-6 w-6 rounded-full ring-2 ring-offset-1 transition"
                                        :class="
                                            row.color === preset.value
                                                ? 'ring-indigo-500'
                                                : 'ring-transparent hover:ring-slate-300'
                                        "
                                        :style="{
                                            backgroundColor: preset.value,
                                        }"
                                        :title="preset.label"
                                        @click="row.color = preset.value"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="stageForm.stages.length < 20"
                        type="button"
                        class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 py-2 text-sm text-slate-600 hover:border-indigo-400 hover:text-indigo-600"
                        @click="addStageRow"
                    >
                        <span class="text-lg leading-none">+</span>
                        {{ t('funnels.moreStage') }}
                    </button>

                    <InputError
                        class="mt-2"
                        :message="stageForm.errors.stages"
                    />

                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <SecondaryButton
                            type="button"
                            @click="closeStageModal"
                        >
                            {{ t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="stageForm.processing"
                        >
                            {{ t('funnels.addStages') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showEditStageModal"
            max-width="md"
            @close="closeEditStageModal"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('funnels.stageSettings') }}
                </h2>
                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitEditStage"
                >
                    <div>
                        <InputLabel
                            for="edit_stage_name"
                            :value="t('funnels.stageName')"
                        />
                        <TextInput
                            id="edit_stage_name"
                            v-model="editStageForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="editStageForm.errors.name"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <SecondaryButton
                            type="button"
                            @click="closeEditStageModal"
                        >
                            {{ t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="editStageForm.processing"
                        >
                            {{ t('common.save') }}
                        </PrimaryButton>
                    </div>
                </form>

                <div
                    v-if="hasLinkablePipelines"
                    class="mt-8 border-t border-slate-200 pt-6"
                >
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ t('funnels.linkTitle') }}
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('funnels.linkHintFull') }}
                    </p>

                    <div
                        v-if="editingStage?.tunnel"
                        class="mt-4 rounded-lg bg-indigo-50 px-3 py-2 text-sm text-indigo-900 ring-1 ring-indigo-200"
                    >
                        <span class="font-medium">{{ editingStage.name }}</span>
                        →
                        <span class="font-medium">{{
                            editingStage.tunnel.to_pipeline_name
                        }}</span
                        >:
                        {{ editingStage.tunnel.to_stage_name }}
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <InputLabel
                                for="tunnel_pipeline"
                                :value="t('funnels.targetPipeline')"
                            />
                            <select
                                id="tunnel_pipeline"
                                v-model="tunnelTargetPipelineId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="onTunnelPipelineChange"
                            >
                                <option value="">{{ t('messenger.selectPipeline') }}</option>
                                <option
                                    v-for="p in linkablePipelines"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="tunnelTargetPipelineId">
                            <InputLabel
                                for="tunnel_stage"
                                :value="t('funnels.targetStage')"
                            />
                            <select
                                id="tunnel_stage"
                                v-model="tunnelForm.to_stage_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">{{ t('funnels.selectStage') }}</option>
                                <option
                                    v-for="s in tunnelTargetStages"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="tunnelForm.errors.to_stage_id"
                            />
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-wrap gap-2"
                    >
                        <PrimaryButton
                            type="button"
                            :disabled="
                                !tunnelForm.to_stage_id || tunnelForm.processing
                            "
                            @click="submitStageTunnel"
                        >
                            {{
                                editingStage?.tunnel
                                    ? t('funnels.updateLink')
                                    : t('funnels.createLink')
                            }}
                        </PrimaryButton>
                        <SecondaryButton
                            v-if="editingStage?.tunnel"
                            type="button"
                            @click="removeStageTunnel"
                        >
                            {{ t('funnels.deleteLink') }}
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal
            :show="showDeleteStageModal"
            max-width="md"
            @close="closeDeleteStageModal"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('funnels.deleteStageTitle') }}
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ t('funnels.deleteStageBodyFull', { name: deletingStage?.name }) }}
                </p>
                <div
                    class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <SecondaryButton
                        type="button"
                        @click="closeDeleteStageModal"
                    >
                        {{ t('common.cancel') }}
                    </SecondaryButton>
                    <DangerButton type="button" @click="confirmDeleteStage">
                        {{ t('funnels.deleteStageBtn') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
