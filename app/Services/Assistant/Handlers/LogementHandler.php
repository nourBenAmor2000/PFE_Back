<?php

namespace App\Services\Assistant\Handlers;

use Modules\Logement\App\Models\Logement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class LogementHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'logement';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['logement', 'logements', 'property', 'properties']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        $q = Logement::query();
        
        // Enhanced filtering
        if (($intent['filters']['status'] ?? null) === 'available') {
            $q->where('free', true);
        }
        if (($intent['filters']['free'] ?? null) !== null) {
            $q->where('free', (bool)$intent['filters']['free']);
        }
        if (isset($intent['filters']['price_min'])) {
            $q->where('price', '>=', (float)$intent['filters']['price_min']);
        }
        if (isset($intent['filters']['price_max'])) {
            $q->where('price', '<=', (float)$intent['filters']['price_max']);
        }
        if (isset($intent['filters']['category_id'])) {
            $q->where('category_id', $intent['filters']['category_id']);
        }
        if (isset($intent['filters']['agency_id'])) {
            $q->where('agency_id', $intent['filters']['agency_id']);
        }
        
        // Count query
        if (($intent['type'] ?? 'list') === 'count') {
            $count = $q->count();
            $available = Logement::where('free', true)->count();
            $total = Logement::count();
            
            return [
                'answer' => "Total: {$total} logement(s), dont {$available} disponible(s) et {$count} correspondant aux filtres.",
                'data' => [
                    'count' => $count,
                    'total' => $total,
                    'available' => $available,
                    'occupied' => $total - $available,
                ],
                'analytics' => [
                    'occupancy_rate' => $total > 0 ? round((($total - $available) / $total) * 100, 2) : 0,
                ],
                'sources' => ['logements'],
                'suggestions' => [
                    'Voir la liste complète',
                    'Analyser le marché',
                    'Optimiser la disponibilité',
                ],
            ];
        }
        
        // List query with ALL relationships for comprehensive data
        $limit = $intent['type'] === 'analyze' ? 200 : 100;
        try {
            $logements = $q->with([
                'agency:id,name,city,address,phone,location',
                'category:id,name',
                'visits:id,logement_id,client_id,visit_date',
                'contracts:id,logement_id,client_id,start_date,end_date,amount',
                'clients:id,name,email,phone',
                'reviews:id,logement_id,client_id,rating,comment'
            ])->latest()->limit($limit)->get();
        } catch (\Throwable $e) {
            Log::warning('LogementHandler: Failed to load relationships', ['error' => $e->getMessage()]);
            try {
                $logements = $q->with(['agency', 'category'])->latest()->limit($limit)->get();
            } catch (\Throwable $e2) {
                $logements = $q->latest()->limit($limit)->get();
            }
        }
        $count = $logements->count();
        
        // Calculate analytics
        $analytics = [
            'total' => $count,
            'available' => $logements->where('free', true)->count(),
            'occupied' => $logements->where('free', false)->count(),
        ];
        
        if ($count > 0) {
            $prices = $logements->pluck('price')->filter();
            if ($prices->isNotEmpty()) {
                $analytics['price_avg'] = $prices->avg();
                $analytics['price_min'] = $prices->min();
                $analytics['price_max'] = $prices->max();
                $analytics['price_median'] = $prices->median();
            }
            
            // Group by category
            $byCategory = $logements->groupBy('category_id')->map->count();
            $analytics['by_category'] = $byCategory->toArray();
            
            // Group by agency
            $byAgency = $logements->groupBy('agency_id')->map->count();
            $analytics['by_agency'] = $byAgency->toArray();
        }
        
        // Build detailed answer
        $answer = "{$count} logement(s) trouvé(s).";
        if (isset($analytics['price_avg'])) {
            $answer .= " Prix moyen: " . number_format($analytics['price_avg'], 0, ',', ' ') . " TND.";
        }
        if (isset($analytics['available'])) {
            $answer .= " {$analytics['available']} disponible(s).";
        }
        
        return [
            'answer' => $answer,
            'data' => $logements->map(function($logement) {
                try {
                    return [
                        '_id' => $logement->_id,
                        'title' => $logement->title,
                        'description' => $logement->description ?? '',
                        'price' => $logement->price,
                        'location' => $logement->location,
                        'latitude' => $logement->latitude,
                        'longitude' => $logement->longitude,
                        'surface' => $logement->surface ?? null,
                        'floor' => $logement->floor ?? null,
                        'free' => $logement->free,
                        'category' => $logement->category ? [
                            'id' => $logement->category->_id ?? null,
                            'name' => $logement->category->name ?? 'N/A',
                        ] : null,
                        'agency' => $logement->agency ? [
                            'id' => $logement->agency->_id ?? null,
                            'name' => $logement->agency->name ?? 'N/A',
                            'city' => $logement->agency->city ?? null,
                            'address' => $logement->agency->address ?? null,
                            'phone' => $logement->agency->phone ?? null,
                        ] : null,
                        'stats' => [
                            'visits_count' => $logement->visits ? $logement->visits->count() : 0,
                            'contracts_count' => $logement->contracts ? $logement->contracts->count() : 0,
                            'reviews_count' => $logement->reviews ? $logement->reviews->count() : 0,
                            'avg_rating' => $logement->reviews && $logement->reviews->count() > 0 
                                ? round($logement->reviews->avg('rating'), 1) 
                                : null,
                        ],
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $logement->_id ?? 'N/A',
                        'title' => $logement->title ?? 'N/A',
                        'description' => $logement->description ?? '',
                        'price' => $logement->price ?? 0,
                        'location' => $logement->location ?? 'N/A',
                        'latitude' => $logement->latitude ?? null,
                        'longitude' => $logement->longitude ?? null,
                        'surface' => $logement->surface ?? null,
                        'free' => $logement->free ?? false,
                        'category' => null,
                        'agency' => null,
                        'stats' => [],
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['logements'],
            'suggestions' => $this->generateContextualSuggestions($analytics, $count),
        ];
    }
    
    /**
     * Generate contextual suggestions based on logement analytics
     */
    private function generateContextualSuggestions(array $analytics, int $count): array
    {
        $suggestions = [];
        
        if ($count > 0) {
            if (isset($analytics['occupancy_rate'])) {
                if ($analytics['occupancy_rate'] < 50) {
                    $suggestions[] = "Comment améliorer le taux d'occupation de " . number_format($analytics['occupancy_rate'], 1) . "%?";
                } else {
                    $suggestions[] = "Comment maintenir ce taux d'occupation élevé?";
                }
            }
            if (isset($analytics['price_avg']) && isset($analytics['price_min']) && isset($analytics['price_max'])) {
                $suggestions[] = "Quels logements sont au-dessus de la moyenne (" . number_format($analytics['price_avg'], 0, ',', ' ') . " TND)?";
            }
        }
        
        $suggestions = array_merge($suggestions, [
            'Quels sont les logements les plus chers?',
            'Quels logements sont disponibles depuis le plus longtemps?',
            'Quelle est la performance des logements par catégorie?',
            'Quels logements ont le meilleur taux de conversion?',
            'Quelle est la répartition des logements par ville?',
            'Quels logements ont les meilleures notes?',
            'Quels logements ont le plus de visites?',
            'Quels logements génèrent le plus de revenus?',
        ]);
        
        return array_slice($suggestions, 0, 8);
    }
}