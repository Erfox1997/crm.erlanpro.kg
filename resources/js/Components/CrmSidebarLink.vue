<script setup>
import { Link } from '@inertiajs/vue3';

const emit = defineEmits(['navigate']);

defineProps({
    href: String,
    active: Boolean,
    collapsed: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Link
        :href="href"
        :title="collapsed && title ? title : undefined"
        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
        @click="emit('navigate')"
        :class="[
            active
                ? 'bg-slate-800 text-white'
                : 'text-slate-300 hover:bg-slate-800/60 hover:text-white',
            collapsed ? 'justify-center md:justify-center' : '',
        ]"
    >
        <span
            v-if="$slots.icon"
            class="inline-flex h-5 w-5 shrink-0 items-center justify-center"
            aria-hidden="true"
        >
            <slot name="icon" />
        </span>
        <span
            :class="['truncate', collapsed ? 'md:sr-only' : '']"
        >
            <slot />
        </span>
    </Link>
</template>
