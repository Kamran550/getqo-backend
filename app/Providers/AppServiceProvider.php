<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //        if ($this->app->environment('local')) {
        //            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        //            $this->app->register(TelescopeServiceProvider::class);
        //        }
    }

    /**
     * Bootstrap any application services.
    *
     * @return void
     */
    public function boot(): void
    {
        $key = env('APP_LICENSE_KEY');
        Cache::forever('app.license', ['active' => 1, 'key' => $key]);
    }
}
