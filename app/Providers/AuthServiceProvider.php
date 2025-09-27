<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Client\App\Models\Client;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Model => Policy mappings
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
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            $userType = class_basename($notifiable);
            
            return (new MailMessage)
                ->subject("Vérification de l'adresse email")
                ->line("Cliquez pour vérifier votre email")
                ->action('Vérifier', $url);
        });
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

        // Configuration alternative via Notification
        Client::created(function ($client) {
            $client->sendPasswordResetNotification(
                Password::broker('clients')->createToken($client)
            );
        });
    }
}