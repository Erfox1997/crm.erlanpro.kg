<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { setLocale, SUPPORTED_LOCALES } from '@/i18n';

const props = defineProps({
    variant: {
        type: String,
        default: 'dark',
    },
});

const { locale } = useI18n();

const buttonClass = computed(() => {
    if (props.variant === 'light') {
        return {
            base: 'rounded px-1.5 py-0.5 text-xs font-semibold transition',
            active: 'bg-indigo-600 text-white',
            idle: 'text-slate-500 hover:text-slate-800',
        };
    }

    if (props.variant === 'public') {
        return {
            base: 'rounded-md px-2 py-1 text-xs font-semibold transition',
            active: 'bg-indigo-600 text-white',
            idle: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        };
    }

    return {
        base: 'rounded px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide transition',
        active: 'bg-white/15 text-white',
        idle: 'text-slate-400 hover:text-white',
    };
});

function switchTo(code) {
    setLocale(code);
}
</script>

<template>
    <div
        class="inline-flex items-center gap-0.5 rounded-lg border border-white/10 bg-black/20 p-0.5"
        :class="{
            'border-slate-200 bg-slate-100': variant === 'light' || variant === 'public',
        }"
        role="group"
        :aria-label="$t('common.language')"
    >
        <button
            v-for="code in SUPPORTED_LOCALES"
            :key="code"
            type="button"
            :class="[
                buttonClass.base,
                locale === code ? buttonClass.active : buttonClass.idle,
            ]"
            :aria-pressed="locale === code"
            @click="switchTo(code)"
        >
            {{ code.toUpperCase() }}
        </button>
    </div>
</template>
