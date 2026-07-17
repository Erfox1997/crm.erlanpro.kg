<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    variant: {
        type: String,
        default: 'light',
    },
});

const page = usePage();

const newsGroupUrl = computed(
    () =>
        page.props.publicTelegram?.newsGroupUrl ||
        'https://t.me/+XAExfDN7j8Q1NWRi',
);

const linkClass = (variant) =>
    variant === 'dark'
        ? 'hover:text-slate-400'
        : 'hover:text-slate-700';
</script>

<template>
    <footer
        :class="
            variant === 'dark'
                ? 'mt-24 border-t border-white/10 pt-10 text-center'
                : 'border-t border-slate-200 bg-white py-6'
        "
    >
        <p
            :class="
                variant === 'dark'
                    ? 'text-sm text-slate-500'
                    : 'text-center text-xs text-slate-500'
            "
        >
            © {{ new Date().getFullYear() }}
            <strong>ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ</strong>
            · crm.erlanpro.kg
        </p>
        <p
            :class="
                variant === 'dark'
                    ? 'mt-2 text-xs text-slate-600'
                    : 'mt-2 text-center text-xs text-slate-500'
            "
        >
            <Link href="/" :class="linkClass(variant)">На главную</Link>
            ·
            <Link href="/legal" :class="linkClass(variant)">Реквизиты</Link>
            ·
            <Link href="/privacy" :class="linkClass(variant)">
                Конфиденциальность
            </Link>
            ·
            <Link href="/terms" :class="linkClass(variant)">Соглашение</Link>
            ·
            <Link href="/updates" :class="linkClass(variant)">Обновления</Link>
            ·
            <a
                :href="newsGroupUrl"
                :class="linkClass(variant)"
                target="_blank"
                rel="noopener noreferrer"
                >Новости Telegram</a
            >
        </p>
    </footer>
</template>
