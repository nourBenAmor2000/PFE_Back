@component('mail::message')
# Bonjour {{ $notifiable->name }} !

Veuillez vérifier votre adresse email pour activer votre compte agent.

@component('mail::button', ['url' => $verificationUrl])
Vérifier mon email
@endcomponent

Ce lien expirera dans 60 minutes.

Merci,<br>
{{ config('app.name') }}
@endcomponent