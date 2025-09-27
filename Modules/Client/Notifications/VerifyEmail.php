<?php

namespace Modules\Client\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Modules\Client\App\Models\Client;

class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(Client $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the verification URL.
     */
    protected function verificationUrl(Client $notifiable): string
    {
        return URL::temporarySignedRoute(
            'client.verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(Client $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vérification de votre adresse email')
            ->line('Merci de vous être inscrit sur notre plateforme.')
            ->line('Veuillez cliquer sur le bouton ci-dessous pour vérifier votre adresse email :')
            ->action('Vérifier mon email', $this->verificationUrl($notifiable))
            ->line("Si vous n'avez pas créé de compte, vous pouvez ignorer cet email.");
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(Client $notifiable): array
    {
        return [
            //
        ];
    }
}