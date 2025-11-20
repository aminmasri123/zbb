import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {

        //host: '192.168.0.192',
        //host: '172.19.10.18',
        host: 'localhost',
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
