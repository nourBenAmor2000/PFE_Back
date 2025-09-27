<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPassword extends Notification
{
    use Queueable;

    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $url = url('/reset-password?token=' . $this->token . '&email=' . $notifiable->email);

        return (new MailMessage)
            ->subject('Réinitialisation de mot de passe')
            ->greeting('Bonjour ' . $notifiable->name)
            ->line('Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation du mot de passe pour votre compte.')
            ->action('Réinitialiser le mot de passe', $url)
            ->line('Si vous n\'avez pas demandé cette réinitialisation, aucune action n\'est requise.');
    }
}
