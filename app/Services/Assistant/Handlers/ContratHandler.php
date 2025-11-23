<?php

namespace App\Services\Assistant\Handlers;

use Carbon\Carbon;
use Modules\Contract\App\Models\Contract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Handler "contrat"
 * Intents supportés:
 *  - signed_this_month
 *  - pending_signature
 *  - by_id (requires 'id')
 */
class ContratHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'contrat';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['contrat', 'contrats', 'contract', 'contracts']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        try {
            $action = $intent['action'] ?? null;

            switch ($action) {
                case 'signed_this_month':
                    return $this->signedThisMonth();

                case 'pending_signature':
                    return $this->pendingSignature();

                case 'by_id':
                    $id = $intent['filters']['id'] ?? $intent['id'] ?? null;
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
                    // Default: return all contracts or recent contracts
                    return $this->getAllContracts();
            }
        } catch (\Throwable $e) {
            Log::error('ContratHandler.handle error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'answer' => "Erreur lors du traitement de la requête contrat: " . $e->getMessage(),
                'data' => [],
                'analytics' => [],
                'sources' => ['contrats'],
                'suggestions' => ['Réessayer', 'Vérifier les données'],
            ];
        }
    }

    private function getAllContracts(): array
    {
        try {
            $rows = Contract::query()
                ->with(['client', 'logement'])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            $count = $rows->count();
            
            return [
                'answer' => "{$count} contrat(s) trouvé(s).",
                'data' => $rows->map(function($contract) {
                    return [
                        '_id' => $contract->_id,
                        'client_id' => $contract->client_id,
                        'logement_id' => $contract->logement_id,
                        'amount' => $contract->amount,
                        'start_date' => $contract->start_date,
                        'end_date' => $contract->end_date,
                        'client_name' => $contract->client->name ?? 'N/A',
                        'logement_title' => $contract->logement->title ?? 'N/A',
                    ];
                }),
                'analytics' => [
                    'total' => $count,
                    'total_value' => $rows->sum('amount'),
                ],
                'sources' => ['contrats'],
                'suggestions' => [
                    'Voir les contrats de ce mois',
                    'Contrats en attente',
                    'Exporter les données',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('ContratHandler.getAllContracts error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de la récupération des contrats.",
                'data' => [],
                'analytics' => [],
                'sources' => ['contrats'],
                'suggestions' => ['Réessayer'],
            ];
        }
    }

    private function signedThisMonth(): array
    {
        try {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();

            // Enhanced query with relationships - use created_at since signed_at doesn't exist
            $rows = Contract::query()
                ->with(['client', 'logement'])
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->get();

            $count = $rows->count();
        } catch (\Throwable $e) {
            Log::error('ContratHandler.signedThisMonth error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de la récupération des contrats de ce mois.",
                'data' => [],
                'analytics' => [],
                'sources' => ['contrats'],
                'suggestions' => ['Réessayer', 'Vérifier les données'],
            ];
        }
        
        // Calculate analytics
        $analytics = [
            'total' => $count,
            'total_value' => $rows->sum('amount'),
        ];
        
        if ($count > 0) {
            $analytics['avg_value'] = round($rows->avg('amount'), 2);
            $analytics['min_value'] = $rows->min('amount');
            $analytics['max_value'] = $rows->max('amount');
            
            // Group by week
            $byWeek = $rows->groupBy(function($contract) {
                return $contract->created_at ? Carbon::parse($contract->created_at)->format('W') : 'unknown';
            })->map->count();
            $analytics['by_week'] = $byWeek->toArray();
        }

        return [
            'answer' => "{$count} contrat(s) signé(s) ce mois-ci" . 
                       (isset($analytics['total_value']) ? " pour un total de " . number_format($analytics['total_value'], 2, ',', ' ') . " TND." : "."),
            'data' => $rows->map(function($contract) {
                return [
                    '_id' => $contract->_id,
                    'client_id' => $contract->client_id,
                    'logement_id' => $contract->logement_id,
                    'amount' => $contract->amount,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'client_name' => $contract->client->name ?? 'N/A',
                    'logement_title' => $contract->logement->title ?? 'N/A',
                ];
            }),
            'analytics' => $analytics,
            'sources' => ['contrats'],
            'suggestions' => [
                'Analyser les tendances contractuelles',
                'Prévoir les revenus futurs',
                'Exporter les données',
            ],
        ];
    }

    private function pendingSignature(): array
    {
        try {
            // Enhanced query with relationships
            // Since status field doesn't exist, we'll return contracts without payments
            // or contracts created recently (last 30 days) as "pending"
            $allRows = Contract::query()
                ->with(['client', 'logement', 'payment'])
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->get();
                
            // Filter contracts without payments
            $rows = $allRows->filter(function($contract) {
                // Consider contracts without payments as pending
                try {
                    return !$contract->payment;
                } catch (\Throwable $e) {
                    // If payment relationship fails, consider it as pending
                    return true;
                }
            })->values(); // Re-index the collection

            $count = $rows->count();
        } catch (\Throwable $e) {
            Log::error('ContratHandler.pendingSignature error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de la récupération des contrats en attente.",
                'data' => [],
                'analytics' => [],
                'sources' => ['contrats'],
                'suggestions' => ['Réessayer', 'Vérifier les données'],
            ];
        }
        
        // Calculate analytics
        $analytics = [
            'total' => $count,
            'total_potential_value' => $rows->sum('amount'),
        ];
        
        if ($count > 0) {
            $analytics['avg_potential_value'] = round($rows->avg('amount'), 2);
            
            // Days pending analysis
            $now = Carbon::now();
            $daysPending = $rows->map(function($contract) use ($now) {
                try {
                    return $contract->created_at ? $now->diffInDays($contract->created_at) : 0;
                } catch (\Throwable $e) {
                    return 0;
                }
            });
            $analytics['avg_days_pending'] = round($daysPending->avg(), 1);
            $analytics['oldest_pending_days'] = $daysPending->max();
        }

        return [
            'answer' => "{$count} contrat(s) en attente de signature" . 
                       (isset($analytics['total_potential_value']) ? " pour une valeur potentielle de " . number_format($analytics['total_potential_value'], 2, ',', ' ') . " TND." : "."),
            'data' => $rows->map(function($contract) {
                try {
                    return [
                        '_id' => $contract->_id,
                        'client_id' => $contract->client_id,
                        'logement_id' => $contract->logement_id,
                        'amount' => $contract->amount,
                        'has_payment' => isset($contract->payment) && $contract->payment ? true : false,
                        'client_name' => $contract->client->name ?? 'N/A',
                        'logement_title' => $contract->logement->title ?? 'N/A',
                        'created_at' => $contract->created_at,
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $contract->_id ?? 'N/A',
                        'client_id' => $contract->client_id ?? 'N/A',
                        'logement_id' => $contract->logement_id ?? 'N/A',
                        'amount' => $contract->amount ?? 0,
                        'has_payment' => false,
                        'client_name' => 'N/A',
                        'logement_title' => 'N/A',
                        'created_at' => $contract->created_at ?? null,
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['contrats'],
            'suggestions' => [
                'Suivre les contrats en cours',
                'Anticiper les renouvellements',
                'Contacter les clients en attente',
            ],
        ];
    }

    private function detailsById(string $id): array
    {
        $row = Contract::find($id);

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
