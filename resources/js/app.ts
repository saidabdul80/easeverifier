import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, Fragment, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import vuetify from './plugins/vuetify';
import Toast from './components/Toast.vue';
import WhatsAppChat from './components/WhatsAppChat.vue';

import { Ziggy } from './ziggy';
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () =>
                h(Fragment, null, [h(App, props), h(Toast), h(WhatsAppChat)]),
        });
        app.config.globalProperties.$ziggy = Ziggy;
        app.use(plugin).use(vuetify).mount(el);
    },
    progress: {
        delay: 0,
        color: '#f4c74c',
        includeCSS: true,
        showSpinner: false,
    },
});

// This will set light / dark mode on page load...
initializeTheme();
