<?php





namespace Modules\Client\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

 
public function toMail($notifiable)
{
    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

    $resetUrl = $frontendUrl
        . '/reset-password?token=' . $this->token
        . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

    return (new MailMessage)
        ->subject('Réinitialisation de votre mot de passe')
        ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
        ->action('Réinitialiser le mot de passe', $resetUrl)
        ->line('Si vous n\'avez pas demandé cette réinitialisation, aucune autre action n\'est requise.');
}
}
