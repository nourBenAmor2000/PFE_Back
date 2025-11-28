<?php

namespace App\Services\Assistant\Handlers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
// adapte le namespace du modèle à ton projet (module Clients)
use Modules\Client\App\Models\Client;

class ClientHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'client';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['client', 'clients']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        $q = Client::query();

        // Filtre "actifs" si l'intent le demande
        $onlyActive = ($intent['filters']['active'] ?? false) === true
                   || ($intent['filters']['status'] ?? null) === 'active';

        // Vérifier si le modèle a un champ 'active' ou 'status'
        try {
            $sample = Client::first();
            if ($sample && isset($sample->active)) {
                // Utiliser le champ booléen 'active'
                if ($onlyActive) {
                    $q->where('active', true);
                }
            } elseif ($sample && isset($sample->status)) {
                // Utiliser le champ 'status'
                if ($onlyActive) {
                    $q->where('status', 'active');
                }
            }
        } catch (\Throwable $e) {
            // Si erreur, continuer sans filtre
        }

        // Filtrer par timeframe si présent
        $timeframe = $intent['timeframe'] ?? null;
        if ($timeframe === 'today') {
            $q->whereDate('created_at', today());
        } elseif ($timeframe === 'week') {
            $q->where('created_at', '>=', now()->startOfWeek());
        } elseif ($timeframe === 'month') {
            $q->where('created_at', '>=', now()->startOfMonth());
        }

        // Type de requête
        $type = $intent['type'] ?? 'list';
        if ($type === 'count') {
            $count = $q->count();
            return [
                'answer' => $onlyActive
                    ? "Il y a {$count} client(s) actif(s)."
                    : "Total clients : {$count}.",
                'data' => ['count' => $count],
                'sources' => ['clients'],
                'suggestions' => ['Voir la liste complète', 'Exporter en CSV'],
            ];
        }

        // Liste des clients with ALL relationships for comprehensive data
        $limit = $intent['type'] === 'analyze' ? 200 : 100;
        try {
            $clients = $q->with([
                'visits:id,client_id,logement_id,visit_date',
                'contracts:id,client_id,logement_id,start_date,end_date,amount',
                'logements:id,title,price,location,free',
                'reviews:id,client_id,logement_id,rating,comment'
            ])->orderBy('created_at', 'desc')->limit($limit)->get();
        } catch (\Throwable $e) {
            Log::warning('ClientHandler: Failed to load relationships', ['error' => $e->getMessage()]);
            try {
                $clients = $q->with(['visits', 'contracts'])->orderBy('created_at', 'desc')->limit($limit)->get();
            } catch (\Throwable $e2) {
                $clients = $q->orderBy('created_at', 'desc')->limit($limit)->get();
            }
        }
        $count = $clients->count();
        
        // Calculate analytics
        $analytics = [
            'total' => $count,
        ];
        
        if ($count > 0) {
            try {
                $withVisits = $clients->filter(function($c) {
                    try {
                        return $c->visits && $c->visits->isNotEmpty();
                    } catch (\Throwable $e) {
                        return false;
                    }
                })->count();
                $withContracts = $clients->filter(function($c) {
                    try {
                        return $c->contracts && $c->contracts->isNotEmpty();
                    } catch (\Throwable $e) {
                        return false;
                    }
                })->count();
                
                $analytics['with_visits'] = $withVisits;
                $analytics['with_contracts'] = $withContracts;
                
                $totalVisits = $clients->sum(function($c) {
                    try {
                        return $c->visits ? $c->visits->count() : 0;
                    } catch (\Throwable $e) {
                        return 0;
                    }
                });
                $totalContracts = $clients->sum(function($c) {
                    try {
                        return $c->contracts ? $c->contracts->count() : 0;
                    } catch (\Throwable $e) {
                        return 0;
                    }
                });
                $analytics['avg_visits_per_client'] = round($totalVisits / $count, 2);
                $analytics['avg_contracts_per_client'] = round($totalContracts / $count, 2);
                $analytics['conversion_rate'] = $withVisits > 0 
                    ? round(($withContracts / $withVisits) * 100, 2) 
                    : 0;
            } catch (\Throwable $e) {
                Log::warning('ClientHandler: Error calculating analytics', ['error' => $e->getMessage()]);
            }
        }

        // Build detailed answer
        $answer = $count > 0
            ? "{$count} client(s) trouvé(s)."
            : "Aucun client trouvé.";
            
        if (isset($analytics['with_contracts'])) {
            $answer .= " {$analytics['with_contracts']} avec contrat(s).";
        }
        if (isset($analytics['conversion_rate']) && $analytics['conversion_rate'] > 0) {
            $answer .= " Taux de conversion: {$analytics['conversion_rate']}%.";
        }

        return [
            'answer' => $answer,
            'data' => $clients->map(function($client) {
                try {
                    $visits = $client->visits ?? collect([]);
                    $contracts = $client->contracts ?? collect([]);
                    $reviews = $client->reviews ?? collect([]);
                    
                    return [
                        '_id' => $client->_id,
                        'name' => $client->name,
                        'email' => $client->email,
                        'phone' => $client->phone ?? null,
                        'username' => $client->username ?? null,
                        'created_at' => $client->created_at ?? null,
                        'stats' => [
                            'visits_count' => $visits->count(),
                            'contracts_count' => $contracts->count(),
                            'reviews_count' => $reviews->count(),
                            'total_contract_value' => $contracts->sum('amount') ?? 0,
                            'avg_rating_given' => $reviews->count() > 0 ? round($reviews->avg('rating'), 1) : null,
                        ],
                        'recent_visits' => $visits->take(5)->map(function($visit) {
                            return [
                                'id' => $visit->_id ?? null,
                                'date' => $visit->visit_date ?? null,
                                'logement_id' => $visit->logement_id ?? null,
                            ];
                        })->values(),
                        'active_contracts' => $contracts->filter(function($contract) {
                            return !isset($contract->end_date) || 
                                   ($contract->end_date && strtotime($contract->end_date) > time());
                        })->take(5)->map(function($contract) {
                            return [
                                'id' => $contract->_id ?? null,
                                'amount' => $contract->amount ?? null,
                                'start_date' => $contract->start_date ?? null,
                                'end_date' => $contract->end_date ?? null,
                            ];
                        })->values(),
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $client->_id ?? 'N/A',
                        'name' => $client->name ?? 'N/A',
                        'email' => $client->email ?? 'N/A',
                        'phone' => $client->phone ?? null,
                        'stats' => [
                            'visits_count' => 0,
                            'contracts_count' => 0,
                            'reviews_count' => 0,
                            'total_contract_value' => 0,
                        ],
                        'recent_visits' => [],
                        'active_contracts' => [],
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['clients'],
            'suggestions' => $this->generateContextualSuggestions($analytics, $count),
        ];
    }
    
    /**
     * Generate contextual suggestions based on client analytics
     */
    private function generateContextualSuggestions(array $analytics, int $count): array
    {
        $suggestions = [];
        
        if ($count > 0) {
            if (isset($analytics['conversion_rate'])) {
                if ($analytics['conversion_rate'] < 20) {
                    $suggestions[] = "Comment améliorer le taux de conversion de " . number_format($analytics['conversion_rate'], 1) . "%?";
                }
            }
            if (isset($analytics['with_contracts']) && isset($analytics['total'])) {
                $withoutContracts = $analytics['total'] - $analytics['with_contracts'];
                if ($withoutContracts > 0) {
                    $suggestions[] = "Pourquoi {$withoutContracts} client(s) n'ont pas de contrat?";
                }
            }
        }
        
        $suggestions = array_merge($suggestions, [
            'Quels clients ont le plus de contrats?',
            'Quels sont les clients les plus fidèles?',
            'Quels clients n\'ont pas de contrat?',
            'Quelle est la valeur totale des contrats par client?',
            'Quels clients ont le meilleur taux de conversion?',
            'Quels clients n\'ont fait aucune visite?',
            'Quels clients génèrent le plus de revenus?',
            'Quels sont les clients à risque de départ?',
        ]);
        
        return array_slice($suggestions, 0, 8);
    }
}

