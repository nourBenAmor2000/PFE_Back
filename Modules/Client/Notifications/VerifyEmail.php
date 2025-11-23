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
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());
        
        // Generate signed URL for backend verification, but redirect to frontend
        $signedUrl = URL::temporarySignedRoute(
            'client.verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $id,
                'hash' => $hash,
            ]
        );
        
        // Extract signature from signed URL
        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $signature = $queryParams['signature'] ?? '';
        $expires = $queryParams['expires'] ?? '';
        
        // Build frontend URL with all necessary parameters
        return $frontendUrl . '/verify-email/' . $id . '/' . $hash . 
               '?type=client&signature=' . urlencode($signature) . 
               '&expires=' . urlencode($expires);
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