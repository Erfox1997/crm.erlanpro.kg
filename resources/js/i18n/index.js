import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import ru from './locales/ru.json';

export const LOCALE_KEY = 'crm-locale';
export const SUPPORTED_LOCALES = ['ru', 'en'];

export function getStoredLocale() {
    if (typeof window === 'undefined') {
        return 'ru';
    }

    const stored = localStorage.getItem(LOCALE_KEY);

    return SUPPORTED_LOCALES.includes(stored) ? stored : 'ru';
}

export function setStoredLocale(locale) {
    if (!SUPPORTED_LOCALES.includes(locale)) {
        return;
    }

    localStorage.setItem(LOCALE_KEY, locale);
}

export function localeTag(locale = getStoredLocale()) {
    return locale === 'en' ? 'en-US' : 'ru-RU';
}

const i18n = createI18n({
    legacy: false,
    locale: getStoredLocale(),
    fallbackLocale: 'ru',
    messages: { ru, en },
});

export function setLocale(locale) {
    if (!SUPPORTED_LOCALES.includes(locale)) {
        return;
    }

    i18n.global.locale.value = locale;
    setStoredLocale(locale);

    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale;
    }
}

export default i18n;
