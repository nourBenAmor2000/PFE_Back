<?php

namespace App\Services\Assistant\Handlers;

use Modules\Visit\App\Models\Visit;
use Modules\Logement\App\Models\Logement;
use Modules\Client\App\Models\Client;
use Modules\Contract\App\Models\Contract;
use Modules\PaymentContracts\App\Models\PaymentContracts;
use Modules\Agency\App\Models\Agency;
use Modules\Agent\App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Analytics Handler - Provides cross-entity insights and business intelligence
 */
class AnalyticsHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'analytics';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['analytics', 'analyse', 'statistiques', 'stats', 'revenus', 'performance', 'conversion', 'marché', 'market']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        try {
            $query = mb_strtolower($intent['original_query'] ?? '');
            $analysisType = $intent['filters']['type'] ?? null;
            
            // Detect analysis type from query
            if (!$analysisType) {
                if (str_contains($query, 'revenu') || str_contains($query, 'revenue') || str_contains($query, 'financier')) {
                    $analysisType = 'revenue';
                } elseif (str_contains($query, 'performance') || str_contains($query, 'performant')) {
                    $analysisType = 'performance';
                } elseif (str_contains($query, 'conversion') || str_contains($query, 'convertir')) {
                    $analysisType = 'conversion';
                } elseif (str_contains($query, 'marché') || str_contains($query, 'market')) {
                    $analysisType = 'market';
                } else {
                    $analysisType = 'overview';
                }
            }
            
