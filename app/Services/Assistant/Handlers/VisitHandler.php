<?php
declare(strict_types=1);

namespace App\Services\Assistant\Handlers;

use Modules\Visit\App\Models\Visit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Auth;

final class VisitHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'visit';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['visit', 'visits', 'visite', 'visites']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        // 🔎 debug: combien au total ?
        $total = Visit::count();

        $q = Visit::query();

        // RBAC (désactivé si pas de agency_id dans tes docs)
        // $agencyId = auth()->user()?->agency_id;
        // if ($agencyId) $q->where('agency_id', $agencyId);

        // ⏱ timeframe avec support amélioré
        $tf = $intent['timeframe'] ?? null;
        $tz = 'UTC';
        
        if ($tf === 'today') {
            $day = now($tz)->format('Y-m-d');
            $q->where('visit_date', '>=', $day.' 00:00:00')
              ->where('visit_date', '<=', $day.' 23:59:59');
        } elseif ($tf === 'week') {
            $start = now($tz)->startOfWeek()->format('Y-m-d H:i:s');
            $end = now($tz)->endOfWeek()->format('Y-m-d H:i:s');
            $q->where('visit_date', '>=', $start)
              ->where('visit_date', '<=', $end);
        } elseif ($tf === 'month') {
            $start = now($tz)->startOfMonth()->format('Y-m-d H:i:s');
            $end = now($tz)->endOfMonth()->format('Y-m-d H:i:s');
            $q->where('visit_date', '>=', $start)
              ->where('visit_date', '<=', $end);
        } elseif ($tf === 'year') {
            $start = now($tz)->startOfYear()->format('Y-m-d H:i:s');
            $end = now($tz)->endOfYear()->format('Y-m-d H:i:s');
            $q->where('visit_date', '>=', $start)
              ->where('visit_date', '<=', $end);
        }
        
        // Filtres supplémentaires
        $filters = $intent['filters'] ?? [];
        if (isset($filters['client_id'])) {
            $q->where('client_id', $filters['client_id']);
        }
        if (isset($filters['logement_id'])) {
            $q->where('logement_id', $filters['logement_id']);
        }
        if (isset($filters['agency_id'])) {
            // Filter via logement relationship
            $q->whereHas('logement', function($query) use ($filters) {
                $query->where('agency_id', $filters['agency_id']);
            });
        }

        // Enhanced query with relationships and analytics
        $limit = $intent['type'] === 'count' ? 1000 : 100; // More data for count queries
        $rows = $q->orderBy('visit_date', 'desc')
                  ->limit($limit)
                  ->get();

        $todayCount = $rows->count();
        
        // Calculate analytics
        $analytics = [
            'total' => $total,
            'filtered' => $todayCount,
            'timeframe' => $tf,
        ];
        
        // Load ALL relationships for comprehensive data
        try {
            $rows->load([
                'client:id,name,email,phone',
                'logement:id,title,price,location,latitude,longitude,free,surface,agency_id,category_id',
                'logement.agency:id,name,city,address',
                'logement.category:id,name'
            ]);
        } catch (\Throwable $e) {
            // Fallback: try simpler relationships
            try {
                $rows->load(['client', 'logement']);
            } catch (\Throwable $e2) {
                Log::warning('VisitHandler: Failed to load relationships', ['error' => $e2->getMessage()]);
            }
        }
        
        // Calculate additional metrics
        if ($todayCount > 0) {
            try {
                $uniqueClients = $rows->pluck('client_id')->unique()->count();
                $uniqueLogements = $rows->pluck('logement_id')->unique()->count();
                $analytics['unique_clients'] = $uniqueClients;
                $analytics['unique_logements'] = $uniqueLogements;
                $analytics['avg_visits_per_client'] = $uniqueClients > 0 ? round($todayCount / $uniqueClients, 2) : 0;
                
                // Group by hour for time analysis
                $byHour = [];
                foreach ($rows as $visit) {
                    try {
                        if ($visit->visit_date) {
                            $hour = date('H', strtotime($visit->visit_date));
                            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;
                        }
                    } catch (\Throwable $e) {
                        // Skip invalid dates
                        continue;
                    }
                }
                $analytics['peak_hour'] = !empty($byHour) ? array_search(max($byHour), $byHour) : null;
            } catch (\Throwable $e) {
                Log::warning('VisitHandler: Error calculating metrics', ['error' => $e->getMessage()]);
            }
        }

        // Handle count type
        if ($intent['type'] === 'count') {
            return [
                'answer' => "Nombre total de visites: {$total}. " . 
                           ($tf ? "Pour la période sélectionnée: {$todayCount}." : ""),
                'data' => [],
                'analytics' => array_merge($analytics, ['count' => $total, 'filtered_count' => $todayCount]),
                'sources' => ['visits'],
                'suggestions' => [
                    'Voir les détails des visites',
                    'Analyser les tendances',
                    'Exporter les données',
                ],
            ];
        }
        
        if ($todayCount === 0) {
            $timeframeText = $tf ? "pour " . ($tf === 'today' ? "aujourd'hui" : "la période sélectionnée") : "";
            return [
                'answer' => "Aucune visite trouvée {$timeframeText}.",
                'data' => [],
                'analytics' => $analytics,
                'sources' => ['visits'],
                'suggestions' => [
                    'Voir les visites de la semaine',
                    'Créer une nouvelle visite',
                    'Analyser les tendances',
                ],
            ];
        }

        // Build detailed answer with comprehensive insights
        $insights = [];
        if (isset($analytics['unique_clients'])) {
            $insights[] = "{$analytics['unique_clients']} client(s) unique(s)";
        }
        if (isset($analytics['unique_logements'])) {
            $insights[] = "{$analytics['unique_logements']} logement(s) visité(s)";
        }
        if (isset($analytics['peak_hour'])) {
            $insights[] = "Heure de pointe: {$analytics['peak_hour']}h";
        }
        if (isset($analytics['avg_visits_per_client']) && $analytics['avg_visits_per_client'] > 0) {
            $insights[] = "Moyenne: " . number_format($analytics['avg_visits_per_client'], 1) . " visite(s)/client";
        }

        $timeframeText = $tf ? ($tf === 'today' ? "aujourd'hui" : "pour la période sélectionnée") : "";
        $answer = "{$todayCount} visite(s) trouvée(s) {$timeframeText}.";
        if (!empty($insights)) {
            $answer .= " " . implode(', ', $insights) . ".";
        }

        return [
            'answer' => $answer,
            'data' => $rows->map(function($visit) {
                try {
                    $logement = $visit->logement ?? null;
                    $client = $visit->client ?? null;
                    
                    return [
                        '_id' => $visit->_id,
                        'client_id' => $visit->client_id,
                        'logement_id' => $visit->logement_id,
                        'visit_date' => $visit->visit_date,
                        // Client data
                        'client' => $client ? [
                            'id' => $client->_id ?? null,
                            'name' => $client->name ?? 'N/A',
                            'email' => $client->email ?? null,
                            'phone' => $client->phone ?? null,
                        ] : null,
                        // Logement data
                        'logement' => $logement ? [
                            'id' => $logement->_id ?? null,
                            'title' => $logement->title ?? 'N/A',
                            'price' => $logement->price ?? null,
                            'location' => $logement->location ?? null,
                            'latitude' => $logement->latitude ?? null,
                            'longitude' => $logement->longitude ?? null,
                            'free' => $logement->free ?? null,
                            'surface' => $logement->surface ?? null,
                            'agency' => $logement->agency ? [
                                'id' => $logement->agency->_id ?? null,
                                'name' => $logement->agency->name ?? 'N/A',
                                'city' => $logement->agency->city ?? null,
                            ] : null,
                            'category' => $logement->category ? [
                                'id' => $logement->category->_id ?? null,
                                'name' => $logement->category->name ?? 'N/A',
                            ] : null,
                        ] : null,
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $visit->_id ?? 'N/A',
                        'client_id' => $visit->client_id ?? 'N/A',
                        'logement_id' => $visit->logement_id ?? 'N/A',
                        'visit_date' => $visit->visit_date ?? null,
                        'client' => null,
                        'logement' => null,
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['visits'],
            'suggestions' => $this->generateContextualSuggestions($analytics, $todayCount, $tf),
        ];
    }
    
    /**
     * Generate contextual suggestions based on visit analytics
     */
    private function generateContextualSuggestions(array $analytics, int $count, ?string $timeframe): array
    {
        $suggestions = [];
        
        if ($count > 0) {
            if (isset($analytics['unique_clients']) && $analytics['unique_clients'] > 0) {
                $suggestions[] = "Quels sont les {$analytics['unique_clients']} clients qui ont visité?";
            }
            if (isset($analytics['unique_logements']) && $analytics['unique_logements'] > 0) {
                $suggestions[] = "Quels sont les {$analytics['unique_logements']} logements visités?";
            }
            if (isset($analytics['peak_hour'])) {
                $suggestions[] = "Pourquoi l'heure de pointe est à {$analytics['peak_hour']}h?";
            }
        }
        
        $timeframeText = $timeframe ? "ce {$timeframe}" : "ce mois";
        $suggestions = array_merge($suggestions, [
            "Quels sont les logements les plus visités {$timeframeText}?",
            'Quel est le taux de conversion visites-contrats?',
            'Quels clients ont le plus de visites?',
            'Quelle est la répartition des visites par agence?',
            'Quels sont les créneaux horaires les plus fréquentés?',
            'Quels logements n\'ont reçu aucune visite?',
            'Quelle est l\'évolution du nombre de visites sur 3 mois?',
            'Quels agents ont organisé le plus de visites?',
        ]);
        
        return array_slice($suggestions, 0, 8);
    }
}
