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
        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
        @click="emit('navigate')"
        :class="[
            active
                ? 'bg-gradient-to-r from-indigo-500/90 to-teal-500/80 text-white shadow-lg shadow-indigo-900/30'
                : 'text-slate-300 hover:bg-white/8 hover:text-white',
            collapsed ? 'justify-center md:justify-center' : '',
        ]"
    >
        <span
            v-if="$slots.icon"
            class="inline-flex h-5 w-5 shrink-0 items-center justify-center transition-transform group-hover:scale-110"
            aria-hidden="true"
        >
            <slot name="icon" />
        </span>
        <span :class="['truncate', collapsed ? 'md:sr-only' : '']">
            <slot />
        </span>
    </Link>
</template>
