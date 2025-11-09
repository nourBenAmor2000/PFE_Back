<?php

namespace App\Services\Assistant\Handlers;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Handler "contrat"
 * Intents supportés:
 *  - signed_this_month
 *  - pending_signature
 *  - by_id (requires 'id')
 */
class ContratHandler implements EntityHandler
{
    // ⚠️ adapte le namespace du modèle si besoin (Modules\Contrat\App\Models\Contrat)
    private \Modules\Contrat\App\Models\Contrat $Contrat;

    public function __construct()
    {
        $this->Contrat = new \Modules\Contrat\App\Models\Contrat();
    }

    public function entity(): string
    {
        return 'contrat';
    }

    public function canHandle(array $intent): bool
    {
        return ($intent['entity'] ?? null) === 'contrat';
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        $action = $intent['action'] ?? null;

        switch ($action) {
            case 'signed_this_month':
                return $this->signedThisMonth();

            case 'pending_signature':
                return $this->pendingSignature();

            case 'by_id':
                $id = $intent['id'] ?? null;
                if (!$id) {
                    return [
                        'answer' => "ID de contrat manquant.",
                        'data' => [],
                        'sources' => ['contrats'],
                        'suggestions' => [],
                    ];
                }
                return $this->detailsById($id);

            default:
                return [
                    'answer' => "Action non reconnue pour l’entité contrat.",
                    'data' => [],
                    'sources' => ['contrats'],
                    'suggestions' => [],
                ];
        }
    }

    private function signedThisMonth(): array
    {
        $tz = 'Europe/Berlin';
        $start = Carbon::now($tz)->startOfMonth()->format('Y-m-d 00:00:00');
        $end   = Carbon::now($tz)->endOfMonth()->format('Y-m-d 23:59:59');

        // champs supposés: status = 'signed', signed_at (datetime string)
        $rows = $this->Contrat
            ->where('status', 'signed')
            ->whereBetween('signed_at', [$start, $end])
            ->get();

        return [
            'answer' => "{$rows->count()} contrat(s) signé(s) ce mois-ci.",
            'data' => $rows,
            'sources' => ['contrats'],
            'suggestions' => [],
        ];
    }

    private function pendingSignature(): array
    {
        // status = 'pending'
        $rows = $this->Contrat
            ->where('status', 'pending')
            ->get();

        return [
            'answer' => "{$rows->count()} contrat(s) en attente de signature.",
            'data' => $rows,
            'sources' => ['contrats'],
            'suggestions' => [],
        ];
    }

    private function detailsById(string $id): array
    {
        $row = $this->Contrat->find($id);

        if (!$row) {
            return [
                'answer' => "Contrat #{$id} introuvable.",
                'data' => [],
                'sources' => ['contrats'],
                'suggestions' => [],
            ];
        }

        return [
            'answer' => "Détails du contrat #{$id}.",
            'data' => [$row],
            'sources' => ['contrats'],
            'suggestions' => [],
        ];
    }
}
