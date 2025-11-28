<?php

namespace Modules\Agent\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Modules\Agent\App\Models\Agent;

class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(Agent $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Agent $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());
        
        // Generate signed URL for backend verification, but redirect to frontend
        $signedUrl = URL::temporarySignedRoute(
            'agent.verification.verify',
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
        $verificationUrl = $frontendUrl . '/verify-email/' . $id . '/' . $hash . 
                          '?type=agent&signature=' . urlencode($signature) . 
                          '&expires=' . urlencode($expires);

        return (new MailMessage)
            ->subject('Vérification de votre email Agent')
            ->greeting('Bonjour '.$notifiable->name.' !')
            ->line('Merci de vous être inscrit sur notre plateforme agent.')
            ->action('Vérifier mon email', $verificationUrl)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line("Si vous n'avez pas créé de compte, ignorez cet email.");
    }
}