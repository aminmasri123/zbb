<?php

namespace App\Providers;

use URL;
use Inertia\Inertia;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
         Inertia::share([
        'flash' => function () {
            return [
                'success' => session('success'),
                'error' => session('error'),
            ];
        },
    ]);
    }
}
