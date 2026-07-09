import './bootstrap';
import '../css/app.css';
import '../../public/css/line-awesome.css';
//import '../../public/css/font-awesome.min.css';
import '../../public/css/css.css';

import { formatDate } from '@/utils/dateFormat.js';



import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import i18n from './i18n'; // Stelle sicher, dass i18n korrekt importiert wird


import PrimeVue from "primevue/config";
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Aura from '@primeuix/themes/aura';
import { setThemeOnLoad } from './theme';


const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'ERP ZBB';
window.asset = (path) => `${window.assetBaseUrl || ''}/${String(path).replace(/^\/+/, '')}`;


// Abmelde-Synchronisation über localStorage
window.addEventListener("storage", (event) => {
    if (event.key === "logout") {
        // Benutzer sofort auf Login-Seite umleiten
        window.location.href = window.asset('login');
    }
});



createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })
        .use(plugin)
        .use(i18n)
        .use(ZiggyVue, window.Ziggy)
        .use(PrimeVue, {
            theme: {
                preset: Aura
            }
        })
        .component('InputText', InputText)
        .component('Button', Button);

    // ✅ Hier: globale Funktion hinzufügen
    app.config.globalProperties.$formatDate = formatDate;

    // ✅ Und am Ende mounten
    app.mount(el);

    return app;
},

    progress: {
        color: '#ff8500',
    },

});

setThemeOnLoad();
