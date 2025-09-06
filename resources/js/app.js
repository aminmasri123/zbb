import './bootstrap';
import '../css/app.css';
import '../../public/css/line-awesome.min.css';
//import '../../public/css/font-awesome.min.css';
import '../../public/css/css.css';




import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import i18n from './i18n'; // Stelle sicher, dass i18n korrekt importiert wird


import PrimeVue from "primevue/config";
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Aura from '@primevue/themes/aura';
import { setThemeOnLoad } from './theme';


const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),

    setup({ el, App, props, plugin }) {

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)

            .use(ZiggyVue, Ziggy)
            .use(PrimeVue, {
                theme: {
                    preset: Aura
                }
            })
            .component('InputText', InputText)
            .component('Button', Button)

            .mount(el);
    },
    progress: {
        color: '#ff8500',
    },

});

setThemeOnLoad(); 
