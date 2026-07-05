<script setup>
import { Link } from '@inertiajs/vue3';

const emit = defineEmits(['navigate']);

defineProps({
    href: String,
    active: Boolean,
    badge: {
        type: [Number, String],
        default: null,
    },
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
        class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
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
        <span
            v-if="badge && Number(badge) > 0"
            class="ml-auto inline-flex min-h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-[#25d366] px-1.5 text-[11px] font-bold text-white"
            :class="collapsed ? 'absolute -right-0.5 -top-0.5 md:relative md:right-auto md:top-auto' : ''"
        >
            {{ Number(badge) > 99 ? '99+' : badge }}
        </span>
    </Link>
</template>
