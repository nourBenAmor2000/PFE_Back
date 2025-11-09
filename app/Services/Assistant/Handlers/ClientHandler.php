<?php

namespace App\Services\Assistant\Handlers;

use Illuminate\Contracts\Auth\Authenticatable;
// adapte le namespace du modèle à ton projet (module Clients)
use Modules\Client\App\Models\Client;

class ClientHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'client';
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        // Filtre "actifs" si l'intent le demande, sinon total
        $onlyActive = ($intent['filters']['active'] ?? false) === true
                   || in_array('active', $intent['keywords'] ?? [], true)
                   || in_array('actifs', $intent['keywords'] ?? [], true);

        $q = Client::query();

        // Deux variantes possibles selon ton schéma :
        // 1) booléen "active"
        if (schema_has_bool_active()) {
            if ($onlyActive) $q->where('active', true);
        }
        // 2) enum "status" = 'ACTIVE' | 'SUSPENDED' | ...
        else {
            if ($onlyActive) $q->where('status', 'ACTIVE');
        }

        $count = $q->count();

        return [
            'answer' => $onlyActive
                ? "Il y a {$count} client(s) actif(s)."
                : "Total clients : {$count}.",
            'data' => ['count' => $count],
            'sources' => ['clients'],
            'suggestions' => [],
        ];
    }
}

/**
 * Option utilitaire simple : détecter si le champ 'active' (bool) existe.
 * Tu peux aussi supprimer cette fonction et choisir une des deux
 * branches ci-dessus selon TON schéma.
 */
if (!function_exists('schema_has_bool_active')) {
    function schema_has_bool_active(): bool
    {
        try {
            // si tu es en Mongo, on ne peut pas introspecter facilement :
            // Retourne true si ton modèle utilise 'active' bool.
            return true; // <-- mets false si tu utilises 'status'
        } catch (\Throwable) {
            return true;
        }
    }
}
