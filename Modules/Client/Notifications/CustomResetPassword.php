<?php

namespace Modules\Client\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends Notification
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
{
    return (new MailMessage)
        ->subject('Réinitialisation de votre mot de passe')
        ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe.')
        ->action('Réinitialiser le mot de passe', url(config('app.url') . route('client.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset()
        ], false)))
        ->line('Ce lien expirera dans 60 minutes.');
}

}