            switch ($analysisType) {
                case 'revenue':
                    return $this->revenueAnalysis($intent);
                case 'performance':
                    return $this->performanceAnalysis($intent);
                case 'conversion':
                    return $this->conversionAnalysis($intent);
                case 'market':
                    return $this->marketAnalysis($intent);
                default:
                    return $this->overviewAnalysis($intent);
            }
        } catch (\Throwable $e) {
            Log::error('AnalyticsHandler.handle error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'intent' => $intent,
            ]);
            
            return [
                'answer' => "Erreur lors de l'analyse: " . $e->getMessage(),
                'data' => [],
                'analytics' => [],
                'sources' => ['analytics'],
                'suggestions' => ['Réessayer', 'Vérifier les données'],
            ];
        }
    }
    
    private function overviewAnalysis(array $intent): array
    {
        try {
            $timeframe = $intent['timeframe'] ?? 'month';
            $startDate = $this->getStartDate($timeframe);
            
            // Collect all metrics with error handling
            $metrics = [];
            try {
                $metrics['total_agencies'] = Agency::count();
            } catch (\Throwable $e) {
                $metrics['total_agencies'] = 0;
                Log::warning('AnalyticsHandler: Failed to count agencies', ['error' => $e->getMessage()]);
            }
            
            try {
                $metrics['total_agents'] = Agent::count();
            } catch (\Throwable $e) {
                $metrics['total_agents'] = 0;
            }
            
            try {
                $metrics['total_clients'] = Client::count();
            } catch (\Throwable $e) {
                $metrics['total_clients'] = 0;
            }
            
            try {
                $metrics['total_logements'] = Logement::count();
                $metrics['available_logements'] = Logement::where('free', true)->count();
            } catch (\Throwable $e) {
                $metrics['total_logements'] = 0;
                $metrics['available_logements'] = 0;
            }
            
            try {
                $metrics['total_visits'] = Visit::where('visit_date', '>=', $startDate)->count();
            } catch (\Throwable $e) {
                $metrics['total_visits'] = 0;
            }
            
            try {
                $metrics['total_contracts'] = Contract::where('start_date', '>=', $startDate)->count();
            } catch (\Throwable $e) {
                $metrics['total_contracts'] = 0;
            }
            
            try {
                $metrics['total_revenue'] = PaymentContracts::where('date_paiement', '>=', $startDate)->sum('montant') ?? 0;
            } catch (\Throwable $e) {
                $metrics['total_revenue'] = 0;
            }
        
            // Calculate rates
            $metrics['occupancy_rate'] = ($metrics['total_logements'] ?? 0) > 0 
                ? round((($metrics['total_logements'] - ($metrics['available_logements'] ?? 0)) / $metrics['total_logements']) * 100, 2)
                : 0;
            
            $metrics['conversion_rate'] = ($metrics['total_visits'] ?? 0) > 0
                ? round((($metrics['total_contracts'] ?? 0) / $metrics['total_visits']) * 100, 2)
                : 0;
            
            try {
                $trends = $this->calculateTrends($timeframe);
            } catch (\Throwable $e) {
                $trends = [];
                Log::warning('AnalyticsHandler: Failed to calculate trends', ['error' => $e->getMessage()]);
            }
            
            return [
                'answer' => "Vue d'ensemble du système: " . ($metrics['total_agencies'] ?? 0) . " agence(s), " . ($metrics['total_clients'] ?? 0) . " client(s), " . ($metrics['total_logements'] ?? 0) . " logement(s). " .
                           "Taux d'occupation: {$metrics['occupancy_rate']}%. " .
                           "Revenus ({$timeframe}): " . number_format($metrics['total_revenue'] ?? 0, 2, ',', ' ') . " TND.",
                'data' => $metrics,
                'analytics' => [
                    'metrics' => $metrics,
                    'timeframe' => $timeframe,
                    'trends' => $trends,
                ],
                'sources' => ['analytics', 'agencies', 'clients', 'logements', 'visits', 'contracts', 'payments'],
                'suggestions' => [
                    'Analyser les revenus détaillés',
                    'Évaluer la performance',
                    'Optimiser la conversion',
                    'Exporter le rapport complet',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('AnalyticsHandler.overviewAnalysis error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de l'analyse globale.",
                'data' => [],
                'analytics' => [],
                'sources' => ['analytics'],
                'suggestions' => ['Réessayer'],
            ];
        }
    }
    
    private function revenueAnalysis(array $intent): array
    {
        try {
            $timeframe = $intent['timeframe'] ?? 'month';
            $startDate = $this->getStartDate($timeframe);
            
            try {
                $payments = PaymentContracts::where('date_paiement', '>=', $startDate)->get();
            } catch (\Throwable $e) {
                Log::warning('AnalyticsHandler: Failed to load payments', ['error' => $e->getMessage()]);
                $payments = collect([]);
            }
            
            $analytics = [
                'total' => $payments->sum('montant') ?? 0,
                'count' => $payments->count(),
                'avg' => $payments->count() > 0 ? ($payments->avg('montant') ?? 0) : 0,
            ];
            
            try {
                $analytics['by_method'] = $payments->groupBy('methode_paiement')->map->sum('montant')->toArray();
            } catch (\Throwable $e) {
                $analytics['by_method'] = [];
            }
            
            try {
                $analytics['by_status'] = $payments->groupBy('statut')->map(function($group) {
                    return [
                        'count' => $group->count(),
                        'total' => $group->sum('montant') ?? 0,
                    ];
                })->toArray();
            } catch (\Throwable $e) {
                $analytics['by_status'] = [];
            }
            
            // Monthly breakdown
            try {
                $byMonth = $payments->groupBy(function($payment) {
                    try {
                        return $payment->date_paiement ? Carbon::parse($payment->date_paiement)->format('Y-m') : 'unknown';
                    } catch (\Throwable $e) {
                        return 'unknown';
                    }
                })->map->sum('montant');
                $analytics['by_month'] = $byMonth->toArray();
                
                try {
                    $analytics['growth'] = $this->calculateGrowth($byMonth);
                } catch (\Throwable $e) {
                    $analytics['growth'] = 0;
                }
            } catch (\Throwable $e) {
                $analytics['by_month'] = [];
                $analytics['growth'] = 0;
            }
        
            return [
                'answer' => "Analyse des revenus ({$timeframe}): Total de " . number_format($analytics['total'] ?? 0, 2, ',', ' ') . " TND " .
                           "sur " . ($analytics['count'] ?? 0) . " paiement(s). " .
                           "Moyenne: " . number_format($analytics['avg'] ?? 0, 2, ',', ' ') . " TND.",
                'data' => $payments->take(20),
                'analytics' => $analytics,
                'sources' => ['payments', 'analytics'],
                'suggestions' => [
                    'Prévoir les revenus futurs',
                    'Optimiser les méthodes de paiement',
                    'Analyser les tendances',
                    'Exporter le rapport financier',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('AnalyticsHandler.revenueAnalysis error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de l'analyse des revenus.",
                'data' => [],
                'analytics' => [],
                'sources' => ['analytics'],
                'suggestions' => ['Réessayer'],
            ];
        }
    }
    
    private function performanceAnalysis(array $intent): array
    {
        try {
            try {
                $agencies = Agency::with(['agents', 'logements'])->get();
            } catch (\Throwable $e) {
                Log::warning('AnalyticsHandler: Failed to load agencies with relationships', ['error' => $e->getMessage()]);
                $agencies = Agency::get();
            }
        
        $performance = $agencies->map(function($agency) {
            $logements = $agency->logements;
            $available = $logements->where('free', true)->count();
            $total = $logements->count();
            
            return [
                'name' => $agency->name,
                'agents_count' => $agency->agents->count(),
                'logements_count' => $total,
                'occupancy_rate' => $total > 0 ? round((($total - $available) / $total) * 100, 2) : 0,
                'avg_price' => $logements->avg('price'),
            ];
        })->sortByDesc('occupancy_rate');
        
        $analytics = [
            'top_performers' => $performance->take(5)->values()->toArray(),
            'avg_occupancy' => $performance->avg('occupancy_rate'),
            'total_agencies' => $agencies->count(),
        ];
        
            return [
                'answer' => "Analyse de performance: " . ($analytics['total_agencies'] ?? 0) . " agence(s) analysée(s). " .
                           "Taux d'occupation moyen: " . number_format($analytics['avg_occupancy'] ?? 0, 2, ',', ' ') . "%.",
                'data' => $performance->values(),
                'analytics' => $analytics,
                'sources' => ['agencies', 'logements', 'analytics'],
                'suggestions' => [
                    'Identifier les meilleures pratiques',
                    'Améliorer les agences sous-performantes',
                    'Récompenser les top performers',
                    'Exporter le rapport de performance',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('AnalyticsHandler.performanceAnalysis error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de l'analyse de performance.",
                'data' => [],
                'analytics' => [],
                'sources' => ['analytics'],
                'suggestions' => ['Réessayer'],
            ];
        }
    }
    
    private function conversionAnalysis(array $intent): array
    {
        try {
            $timeframe = $intent['timeframe'] ?? 'month';
            $startDate = $this->getStartDate($timeframe);
            
            try {
                $visits = Visit::where('visit_date', '>=', $startDate)->get();
            } catch (\Throwable $e) {
                Log::warning('AnalyticsHandler: Failed to load visits', ['error' => $e->getMessage()]);
                $visits = collect([]);
            }
            
            try {
                $contracts = Contract::where('start_date', '>=', $startDate)->get();
            } catch (\Throwable $e) {
                Log::warning('AnalyticsHandler: Failed to load contracts', ['error' => $e->getMessage()]);
                $contracts = collect([]);
            }
        
        $analytics = [
            'total_visits' => $visits->count(),
            'total_contracts' => $contracts->count(),
            'conversion_rate' => $visits->count() > 0 
                ? round(($contracts->count() / $visits->count()) * 100, 2) 
                : 0,
            'unique_clients_visited' => $visits->pluck('client_id')->unique()->count(),
            'unique_clients_contracted' => $contracts->pluck('client_id')->unique()->count(),
            'avg_visits_before_contract' => $this->calculateAvgVisitsBeforeContract($visits, $contracts),
        ];
        
            return [
                'answer' => "Analyse de conversion ({$timeframe}): " . ($analytics['total_visits'] ?? 0) . " visite(s) → " . ($analytics['total_contracts'] ?? 0) . " contrat(s). " .
                           "Taux de conversion: " . number_format($analytics['conversion_rate'] ?? 0, 2, ',', ' ') . "%.",
                'data' => [
                    'visits' => $visits->count(),
                    'contracts' => $contracts->count(),
                ],
                'analytics' => $analytics,
                'sources' => ['visits', 'contracts', 'analytics'],
                'suggestions' => [
                    'Améliorer le taux de conversion',
                    'Analyser les visites non converties',
                    'Optimiser le processus de vente',
                    'Exporter l\'analyse de conversion',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('AnalyticsHandler.conversionAnalysis error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de l'analyse de conversion.",
                'data' => [],
                'analytics' => [],
                'sources' => ['analytics'],
                'suggestions' => ['Réessayer'],
            ];
        }
    }
    
    private function marketAnalysis(array $intent): array
    {
        try {
            try {
                $logements = Logement::with('category')->get();
            } catch (\Throwable $e) {
                Log::warning('AnalyticsHandler: Failed to load logements with category', ['error' => $e->getMessage()]);
                $logements = Logement::get();
            }
        
        $analytics = [
            'total' => $logements->count(),
            'available' => $logements->where('free', true)->count(),
            'price_avg' => $logements->avg('price'),
            'price_median' => $logements->median('price'),
            'by_category' => $logements->groupBy('category_id')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'avg_price' => round($group->avg('price'), 2),
                    'available' => $group->where('free', true)->count(),
                ];
            }),
            'price_range' => [
                'min' => $logements->min('price'),
                'max' => $logements->max('price'),
            ],
        ];
        
            return [
                'answer' => "Analyse du marché: " . ($analytics['total'] ?? 0) . " logement(s), " . ($analytics['available'] ?? 0) . " disponible(s). " .
                           "Prix moyen: " . number_format($analytics['price_avg'] ?? 0, 0, ',', ' ') . " TND. " .
                           "Fourchette: " . number_format($analytics['price_range']['min'] ?? 0, 0, ',', ' ') . " - " . 
                           number_format($analytics['price_range']['max'] ?? 0, 0, ',', ' ') . " TND.",
                'data' => $logements->take(20),
                'analytics' => $analytics,
                'sources' => ['logements', 'analytics'],
                'suggestions' => [
                    'Optimiser les prix',
                    'Analyser la demande',
                    'Identifier les opportunités',
                    'Exporter l\'analyse de marché',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('AnalyticsHandler.marketAnalysis error', ['error' => $e->getMessage()]);
            return [
                'answer' => "Erreur lors de l'analyse du marché.",
                'data' => [],
                'analytics' => [],
                'sources' => ['analytics'],
                'suggestions' => ['Réessayer'],
            ];
        }
    }
    
    private function getStartDate(string $timeframe): string
    {
        return match($timeframe) {
            'today' => now()->startOfDay()->toDateTimeString(),
            'week' => now()->startOfWeek()->toDateTimeString(),
            'month' => now()->startOfMonth()->toDateTimeString(),
            'year' => now()->startOfYear()->toDateTimeString(),
            default => now()->startOfMonth()->toDateTimeString(),
        };
    }
    
    private function calculateTrends(string $timeframe): array
    {
        // Simple trend calculation
        $current = $this->getMetricForPeriod($timeframe);
        $previous = $this->getMetricForPeriod($timeframe, true);
        
        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0,
        ];
    }
    
    private function getMetricForPeriod(string $timeframe, bool $previous = false): float
    {
        $start = $this->getStartDate($timeframe);
        if ($previous) {
            $start = Carbon::parse($start)->sub($timeframe === 'month' ? '1 month' : '1 week')->toDateTimeString();
        }
        
        return PaymentContracts::where('date_paiement', '>=', $start)->sum('montant');
    }
    
    private function calculateGrowth($byMonth): float
    {
        $months = array_values($byMonth->toArray());
        if (count($months) < 2) return 0;
        
        $current = end($months);
        $previous = $months[count($months) - 2];
        
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
    }
    
    private function calculateAvgVisitsBeforeContract($visits, $contracts): float
    {
        // Simplified calculation
        $clientVisits = $visits->groupBy('client_id')->map->count();
        $clientContracts = $contracts->pluck('client_id')->unique();
        
        $visitsBeforeContract = [];
        foreach ($clientContracts as $clientId) {
            if (isset($clientVisits[$clientId])) {
                $visitsBeforeContract[] = $clientVisits[$clientId];
            }
        }
        
        return !empty($visitsBeforeContract) ? round(array_sum($visitsBeforeContract) / count($visitsBeforeContract), 2) : 0;
    }
}

