<?php

namespace App\Services\Assistant\Handlers;

use Illuminate\Contracts\Auth\Authenticatable;

interface EntityHandler
{
    /** Nom logique géré par le handler, ex. 'visit', 'logement' */
    public function entity(): string;

    /**
     * Traite l’intent et retourne la réponse assistant.
     * $intent: tableau déjà parsé (timeframe, filters, etc.)
     * $user: utilisateur courant (peut être null si non connecté)
     */
    public function handle(array $intent, ?Authenticatable $user = null): array;
     public function canHandle(string $entity): bool;
    
}
