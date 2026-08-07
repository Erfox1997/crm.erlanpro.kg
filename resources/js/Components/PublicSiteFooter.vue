<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    variant: {
        type: String,
        default: 'light',
    },
});

const { t } = useI18n();
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
            <Link href="/" :class="linkClass(variant)">{{ t('public.footer.home') }}</Link>
            ·
            <Link href="/legal" :class="linkClass(variant)">{{ t('public.footer.legal') }}</Link>
            ·
            <Link href="/privacy" :class="linkClass(variant)">
                {{ t('public.footer.privacy') }}
            </Link>
            ·
            <Link href="/terms" :class="linkClass(variant)">{{ t('public.footer.terms') }}</Link>
            ·
            <Link href="/updates" :class="linkClass(variant)">{{ t('public.footer.updates') }}</Link>
            ·
            <a
                :href="newsGroupUrl"
                :class="linkClass(variant)"
                target="_blank"
                rel="noopener noreferrer"
                >{{ t('public.footer.telegramNews') }}</a
            >
        </p>
    </footer>
</template>
