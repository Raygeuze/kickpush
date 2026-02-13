import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import {loadStripe} from '@stripe/stripe-js';
import { createPinia } from 'pinia'
// import utilities from './utilities.js';
import * as utilities from './utilities';

const stripe = await loadStripe(import.meta.env.VITE_STRIPE_KEY);
const pinia = createPinia();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .provide('utilities', utilities)
            .provide('stripe', stripe)
            .mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});

