import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '192.168.0.192', //zuhause
        //host: '192.168.245.56', //ZBB
        //host: '192.168.101.13',
    },

    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/css/app.css'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        rollupOptions: {
            input: {
                main: 'resources/js/app.js', // Haupt-Einstiegspunkt
            },
        },
    },

});
