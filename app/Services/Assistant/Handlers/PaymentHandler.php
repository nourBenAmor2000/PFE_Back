<?php

namespace App\Services\Assistant\Handlers;

use Modules\PaymentContracts\App\Models\PaymentContracts;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class PaymentHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'payment';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['payment', 'payments', 'paiement', 'paiements']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        $q = PaymentContracts::query();

        // Filter by status
        if (isset($intent['filters']['status'])) {
            $q->where('statut', $intent['filters']['status']);
        }

        // Filter by timeframe
        $timeframe = $intent['timeframe'] ?? null;
        if ($timeframe === 'today') {
            $q->whereDate('date_paiement', today());
        } elseif ($timeframe === 'week') {
            $q->where('date_paiement', '>=', now()->startOfWeek());
        } elseif ($timeframe === 'month') {
            $q->where('date_paiement', '>=', now()->startOfMonth());
        }

        // Type of query
        $type = $intent['type'] ?? 'list';
        if ($type === 'count') {
            $count = $q->count();
            $total = $q->sum('montant');
            return [
                'answer' => "{$count} paiement(s) trouvé(s) pour un total de " . number_format($total, 2) . " TND.",
                'data' => ['count' => $count, 'total' => $total],
                'sources' => ['payments'],
                'suggestions' => ['Voir la liste complète', 'Exporter en CSV'],
            ];
        }

        // List payments with relationships
        try {
            $payments = $q->with('contract')->orderBy('date_paiement', 'desc')->limit(50)->get();
        } catch (\Throwable $e) {
            Log::warning('PaymentHandler: Failed to load relationships', ['error' => $e->getMessage()]);
            $payments = $q->orderBy('date_paiement', 'desc')->limit(50)->get();
        }
        $count = $payments->count();
        $total = $payments->sum('montant');
        
        // Calculate detailed analytics
        $analytics = [
            'total' => $count,
            'total_amount' => $total,
            'avg_amount' => $count > 0 ? round($total / $count, 2) : 0,
        ];
        
        if ($count > 0) {
            // Group by status
            $byStatus = $payments->groupBy('statut')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('montant'),
                ];
            });
            $analytics['by_status'] = $byStatus->toArray();
            
            // Group by payment method
            $byMethod = $payments->groupBy('methode_paiement')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('montant'),
                ];
            });
            $analytics['by_method'] = $byMethod->toArray();
            
            // Time analysis
            $byMonth = $payments->groupBy(function($payment) {
                return $payment->date_paiement ? date('Y-m', strtotime($payment->date_paiement)) : 'unknown';
            })->map->sum('montant');
            $analytics['by_month'] = $byMonth->toArray();
            
            // Status breakdown
            $pending = $payments->where('statut', 'pending')->count();
            $completed = $payments->where('statut', 'completed')->count();
            $analytics['pending_count'] = $pending;
            $analytics['completed_count'] = $completed;
        }

        // Build detailed answer
        $answer = $count > 0
            ? "{$count} paiement(s) trouvé(s) pour un total de " . number_format($total, 2, ',', ' ') . " TND."
            : "Aucun paiement trouvé.";
            
        if (isset($analytics['avg_amount']) && $analytics['avg_amount'] > 0) {
            $answer .= " Montant moyen: " . number_format($analytics['avg_amount'], 2, ',', ' ') . " TND.";
        }
        if (isset($analytics['pending_count']) && $analytics['pending_count'] > 0) {
            $answer .= " {$analytics['pending_count']} en attente.";
        }

        return [
            'answer' => $answer,
            'data' => $payments->map(function($payment) {
                try {
                    return [
                        '_id' => $payment->_id,
                        'contract_id' => $payment->contract_id,
                        'montant' => $payment->montant,
                        'methode_paiement' => $payment->methode_paiement,
                        'statut' => $payment->statut,
                        'date_paiement' => $payment->date_paiement,
                        'reference_transaction' => $payment->reference_transaction ?? null,
                        'contract_amount' => $payment->contract->amount ?? null,
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $payment->_id ?? 'N/A',
                        'contract_id' => $payment->contract_id ?? 'N/A',
                        'montant' => $payment->montant ?? 0,
                        'methode_paiement' => $payment->methode_paiement ?? 'N/A',
                        'statut' => $payment->statut ?? 'N/A',
                        'date_paiement' => $payment->date_paiement ?? null,
                        'reference_transaction' => $payment->reference_transaction ?? null,
                        'contract_amount' => null,
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['payments'],
            'suggestions' => $count > 0 ? [
                'Analyser les revenus',
                'Optimiser les paiements',
                'Prévoir les revenus futurs',
                'Exporter en CSV',
            ] : [],
        ];
    }
}


