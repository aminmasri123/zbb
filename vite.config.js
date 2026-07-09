import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const pdfToolPackages = [
    'fast-png',
    'fflate',
    'jspdf',
];

const pdfSupportPackages = [
    '@babel/runtime',
    'canvg',
    'core-js',
    'dompurify',
    'iobuffer',
    'pako',
    'raf',
    'regenerator-runtime',
    'rgbcolor',
    'stackblur-canvas',
    'svg-pathdata',
    'utrie',
];

const canvasToolPackages = [
    'css-line-break',
    'html2canvas',
    'text-segmentation',
];

const isPackage = (id, packageName) => id.includes(`/node_modules/${packageName}/`);

export default defineConfig({
     server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
                host: 'localhost',
        }
    }, 
   /*  server: {
        host: 'localhost',

    }, */
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
            output: {
                manualChunks(id) {
                    const normalizedId = id.split('\\').join('/');

                    if (!normalizedId.includes('node_modules')) {
                        return;
                    }

                    if (pdfToolPackages.some((packageName) => isPackage(normalizedId, packageName))) {
                        return 'pdf-tools';
                    }

                    if (pdfSupportPackages.some((packageName) => isPackage(normalizedId, packageName))) {
                        return 'pdf-support';
                    }

                    if (canvasToolPackages.some((packageName) => isPackage(normalizedId, packageName))) {
                        return 'canvas-tools';
                    }

                    if (normalizedId.includes('primevue') || normalizedId.includes('@primevue')) {
                        return 'primevue';
                    }

                    if (normalizedId.includes('/vue/') || normalizedId.includes('/@vue/')) {
                        return 'vue-core';
                    }

                    if (normalizedId.includes('@inertiajs') || normalizedId.includes('vue-router') || normalizedId.includes('vue-i18n')) {
                        return 'inertia-vue';
                    }

                    if (normalizedId.includes('lodash')) {
                        return 'lodash';
                    }

                    if (normalizedId.includes('axios')) {
                        return 'http';
                    }

                    if (normalizedId.includes('dayjs')) {
                        return 'dates';
                    }

                    if (normalizedId.includes('@popperjs')) {
                        return 'popper';
                    }

                    if (normalizedId.includes('@vueform')) {
                        return 'forms';
                    }

                    if (normalizedId.includes('sweetalert2')) {
                        return 'alerts';
                    }

                    if (normalizedId.includes('dropzone')) {
                        return 'uploads';
                    }

                    return 'vendor';
                },
            },
        },
    }

});
