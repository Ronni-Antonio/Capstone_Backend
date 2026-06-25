<?php

namespace App\Providers;

use App\Models\systemSettings;
use App\Observers\systemSettingsObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        systemSettings::observe(systemSettingsObserver::class);
    }
}
