<script setup>
defineProps({
    showDomain: {
        type: Boolean,
        default: true,
    },
    domain: {
        type: String,
        default: 'crm.erlanpro.kg',
    },
    name: {
        type: String,
        default: 'ErlanPro',
    },
    iconClass: {
        type: String,
        default: 'h-10 w-10',
    },
    light: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-3">
        <img
            src="/images/erlanpro-logo.svg"
            :alt="name"
            class="shrink-0 rounded-xl"
            :class="iconClass"
        />
        <span v-if="showDomain" class="min-w-0 truncate leading-tight">
            <span
                class="block text-base font-semibold tracking-tight"
                :class="light ? 'text-white' : 'text-slate-900'"
            >
                {{ name }}
            </span>
            <span
                class="block text-xs font-medium"
                :class="light ? 'text-slate-400' : 'text-slate-500'"
            >
                {{ domain }}
            </span>
        </span>
    </span>
</template>
