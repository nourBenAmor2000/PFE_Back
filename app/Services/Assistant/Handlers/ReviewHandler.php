<?php

namespace App\Services\Assistant\Handlers;

use Modules\Review\App\Models\Review;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class ReviewHandler implements EntityHandler
{
    public function entity(): string
    {
        return 'review';
    }

    public function canHandle(string $entity): bool
    {
        return in_array(strtolower($entity), ['review', 'reviews', 'avis', 'commentaire', 'commentaires']);
    }

    public function handle(array $intent, ?Authenticatable $user = null): array
    {
        $q = Review::query();

        // Filter by rating
        if (isset($intent['filters']['rating'])) {
            $rating = (int) $intent['filters']['rating'];
            $q->where('rating', $rating);
        } elseif (isset($intent['filters']['min_rating'])) {
            $minRating = (int) $intent['filters']['min_rating'];
            $q->where('rating', '>=', $minRating);
        }

        // Filter by logement
        if (isset($intent['filters']['logement_id'])) {
            $q->where('logement_id', $intent['filters']['logement_id']);
        }

        // Filter by client
        if (isset($intent['filters']['client_id'])) {
            $q->where('client_id', $intent['filters']['client_id']);
        }

        // Type of query
        $type = $intent['type'] ?? 'list';
        if ($type === 'count') {
            $count = $q->count();
            $avgRating = $q->avg('rating');
            return [
                'answer' => "{$count} avis trouvé(s) avec une note moyenne de " . number_format($avgRating, 1) . "/5.",
                'data' => ['count' => $count, 'avg_rating' => $avgRating],
                'sources' => ['reviews'],
                'suggestions' => ['Voir la liste complète', 'Filtrer par note'],
            ];
        }

        // List reviews with relationships
        try {
            $reviews = $q->with(['client', 'logement'])->orderBy('created_at', 'desc')->limit(50)->get();
        } catch (\Throwable $e) {
            Log::warning('ReviewHandler: Failed to load relationships', ['error' => $e->getMessage()]);
            $reviews = $q->orderBy('created_at', 'desc')->limit(50)->get();
        }
        $count = $reviews->count();
        $avgRating = $reviews->avg('rating');
        
        // Calculate detailed analytics
        $analytics = [
            'total' => $count,
            'avg_rating' => round($avgRating, 2),
        ];
        
        if ($count > 0) {
            // Rating distribution
            $ratingDist = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingDist[$i] = $reviews->where('rating', $i)->count();
            }
            $analytics['rating_distribution'] = $ratingDist;
            
            // Most reviewed logements
            $byLogement = $reviews->groupBy('logement_id')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'avg_rating' => round($group->avg('rating'), 2),
                ];
            })->sortByDesc('count')->take(5);
            $analytics['top_logements'] = $byLogement->toArray();
            
            // Recent reviews
            $recentCount = $reviews->filter(function($review) {
                return $review->created_at && $review->created_at->isAfter(now()->subDays(7));
            })->count();
            $analytics['recent_reviews'] = $recentCount;
        }

        // Build detailed answer
        $answer = $count > 0
            ? "{$count} avis trouvé(s) avec une note moyenne de " . number_format($avgRating, 1) . "/5."
            : "Aucun avis trouvé.";
            
        if (isset($analytics['recent_reviews']) && $analytics['recent_reviews'] > 0) {
            $answer .= " {$analytics['recent_reviews']} avis récent(s) (7 derniers jours).";
        }

        return [
            'answer' => $answer,
            'data' => $reviews->map(function($review) {
                try {
                    return [
                        '_id' => $review->_id,
                        'client_id' => $review->client_id,
                        'logement_id' => $review->logement_id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'client_name' => $review->client->name ?? 'N/A',
                        'logement_title' => $review->logement->title ?? 'N/A',
                        'created_at' => $review->created_at,
                    ];
                } catch (\Throwable $e) {
                    return [
                        '_id' => $review->_id ?? 'N/A',
                        'client_id' => $review->client_id ?? 'N/A',
                        'logement_id' => $review->logement_id ?? 'N/A',
                        'rating' => $review->rating ?? 0,
                        'comment' => $review->comment ?? '',
                        'client_name' => 'N/A',
                        'logement_title' => 'N/A',
                        'created_at' => $review->created_at ?? null,
                    ];
                }
            }),
            'analytics' => $analytics,
            'sources' => ['reviews'],
            'suggestions' => $count > 0 ? [
                'Analyser la satisfaction client',
                'Identifier les logements les mieux notés',
                'Améliorer les logements mal notés',
                'Exporter les données',
            ] : [],
        ];
    }
}


