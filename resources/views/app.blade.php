<!DOCTYPE html>
<html lang="{{auth()->check() ? auth()->user()->lang : str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <link rel="shortcut icon" type="image/x-icon" href="img/logo/zbb-icon2.ico">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'ZBB') }}</title>


        <style>
            .menu-arrow::before {
              content: '\25BC'; /* Custom arrow symbol */
              display: inline-block;
              transform: rotate(0deg);
              transition: transform 0.3s ease;
            }

            ul[v-show='true'] + a .menu-arrow::before {
              transform: rotate(180deg); /* Rotate arrow when submenu is open */
            }
        </style>


        <!-- Fonts -->
       {{--  <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
 --}}
        <!-- Scripts -->
        @routes
            @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead


    </head>
    <body class="font-Helvetica antialiased">
        @inertia
    </body>

</html>
<style>
    /* Custom scrollbar styles */
    ::-webkit-scrollbar {
        width: 8px; /* Width of the scrollbar */
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1; /* Background of the scrollbar track */
    }

    ::-webkit-scrollbar-thumb {
        background: #888; /* Color of the scrollbar thumb */
        border-radius: 4px; /* Rounded corners for the thumb */
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #555; /* Color of the thumb on hover */
    }
