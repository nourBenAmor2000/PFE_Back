<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Password;
use Modules\Visit\App\Models\Visit;
use Modules\Visit\App\Policies\VisitPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Visit::class => VisitPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Configuration de la vérification d'email
        $this->configureEmailVerification();

        // Configuration de la réinitialisation de mot de passe
        $this->configurePasswordReset();
    }

    protected function configureEmailVerification(): void
    {
        // NOTE: VerifyEmail::toMailUsing() removed to prevent automatic email sending
        // We use custom notifications (VerifyEmail in modules) that are sent manually
        // in the registration controllers. This ensures only ONE email is sent.
        // The custom notifications use code-based verification, not URL-based.
    }

    protected function configurePasswordReset(): void
    {
        // Solution universelle pour toutes versions de Laravel
        $this->app->bind(
            \Illuminate\Auth\Passwords\PasswordBroker::class,
            function ($app) {
                return Password::broker('clients');
            }
        );

        // NOTE: Ne pas envoyer d'email de réinitialisation lors de la création
        // L'email de réinitialisation est envoyé UNIQUEMENT via ForgotPasswordController
        // L'email de vérification est envoyé UNIQUEMENT lors de l'inscription (register)
    }
}

