const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    darkMode: "class",
    theme: {
        extend: {
            fontFamily: {
                //'Helvetica, Figtree'
                Helvetica: ['Helvetica', ...defaultTheme.fontFamily.sans],
                Montserrat: ['Montserrat', 'sans-serif'],
            },
            colors:{
                zbb: '#ff8500',
                zbbTrp: 'rgba(255, 133, 0, 0.1)',
                danger: '#ff0000',
                success: '#16a34a',
                warning: '#eab308',
                info: '#3b82f6'
            },

        },
    },

    plugins: [require('@tailwindcss/forms'), require('@tailwindcss/typography')],
};
