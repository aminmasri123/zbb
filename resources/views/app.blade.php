<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <link rel="shortcut icon" type="image/x-icon" href="{{asset('public/img/logo/ZBB_Zukunft-favi.ico')}}">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>



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
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
            @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead




    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>

</html>
