<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register microservice communication services
        $this->app->singleton(\App\Services\UserService::class);
        $this->app->singleton(\App\Services\CalendarService::class);
        $this->app->singleton(\App\Services\EventEnrichmentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
