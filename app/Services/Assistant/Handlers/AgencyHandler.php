<?php

namespace App\Services\Assistant\Handlers;

use Modules\Agency\App\Models\Agency;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class AgencyHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'agency';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['agency', 'agencies', 'agence', 'agences']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        $q = Agency::query();

        // Filter by city
        if (isset($intent['filters']['city'])) {
            $q->where('city', 'like', '%' . $intent['filters']['city'] . '%');
        }

        // Filter by location
        if (isset($intent['filters']['location'])) {
            $q->where('location', 'like', '%' . $intent['filters']['location'] . '%');
        }

        // Type of query
        $type = $intent['type'] ?? 'list';
        if ($type === 'count') {
            $count = $q->count();
            return [
                'answer' => "{$count} agence(s) trouvée(s).",
                'data' => ['count' => $count],
                'sources' => ['agencies'],
                'suggestions' => ['Voir la liste complète', 'Filtrer par ville'],
            ];
        }

        // List agencies with relationships
        try {
            $agencies = $q->with(['agents', 'logements'])->orderBy('name')->limit(50)->get();
        } catch (\Throwable $e) {
            Log::warning('AgencyHandler: Failed to load relationships', ['error' => $e->getMessage()]);
            $agencies = $q->orderBy('name')->limit(50)->get();
        }
        $count = $agencies->count();
        
        // Calculate analytics
        $analytics = [
            'total' => $count,
        ];
        
        if ($count > 0) {
            $totalAgents = $agencies->sum(fn($a) => $a->agents->count());
            $totalLogements = $agencies->sum(fn($a) => $a->logements->count());
            $analytics['total_agents'] = $totalAgents;
            $analytics['total_logements'] = $totalLogements;
            $analytics['avg_agents_per_agency'] = round($totalAgents / $count, 2);
            $analytics['avg_logements_per_agency'] = round($totalLogements / $count, 2);
            
            // Group by city
            $byCity = $agencies->groupBy('city')->map->count();
            $analytics['by_city'] = $byCity->toArray();
            
            // Top agencies by logements
            $topAgencies = $agencies->sortByDesc(fn($a) => $a->logements->count())->take(5)->map(function($agency) {
                return [
                    'name' => $agency->name,
                    'logements_count' => $agency->logements->count(),
                ];
            });
            $analytics['top_agencies'] = $topAgencies->values()->toArray();
        }

        // Build detailed answer
        $answer = $count > 0
            ? "{$count} agence(s) trouvée(s)."
            : "Aucune agence trouvée.";
            
        if (isset($analytics['total_agents'])) {
            $answer .= " Total: {$analytics['total_agents']} agent(s), {$analytics['total_logements']} logement(s).";
        }

        return [
            'answer' => $answer,
            'data' => $agencies->map(function($agency) {
                try {
                    return [
                        '_id' => $agency->_id,
                        'name' => $agency->name,
                        'address' => $agency->address ?? null,
                        'phone' => $agency->phone ?? null,
                        'city' => $agency->city ?? null,
                        'location' => $agency->location ?? null,
                        'agents_count' => $agency->agents ? $agency->agents->count() : 0,
                        'logements_count' => $agency->logements ? $agency->logements->count() : 0,
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $agency->_id ?? 'N/A',
                        'name' => $agency->name ?? 'N/A',
                        'address' => $agency->address ?? null,
                        'phone' => $agency->phone ?? null,
                        'city' => $agency->city ?? null,
                        'location' => $agency->location ?? null,
                        'agents_count' => 0,
                        'logements_count' => 0,
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['agencies'],
            'suggestions' => $count > 0 ? [
                'Analyser la performance des agences',
                'Comparer les agences',
                'Optimiser la répartition',
                'Exporter les données',
            ] : ['Créer une agence'],
        ];
    }
}


