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
        $this->app->bind(\App\Contracts\AIAssistantContract::class, \App\Services\Assistant\AIAssistant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // NOTE: VerifyEmail::toMailUsing() removed to prevent automatic email sending
        // We use custom notifications (VerifyEmail in modules) that are sent manually
        // in the registration controllers. This ensures only ONE email is sent.
        
    }
}

