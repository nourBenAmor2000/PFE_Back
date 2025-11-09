<?php
declare(strict_types=1);

namespace App\Services\Assistant\Handlers;

use Modules\Visit\App\Models\Visit;
// use Illuminate\Support\Facades\Auth;

final class VisitHandler
{
    public function handle(array $intent): array
    {
        // 🔎 debug: combien au total ?
        $total = Visit::count();

        $q = Visit::query();

        // RBAC (désactivé si pas de agency_id dans tes docs)
        // $agencyId = auth()->user()?->agency_id;
        // if ($agencyId) $q->where('agency_id', $agencyId);

        // ⏱ timeframe
        $tf = $intent['timeframe'] ?? 'today';
        $tz = 'UTC'; // très important car tes dates sont stockées en UTC (string)

        if ($tf === 'today') {
            $day = now($tz)->format('Y-m-d');
            $q->where('visit_date', '>=', $day.' 00:00:00')
              ->where('visit_date', '<=', $day.' 23:59:59');
        }

        $rows = $q->orderBy('visit_date', 'desc')
                  ->limit(50)
                  ->get(['_id','client_id','logement_id','visit_date']);

        // 🔎 debug: combien pour today ?
        $todayCount = $rows->count();

        // ✅ renvoyer les données (pas juste un message)
        if ($todayCount === 0) {
            return [
                'answer' => "Aucune visite trouvée pour aujourd'hui.",
                'data' => [
                    'debug' => [
                        'entity' => 'visit',
                        'timeframe' => $tf,
                        'total_in_collection' => $total,
                        'today_count' => $todayCount,
                    ],
                ],
                'sources' => ['visits'],
                'suggestions' => ['Voir la semaine', 'Créer une visite'],
            ];
        }

        return [
            'answer' => "{$todayCount} visite(s) trouvée(s) pour aujourd'hui.",
            'data' => $rows,
            'sources' => ['visits'],
            'suggestions' => ['Filtrer par client', 'Exporter CSV'],
        ];
    }
}
