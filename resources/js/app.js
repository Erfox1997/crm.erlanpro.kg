import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n, { getStoredLocale, syncLocaleCookie } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function showClientError(error) {
    const message = error?.stack || error?.message || String(error);
    console.error(error);

    if (typeof document === 'undefined') {
        return;
    }

    let box = document.getElementById('crm-client-error');
    if (! box) {
        box = document.createElement('pre');
        box.id = 'crm-client-error';
        box.style.cssText = 'position:fixed;inset:12px;z-index:99999;overflow:auto;margin:0;padding:16px;border-radius:12px;background:#7f1d1d;color:#fee2e2;font:12px/1.4 ui-monospace,monospace;white-space:pre-wrap;';
        document.body.appendChild(box);
    }

    box.textContent = message;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const locale = getStoredLocale();
        document.documentElement.lang = locale;
        syncLocaleCookie(locale);

        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n);

        vueApp.config.errorHandler = (err) => {
            // Don't blanket the whole UI in red for expected media / permission failures.
            const name = String(err?.name || '');
            if (['NotAllowedError', 'NotFoundError', 'NotReadableError', 'AbortError'].includes(name)) {
                console.warn(err);
                return;
            }
            showClientError(err);
        };

        window.addEventListener('unhandledrejection', (event) => {
            const reason = event.reason;
            const name = String(reason?.name || '');
            if (['NotAllowedError', 'NotFoundError', 'NotReadableError', 'AbortError'].includes(name)) {
                console.warn(reason);
                return;
            }
            showClientError(reason);
        });

        return vueApp.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});